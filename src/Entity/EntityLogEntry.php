<?php

namespace Idlab\Loggable\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'entity_log_entries')]
class EntityLogEntry
{
    public const ACTION_CREATE = 'create';
    public const ACTION_UPDATE = 'update';
    public const ACTION_REMOVE = 'remove';

    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 8, nullable: false)]
    protected ?string $action;

    #[ORM\Column(type: 'string', length: 8, nullable: true)]
    protected ?string $collectionAction;

    #[ORM\Column(type: 'integer', nullable: false)]
    protected ?int $objectId;

    #[ORM\Column(type: 'string', nullable: false)]
    protected ?string $objectClass;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $data;

    #[ORM\Column(type: 'datetime_immutable', nullable: false)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $createdBy;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $impersonatedBy;

    public function __construct(
        string $action,
        ?string $createdBy,
        int $objectId,
        string $objectClass,
        ?array $data = null,
        ?string $impersonatedBy = null,
        ?string $collectionAction = null
    ) {
        $this->createdAt      = new \DateTimeImmutable();
        $this->action         = $action;
        $this->createdBy      = $createdBy ?: 'anonymous';
        $this->data           = $data;
        $this->objectId       = $objectId;
        $this->objectClass    = $objectClass;
        $this->impersonatedBy = $impersonatedBy;
        $this->collectionAction = $collectionAction;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAction(): ?string
    {
        return $this->action;
    }

    public function setAction(?string $action): void
    {
        $this->action = $action;
    }

    public function getCollectionAction(): ?string
    {
        return $this->collectionAction;
    }

    public function setCollectionAction(?string $collectionAction): void
    {
        $this->collectionAction = $collectionAction;
    }

    public function getObjectId(): ?string
    {
        return $this->objectId;
    }

    public function setObjectId(?string $objectId): void
    {
        $this->objectId = $objectId;
    }

    public function getObjectClass(): ?string
    {
        return $this->objectClass;
    }

    public function setObjectClass(?string $objectClass): void
    {
        $this->objectClass = $objectClass;
    }

    public function getData(): ?array
    {
        return $this->data;
    }

    public function setData(?array $data): void
    {
        $this->data = $data;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getCreatedBy(): string
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?string $createdBy): void
    {
        $this->createdBy = $createdBy;
    }

    public function getImpersonatedBy(): ?string
    {
        return $this->impersonatedBy;
    }

    public function setImpersonatedBy(?string $impersonatedBy): void
    {
        $this->impersonatedBy = $impersonatedBy;
    }
}
