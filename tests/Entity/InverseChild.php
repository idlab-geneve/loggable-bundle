<?php

namespace Idlab\Loggable\Tests\Entity;

use Doctrine\ORM\Mapping as ORM;
use Idlab\Loggable\Mapping\Attributes\IdlabLoggable;

#[ORM\Entity]
#[IdlabLoggable]
class InverseChild
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;

    #[ORM\ManyToOne(targetEntity: InverseParent::class, inversedBy: 'children')]
    public ?InverseParent $parent = null;
}
