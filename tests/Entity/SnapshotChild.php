<?php

namespace Idlab\Loggable\Tests\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Idlab\Loggable\Mapping\Attributes\IdlabLoggable;

#[ORM\Entity]
#[IdlabLoggable]
class SnapshotChild
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;

    /** @var Collection<int, SnapshotEntity> */
    #[ORM\ManyToMany(targetEntity: SnapshotEntity::class, mappedBy: 'children')]
    public Collection $parents;

    public function __construct()
    {
        $this->parents = new ArrayCollection();
    }
}
