<?php

namespace Idlab\Loggable\Tests\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class SnapshotChild
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;
}
