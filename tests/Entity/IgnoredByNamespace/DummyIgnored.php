<?php

namespace Idlab\Loggable\Tests\Entity\IgnoredByNamespace;

use Doctrine\ORM\Mapping as ORM;
use Idlab\Loggable\Mapping\Attributes\IdlabLoggable;

#[ORM\Entity]
class DummyIgnored
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;

    #[ORM\Column(nullable: true)]
    #[IdlabLoggable]
    public ?string $value = null;

    public function getId(): ?int
    {
        return $this->id;
    }
}
