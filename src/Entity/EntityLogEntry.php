<?php

namespace Idlab\Loggable\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'entity_log_entries')]
#[ORM\Index(name: 'idx_log_class_lookup', columns: ['object_class'])]
#[ORM\Index(name: 'idx_log_date_lookup', columns: ['logged_at'])]
#[ORM\Index(name: 'idx_log_username_lookup', columns: ['username'])]
#[ORM\Index(name: 'idx_log_user_id_lookup', columns: ['user_id'])]
#[ORM\Index(name: 'idx_log_id_lookup', columns: ['object_class', 'object_id'])]
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
    protected string $action;

    #[ORM\Column(name: 'collection_action', type: 'string', length: 8, nullable: true)]
    protected ?string $collectionAction;

    #[ORM\Column(name: 'object_id', type: 'string', length: 64, nullable: false)]
    protected string $objectId;

    #[ORM\Column(name: 'object_class', type: 'string', nullable: false)]
    protected string $objectClass;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $data;

    #[ORM\Column(name: 'logged_at', type: 'datetime_immutable', nullable: false)]
    private \DateTimeImmutable $loggedAt;

    #[ORM\Column(type: 'string', nullable: false)]
    private string $username;

    #[ORM\Column(name: 'user_id', type: 'string', length: 64, nullable: false)]
    private string $userId;

    #[ORM\Column(name: 'impersonated_by', type: 'string', nullable: true)]
    private ?string $impersonatedBy;

    public function __construct(
        string $action,
        string $username,
        string $userId,
        string $objectId,
        string $objectClass,
        ?array $data = null,
        ?string $impersonatedBy = null,
        ?string $collectionAction = null
    ) {
        $this->loggedAt         = new \DateTimeImmutable();
        $this->action           = $action;
        $this->username         = $username;
        $this->userId           = $userId;
        $this->objectId         = $objectId;
        $this->objectClass      = $objectClass;
        $this->data             = $data;
        $this->impersonatedBy   = $impersonatedBy;
        $this->collectionAction = $collectionAction;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function getCollectionAction(): ?string
    {
        return $this->collectionAction;
    }

    public function getObjectId(): string
    {
        return $this->objectId;
    }

    public function getObjectClass(): string
    {
        return $this->objectClass;
    }

    public function getData(): ?array
    {
        return $this->data;
    }

    public function getLoggedAt(): \DateTimeImmutable
    {
        return $this->loggedAt;
    }

    /**
     * @deprecated since 2.0, use getLoggedAt() instead.
     */
    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->getLoggedAt();
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    /**
     * @deprecated since 2.0, use getUsername() instead.
     */
    public function getCreatedBy(): string
    {
        return $this->getUsername();
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function getImpersonatedBy(): ?string
    {
        return $this->impersonatedBy;
    }
}
