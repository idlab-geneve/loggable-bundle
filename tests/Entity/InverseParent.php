<?php

namespace Idlab\Loggable\Tests\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Idlab\Loggable\Mapping\Attributes\IdlabLoggable;

#[ORM\Entity]
#[IdlabLoggable]
class InverseParent
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;

    /** @var Collection<int, InverseChild> */
    #[ORM\OneToMany(targetEntity: InverseChild::class, mappedBy: 'parent')]
    public Collection $children;

    public function __construct()
    {
        $this->children = new ArrayCollection();
    }
}
