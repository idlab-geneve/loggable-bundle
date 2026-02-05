<?php

namespace Idlab\Loggable\Tests\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;

class DummyUser implements UserInterface
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;

    #[ORM\Column(nullable: false)]
    public string $username;

    #[ORM\Column(nullable: false)]
    public string $password;

    #[ORM\Column(nullable: false)]
    public string $salt;

    #[ORM\Column(nullable: false)]
    public array $roles;

    public function __construct(string $username, string $password, array $roles)
    {
        $this->username = $username;
        $this->password = $password;
        $this->salt = 'fake_salt';
        $this->roles = $roles;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRoles(): array
    {
        return $this->roles;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function getSalt(): string
    {
        return $this->salt;
    }

    public function eraseCredentials(): void
    {
        // TODO: Implement eraseCredentials() method.
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getUserIdentifier(): string
    {
        return $this->getUsername();
    }
}
