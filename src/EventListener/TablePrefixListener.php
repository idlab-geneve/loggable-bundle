<?php

namespace Idlab\Loggable\EventListener;

use Idlab\Loggable\Entity\EntityLogEntry;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping\ClassMetadataInfo;

#[AsDoctrineListener(event: Events::loadClassMetadata)]
class TablePrefixListener
{
    private array $config;

    public function setConfig(array $config): void
    {
        $this->config = $config;
    }

    public function loadClassMetadata(LoadClassMetadataEventArgs $eventArgs): void
    {
        $classMetadata = $eventArgs->getClassMetadata();
        $addPrefix     = EntityLogEntry::class === $classMetadata->getName();

        if (!$addPrefix) {
            return;
        }

        if (!$classMetadata->isInheritanceTypeSingleTable() || $classMetadata->getName() === $classMetadata->rootEntityName) {
            $classMetadata->setPrimaryTable([
                'name' => $this->getPrefix() . $classMetadata->getTableName(),
            ]);
        }

        foreach ($classMetadata->getAssociationMappings() as $fieldName => $mapping) {
            if (ClassMetadataInfo::MANY_TO_MANY === $mapping['type'] && $mapping['isOwningSide']) {
                $mappedTableName                                                     = $mapping['joinTable']['name'];
                $classMetadata->associationMappings[$fieldName]['joinTable']['name'] = $this->getPrefix() . $mappedTableName;
            }
        }
    }

    private function getPrefix(): string
    {
        return $this->config['idlab_loggable'] ?? '';
    }
}
