<?php

namespace Idlab\Loggable\Service;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityNotFoundException;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\UnitOfWork;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Persistence\Proxy;
use Idlab\Loggable\Config\IdlabLoggableConfig;
use Idlab\Loggable\Entity\EntityLogEntry;
use Idlab\Loggable\Mapping\Attributes\IdlabLoggable;
use Idlab\Loggable\Mapping\Attributes\IdlabLoggableExclude;

final class EntitySnapshotter
{
    public function __construct(private readonly IdlabLoggableConfig $config) {}

    public function getClassName(object $object): string
    {
        $className = get_class($object);

        return $object instanceof Proxy ? (get_parent_class($object) ?: $className) : $className;
    }

    public function supportsClass(string $className, ClassMetadata $metadata): bool
    {
        foreach (['Proxies\\', ...$this->config->disallowedNamespaces] as $namespace) {
            if (str_starts_with($className, $namespace)) {
                return false;
            }
        }

        if ($className === EntityLogEntry::class || in_array($className, $this->config->disallowedClasses, true)) {
            return false;
        }

        return $this->hasLoggableProperty($className, $metadata);
    }

    public function hasLoggableProperty(string $className, ClassMetadata $metadata): bool
    {
        $classLogged = count((new \ReflectionClass($className))->getAttributes(IdlabLoggable::class)) > 0;

        foreach (array_merge($metadata->getFieldNames(), $metadata->getAssociationNames()) as $fieldName) {
            if ($this->supportsAssociation($fieldName, $metadata)
                && $this->supportsProperty($fieldName, $className, $metadata, $classLogged)) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, mixed> */
    public function snapshot(ObjectManager $objectManager, object $object, ?string $className = null): array
    {
        $className ??= $this->getClassName($object);
        $metadata = $objectManager->getClassMetadata($className);
        $uow = $objectManager->getUnitOfWork();
        $originalData = $uow->getOriginalEntityData($object);
        $classLogged = count((new \ReflectionClass($className))->getAttributes(IdlabLoggable::class)) > 0;
        $data = [];

        foreach (array_merge($metadata->getFieldNames(), $metadata->getAssociationNames()) as $fieldName) {
            if (!$this->supportsAssociation($fieldName, $metadata)
                || !$this->supportsProperty($fieldName, $className, $metadata, $classLogged)) {
                continue;
            }

            try {
                $value = $metadata->getFieldValue($object, $fieldName);
            } catch (\Throwable $exception) {
                if (!array_key_exists($fieldName, $originalData)) {
                    throw $exception;
                }
                $value = $originalData[$fieldName];
            }

            $data[$fieldName] = $value instanceof Collection
                ? $this->getIdentifiersFromCollection($objectManager, $value->getValues())
                : $this->formatValue($uow, $value);
        }

        return $data;
    }

    public function getIdentifier(ObjectManager $objectManager, object $object): ?string
    {
        $values = $objectManager->getClassMetadata($this->getClassName($object))->getIdentifierValues($object);
        if ($values === []) {
            return null;
        }

        return implode('-', array_map(fn(mixed $value): string => $this->formatIdentifier($objectManager, $value), $values));
    }

    public function supportsAssociation(string $fieldName, ClassMetadata $metadata): bool
    {
        if (!isset($metadata->associationMappings[$fieldName])) {
            return true;
        }

        return $this->config->includeInverseAssociations
            || ($metadata->associationMappings[$fieldName]['isOwningSide'] ?? true);
    }

    private function supportsProperty(string $fieldName, string $className, ClassMetadata $metadata, bool $classLogged): bool
    {
        $property = null;
        if (str_contains($fieldName, '.')) {
            [$embedded, $nested] = explode('.', $fieldName, 2);
            $property = new \ReflectionProperty($className, $embedded);
            if (isset($metadata->embeddedClasses[$embedded])) {
                $embeddedReflection = new \ReflectionClass($metadata->embeddedClasses[$embedded]['class']);
                if ($embeddedReflection->hasProperty($nested)) {
                    $property = new \ReflectionProperty($metadata->embeddedClasses[$embedded]['class'], $nested);
                }
            }
        } else {
            $property = new \ReflectionProperty($className, $fieldName);
        }

        if ($property->getAttributes(IdlabLoggableExclude::class) !== []) {
            return false;
        }

        return $classLogged || $property->getAttributes(IdlabLoggable::class) !== [];
    }

    private function getIdentifiersFromCollection(ObjectManager $objectManager, array $values): array
    {
        return array_map(function (mixed $value) use ($objectManager): mixed {
            return is_object($value) ? $this->getIdentifier($objectManager, $value) : $value;
        }, $values);
    }

    private function formatIdentifier(ObjectManager $objectManager, mixed $identifier): string
    {
        if (is_object($identifier)) {
            return $this->getIdentifier($objectManager, $identifier) ?? '';
        }
        if (is_array($identifier)) {
            return implode('-', array_map(fn(mixed $value): string => $this->formatIdentifier($objectManager, $value), $identifier));
        }

        return (string) $identifier;
    }

    /** @throws EntityNotFoundException|\JsonException */
    private function formatValue(UnitOfWork $uow, mixed $value): mixed
    {
        if (null === $value || '' === $value || is_numeric($value)) {
            return $value;
        }
        if ($value instanceof \BackedEnum) {
            return $value->value;
        }
        if ($value instanceof \UnitEnum) {
            return $value->name;
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format(\DateTimeInterface::W3C);
        }
        if (is_object($value) && $uow->isInIdentityMap($value)) {
            return $uow->getEntityIdentifier($value);
        }
        if (is_array($value)) {
            ksort($value);

            return json_encode(array_map(fn(mixed $item): mixed => $this->formatValue($uow, $item), $value), JSON_THROW_ON_ERROR);
        }
        if ($value instanceof \JsonSerializable) {
            return $this->formatValue($uow, $value->jsonSerialize());
        }
        if (is_object($value)) {
            return json_encode($value, JSON_THROW_ON_ERROR);
        }

        return $value;
    }
}
