<?php

namespace Idlab\Loggable\Tests\Entity;

use Doctrine\ORM\Mapping as ORM;
use Idlab\Loggable\Mapping\Attributes\IdlabLoggable;
use Idlab\Loggable\Mapping\Attributes\IdlabLoggableExclude;

#[ORM\Entity]
#[IdlabLoggable]
class ClassLoggedEntity
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;

    #[ORM\Column(nullable: true)]
    public ?string $value = null;

    #[ORM\Column(nullable: true)]
    #[IdlabLoggableExclude]
    public ?string $excludedValue = null;

    #[ORM\Column(type: 'string', enumType: TestStatus::class)]
    public TestStatus $status = TestStatus::Draft;

}
