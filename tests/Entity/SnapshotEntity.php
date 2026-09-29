<?php

namespace Idlab\Loggable\Tests\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Idlab\Loggable\Mapping\Attributes\IdlabLoggable;

#[ORM\Entity]
#[IdlabLoggable]
class SnapshotEntity
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;

    #[ORM\Column(nullable: true)]
    public ?string $value = null;

    #[ORM\ManyToOne(targetEntity: SnapshotChild::class)]
    public ?SnapshotChild $child = null;

    #[ORM\Column(nullable: true)]
    private ?string $privateValue = null;

    #[ORM\ManyToOne(targetEntity: SnapshotChild::class)]
    private ?SnapshotChild $privateChild = null;

    /** @var Collection<int, SnapshotChild> */
    #[ORM\ManyToMany(targetEntity: SnapshotChild::class, inversedBy: 'parents')]
    #[ORM\JoinTable(name: 'snapshot_entity_children')]
    public Collection $children;

    public function __construct()
    {
        $this->children = new ArrayCollection();
    }

    public function setPrivateValue(?string $value): void
    {
        $this->privateValue = $value;
    }

    public function setPrivateChild(?SnapshotChild $child): void
    {
        $this->privateChild = $child;
    }
}
