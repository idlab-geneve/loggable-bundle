<?php

namespace Idlab\Loggable\Tests\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class DummyEntity
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;

    #[ORM\Column(nullable: true)]
    public ?string $value = null;

    public function getId(): ?int
    {
        return $this->id;
    }
}
