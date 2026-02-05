<?php

namespace Idlab\Loggable\EventListener;

use Idlab\Loggable\Config\IdlabLoggableConfig;
use Idlab\Loggable\Entity\EntityLogEntry;
use Idlab\Loggable\Mapping\Attributes\IdlabLoggable;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\Common\Util\ClassUtils;
use Doctrine\ORM\EntityNotFoundException;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\UnitOfWork;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;
use Symfony\Component\Security\Core\Security;

#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::preRemove)]
#[AsDoctrineListener(event: Events::postRemove)]
#[AsDoctrineListener(event: Events::onFlush)]
class EntityLogEntryListener
{
    private ObjectManager $logsEntityManager;
    private ?int $removedObjectId = null;

    private ?string $identifierConnectedUser;
    private ?string $impersonatedBy;

    public function __construct(
        private readonly IdlabLoggableConfig     $config,
        private readonly ManagerRegistry         $registry,
        private readonly Security                $security,
        public readonly JWTTokenManagerInterface $jWTManager,
        public readonly TokenStorageInterface    $tokenStorageInterface,
    ) {
        $this->logsEntityManager = $this->registry->getManager($this->config->loginTargetConnectionName);
        $this->identifierConnectedUser = $this->security?->getUser()?->getUserIdentifier();

        // Save the original user if current impersonation and save connected user
        $this->impersonatedBy = null;
        if ($tokenStorageInterface->getToken() instanceof SwitchUserToken) {
            $originalToken = $tokenStorageInterface->getToken()?->getOriginalToken();
            $this->impersonatedBy = $originalToken->getUser()?->getUserIdentifier();
        }
    }

    private function getDisallowedNamespaces(): array
    {
        return $this->config->disallowedNamespaces;
    }

    /*
     * Don't forget to exclude the EntityLogEntry class
     * from IdlabLoggable
     * If you are currently working on, otherwise
     * you will end up in an infinite loop...
     */
    private function getDisallowedEntitiesClasses(): array
    {
        return array_merge(
            [EntityLogEntry::class],
            $this->config->disallowedClasses
        );
    }

    /*
     * Check if we support the entity in order to create logs for it
     */
    private function supportEntity(string $className): bool
    {
        $disallowedNamespaces = $this->getDisallowedNamespaces();
        foreach ($disallowedNamespaces as $disallowedNamespace) {
            if (str_starts_with($className, $disallowedNamespace)) {
                return false;
            }
        }

        return !in_array($className, $this->getDisallowedEntitiesClasses(), true);
    }

    /*
     * Here, evaluate whether the property has the “IdlabLoggable” attribute
     * in order to determine whether it can be versioned
     */
    private function supportProperty(string $evaluatedPropertyName, string $evaluatedClassName): bool
    {
        $property = new \ReflectionProperty($evaluatedClassName, $evaluatedPropertyName);
        $idlabLoggableAttributes = $property->getAttributes(IdlabLoggable::class);

        return count($idlabLoggableAttributes) > 0;
    }

    /**
     *
     * Creation of a new entity
     * @throws EntityNotFoundException
     * @throws \JsonException
     */
    public function postPersist(PostPersistEventArgs $args): void
    {
        $currentObject = $args->getObject();
        $className = get_class($currentObject);

        if (!$this->supportEntity($className)) {
            return;
        }

        $defaultUow = $args->getObjectManager()->getUnitOfWork();
        $data = $this->manageData($defaultUow, $className, $defaultUow->getEntityChangeSet($currentObject));

        $newLogEntry = new EntityLogEntry(
            EntityLogEntry::ACTION_CREATE,
            $this->identifierConnectedUser,
            $currentObject->getId(),
            $className,
            $data,
            $this->impersonatedBy,
        );

        $this->logsEntityManager->persist($newLogEntry);
        $this->logsEntityManager->flush();
    }

    /*
     * Retrieving the ID of the deleted object before it is no longer available
     * in postRemove
     */
    public function preRemove(PreRemoveEventArgs $args): void
    {
        $currentObject = $args->getObject();
        $className = get_class($currentObject);

        if (!$this->supportEntity($className)) {
            return;
        }

        $this->removedObjectId = $currentObject->getId() ?: null;
    }

    /*
     * When deleting an entity
     */
    public function postRemove(PostRemoveEventArgs $args): void
    {
        $currentObject = $args->getObject();
        $className = get_class($currentObject);

        if (!$this->supportEntity($className)) {
            return;
        }

        $this->logsEntityManager->persist(new EntityLogEntry(
            EntityLogEntry::ACTION_REMOVE,
            $this->identifierConnectedUser,
            $this->removedObjectId,
            $className,
            null,
            $this->impersonatedBy,
        ));

        $this->removedObjectId = null;
        $this->logsEntityManager->flush();
    }

    /**
     * @throws EntityNotFoundException
     * @throws \JsonException
     */
    public function onFlush(OnFlushEventArgs $args): void
    {
        $defaultObjectManager = $args->getObjectManager();
        $uow = $defaultObjectManager->getUnitOfWork();

        $data = $entities = [];
        foreach ($uow->getScheduledEntityUpdates() as $entity) {
            $className = get_class($entity);
            if (!$this->supportEntity($className)) {
                continue;
            }

            // "Flat" properties and ManyToOne considered
            $changeset = $uow->getEntityChangeSet($entity);
            $changeSetData = $this->manageData($uow, $className, $changeset);

            if (count($changeSetData) > 0) {
                $data[$className . '_' . $entity->getId()] = $changeSetData;
                $entities[$className . '_' . $entity->getId()] = $entity;
            }
        }

        // Updated collection (add or remove item)
        foreach ($uow->getScheduledCollectionUpdates() as $collection) {
            $owner = $collection->getOwner();
            if (!$owner) {
                continue;
            }
            $ownerClassName = get_class($owner);
            $mapping = $collection->getMapping();
            $ownerKey = $ownerClassName . '_' . $owner->getId();
            $fieldName = $mapping['fieldName'];

            if ($collection->getInsertDiff() || $collection->getDeleteDiff()) {
                $entities[$ownerKey] = $owner;
                foreach ($collection->getValues() as $value) {
                    $collectionItemClassName = get_class($value);
                    if (method_exists($collectionItemClassName, 'getId')) {
                        $data[$ownerKey][$fieldName][] = $value->getId();
                    } elseif (method_exists($collectionItemClassName, 'getUuid')) {
                        $data[$ownerKey][$fieldName][] = $value->getUuid();
                    } elseif (method_exists($collectionItemClassName, '__toString')) {
                        $data[$ownerKey][$fieldName][] = $value->__toString();
                    } else {
                        $data[$ownerKey][$fieldName][] = $collectionItemClassName;
                    }
                }
            }
        }

        // Collection completely removed (clear(), orphanRemoval, etc...)
        foreach ($uow->getScheduledCollectionDeletions() as $collection) {
            $owner = $collection->getOwner();
            if (!$owner) {
                continue;
            }
            $ownerClassName = get_class($owner);
            $mapping = $collection->getMapping();
            $ownerKey = $ownerClassName . '_' . $owner->getId();
            $fieldName = $mapping['fieldName'];

            // Here, collection became EMPTY
            $entities[$ownerKey] = $owner;
            $data[$ownerKey][$fieldName] = [];
        }

        if (count($data) > 0) {
            foreach ($data as $key => $value) {
                $loggedEntity = $entities[$key];
                $className = get_class($loggedEntity);

                $newLogEntry = new EntityLogEntry(
                    EntityLogEntry::ACTION_UPDATE,
                    $this->identifierConnectedUser,
                    $loggedEntity->getId(),
                    $className,
                    $value,
                    $this->impersonatedBy,
                );
                $this->logsEntityManager->persist($newLogEntry);
            }

            $this->logsEntityManager->flush();
        }
    }

    /**
     * @throws EntityNotFoundException
     * @throws \JsonException
     */
    private function manageData(UnitOfWork $uow, string $className, array $changeSet): array
    {
        foreach ($changeSet as $key => $change) {
            if (!$this->supportProperty($key, $className)) {
                unset($changeSet[$key]);
                continue;
            }

            [$before, $after] = [$change[0], $change[1]];

            $beforeNormalized = $this->normalizeValue($uow, $before);
            $afterNormalized = $this->normalizeValue($uow, $after);

            if ($beforeNormalized === $afterNormalized) {
                unset($changeSet[$key]);
                continue;
            }

            $changeSet[$key] = $this->formatAfterChange($uow, $after);
        }

        return $changeSet;
    }

    /*
     * Normalize the values to compare them with confidence
     */
    /**
     * @throws EntityNotFoundException
     */
    private function normalizeValue(UnitOfWork $uow, mixed $value): mixed
    {
        if (null === $value || '' === $value) {
            return null;
        }

        // Numeric value
        if (is_numeric($value)) {
            return (string) +$value;
        }

        // DateTimeInterface
        if ($value instanceof \DateTimeInterface) {
            return $value->format(\DateTimeInterface::W3C);
        }

        // Doctrine entity (proxy or not)
        if (is_object($value) && $uow->isInIdentityMap($value) && $uow->getEntityIdentifier($value)) {
            return [
                '__entity__' => ClassUtils::getClass($value),
                'id' => $uow->getEntityIdentifier($value),
            ];
        }

        // Arrays (JSON or simple array)
        if (is_array($value)) {
            ksort($value);

            return array_map(fn($v) => $this->normalizeValue($uow, $v), $value);
        }

        // Serializable JSON object
        if ($value instanceof \JsonSerializable) {
            return $this->normalizeValue($uow, $value->jsonSerialize());
        }

        // Fallback generic object
        if (is_object($value)) {
            return [
                '__object__' => get_class($value),
                'hash' => spl_object_hash($value),
            ];
        }

        return $value;
    }

    /**
     * @throws \JsonException
     * @throws EntityNotFoundException
     */
    private function formatAfterChange(UnitOfWork $uow, mixed $value): mixed
    {
        // "Flat" value
        if (null === $value || '' === $value || is_numeric($value)) {
            return $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format(\DateTimeInterface::W3C);
        }

        // Doctrine entity (proxy or not)
        if (is_object($value) && $uow->isInIdentityMap($value)) {
            return $uow->getEntityIdentifier($value);
        }

        // Arrays (JSON or simple array)
        if (is_array($value)) {
            ksort($value);

            return json_encode(array_map(fn($v) => $this->normalizeValue($uow, $v), $value), JSON_THROW_ON_ERROR);
        }

        // Serializable JSON object
        if ($value instanceof \JsonSerializable) {
            return $this->normalizeValue($uow, $value->jsonSerialize());
        }

        // Fallback generic object
        if (is_object($value)) {
            return json_encode($value, JSON_THROW_ON_ERROR);
        }

        return $value;
    }
}
