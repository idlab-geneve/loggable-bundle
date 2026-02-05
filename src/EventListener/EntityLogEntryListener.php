<?php

namespace Idlab\Loggable\EventListener;

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
        public readonly string $logTargetConnectionName = 'default',
        private readonly ManagerRegistry $registry,
        private readonly Security $security,
        public readonly JWTTokenManagerInterface $jWTManager,
        public readonly TokenStorageInterface $tokenStorageInterface,
    ) {
        $this->logsEntityManager       = $this->registry->getManager($logTargetConnectionName);
        $this->identifierConnectedUser = $this->security?->getUser()?->getUserIdentifier();

        $this->impersonatedBy = null;
        if ($tokenStorageInterface->getToken() instanceof SwitchUserToken) {
            $originalToken        = $tokenStorageInterface->getToken()?->getOriginalToken();
            $this->impersonatedBy = $originalToken->getUser()?->getUserIdentifier();
        }
    }

    private function getDisallowedNamespaces(): array
    {
        $disallowedNamespaces = [];

        return $disallowedNamespaces;
    }

    /*
     * Ne pas oublier d'exclure la classe de Log sur
     * laquelle on est en train de travailler, sinon
     * on part dans une boucle infinie...
     */
    private function getDisallowedEntities(): array
    {
        /*
         * Déjà gérés dans l'évaluation du namespace :
         * - App\EntityLegacyAcc\Common\Logger
         * - App\EntityLegacyAcc\Immo\ReconciliationLog
         * - App\EntityLegacyAcc\Plan\ActionLog
         * - App\EntityLegacyAcc\Plan\DemarcheLog
         * - App\Entity\EtlLog\EtlDataLog
         * - App\Entity\EtlLog\EtlJobLog
         */
        $entityLogEntryClass =  EntityLogEntry::class;
        $configuredDisallowedEntities = [];

        return array_merge([$entityLogEntryClass], $configuredDisallowedEntities);
    }

    private function supportEntity(string $className): bool
    {
        $disallowedNamespaces = $this->getDisallowedNamespaces();
        foreach ($disallowedNamespaces as $disallowedNamespace) {
            if (str_starts_with($className, $disallowedNamespace)) {
                return false;
            }
        }

        return !in_array($className, $this->getDisallowedEntities(), true);
    }

    /*
     * Ici, évaluer si la propriété a l'attribut "IdlabLoggable"
     * afin de savoir si on peut la version ou non
     */
    private function supportProperty(string $evaluatedPropertyName, string $evaluatedClassName): bool
    {
        $property                = new \ReflectionProperty($evaluatedClassName, $evaluatedPropertyName);
        $idlabLoggableAttributes = $property->getAttributes(IdlabLoggable::class);

        return count($idlabLoggableAttributes) > 0;
    }

    /*
     * Création d'une nouvelle entité
     */
    /**
     * @throws EntityNotFoundException
     * @throws \JsonException
     */
    public function postPersist(PostPersistEventArgs $args): void
    {
        $currentObject = $args->getObject();
        $className     = get_class($currentObject);

        if (!$this->supportEntity($className)) {
            return;
        }

        $defaultUow = $args->getObjectManager()->getUnitOfWork();
        $data       = $this->manageData($defaultUow, $className, $defaultUow->getEntityChangeSet($currentObject));

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
     * Récupération de l'ID de l'objet supprimé, avant qu'il ne soit plus disponible
     * dans le postRemove
     */
    public function preRemove(PreRemoveEventArgs $args): void
    {
        $currentObject = $args->getObject();
        $className     = get_class($currentObject);

        if (!$this->supportEntity($className)) {
            return;
        }

        $this->removedObjectId = $currentObject->getId() ?: null;
    }

    /*
     * Suppression d'une entité
     */
    public function postRemove(PostRemoveEventArgs $args): void
    {
        $currentObject = $args->getObject();
        $className     = get_class($currentObject);

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
        $uow                  = $defaultObjectManager->getUnitOfWork();

        // dump('getScheduledEntityInsertions', $uow->getScheduledEntityInsertions());
        // dump('getScheduledEntityDeletions', $uow->getScheduledEntityDeletions());
        // dump('getScheduledCollectionDeletions', $uow->getScheduledCollectionDeletions());
        // dump('getScheduledCollectionUpdates', $uow->getScheduledCollectionUpdates());

        $data = $entities = [];
        foreach ($uow->getScheduledEntityUpdates() as $entity) {
            $className = get_class($entity);
            if (!$this->supportEntity($className)) {
                continue;
            }

            // Champs "plats" et ManyToOne pris en compte
            $changeset     = $uow->getEntityChangeSet($entity);
            $changeSetData = $this->manageData($uow, $className, $changeset);

            if (count($changeSetData) > 0) {
                $data[$className . '_' . $entity->getId()]     = $changeSetData;
                $entities[$className . '_' . $entity->getId()] = $entity;
            }
        }

        // Collections modifiées (ajout / suppression partielle)
        foreach ($uow->getScheduledCollectionUpdates() as $collection) {
            $owner = $collection->getOwner();
            if (!$owner) {
                continue;
            }
            $ownerClassName = get_class($owner);
            $mapping        = $collection->getMapping();
            $ownerKey       = $ownerClassName . '_' . $owner->getId();
            $fieldName      = $mapping['fieldName'];

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

        // Collections supprimées entièrement (clear(), orphanRemoval, etc.)
        foreach ($uow->getScheduledCollectionDeletions() as $collection) {
            $owner = $collection->getOwner();
            if (!$owner) {
                continue;
            }
            $ownerClassName = get_class($owner);
            $mapping        = $collection->getMapping();
            $ownerKey       = $ownerClassName . '_' . $owner->getId();
            $fieldName      = $mapping['fieldName'];

            // ici, la collection est devenue VIDE
            $entities[$ownerKey]         = $owner;
            $data[$ownerKey][$fieldName] = [];
        }

        if (count($data) > 0) {
            foreach ($data as $key => $value) {
                $loggedEntity = $entities[$key];
                $className    = get_class($loggedEntity);

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
            $afterNormalized  = $this->normalizeValue($uow, $after);

            if ($beforeNormalized === $afterNormalized) {
                unset($changeSet[$key]);
                continue;
            }

            $changeSet[$key] = $this->formatAfterChange($uow, $after);
        }

        return $changeSet;
    }

    /*
     * Normaliser les valeurs afin de comparer les valeurs avec confiance
     */
    /**
     * @throws EntityNotFoundException
     */
    private function normalizeValue(UnitOfWork $uow, mixed $value): mixed
    {
        if (null === $value || '' === $value) {
            return null;
        }

        // scalaires
        if (is_numeric($value)) {
            return (string) +$value;
        }

        // DateTime
        if ($value instanceof \DateTimeInterface) {
            return $value->format(\DateTimeInterface::W3C);
        }

        // Entités Doctrine (proxy ou non)
        if (is_object($value) && $uow->isInIdentityMap($value) && $uow->getEntityIdentifier($value)) {
            return [
                '__entity__' => ClassUtils::getClass($value),
                'id'         => $uow->getEntityIdentifier($value),
            ];
        }

        // Tableaux (JSON, array)
        if (is_array($value)) {
            ksort($value);

            return array_map(fn($v) => $this->normalizeValue($uow, $v), $value);
        }

        // Objets JSON sérialisables
        if ($value instanceof \JsonSerializable) {
            return $this->normalizeValue($uow, $value->jsonSerialize());
        }

        // Fallback objet générique
        if (is_object($value)) {
            return [
                '__object__' => get_class($value),
                'hash'       => spl_object_hash($value),
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
        // Valeur "flat"
        if (null === $value || '' === $value || is_numeric($value)) {
            return $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format(\DateTimeInterface::W3C);
        }

        // Entités Doctrine (proxy ou non)
        if (is_object($value) && $uow->isInIdentityMap($value)) {
            return $uow->getEntityIdentifier($value);
        }

        // Tableaux (JSON, array)
        if (is_array($value)) {
            ksort($value);

            return json_encode(array_map(fn($v) => $this->normalizeValue($uow, $v), $value), JSON_THROW_ON_ERROR);
        }

        // Objets JSON sérialisables
        if ($value instanceof \JsonSerializable) {
            return $this->normalizeValue($uow, $value->jsonSerialize());
        }

        // Fallback objet générique
        if (is_object($value)) {
            return json_encode($value, JSON_THROW_ON_ERROR);
        }

        return $value;
    }
}
