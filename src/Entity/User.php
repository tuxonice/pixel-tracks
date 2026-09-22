<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
class User implements UserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    private string $key;

    #[ORM\Column(length: 255, unique: true)]
    private string $email;

    #[ORM\Column(length: 5, options: ['default' => 'en'])]
    private string $locale = 'en';

    #[ORM\Column(nullable: true)]
    private ?string $loginCodeHash = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $loginCodeExpiresAt = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $loginCodeAttempts = 0;

    /** @var Collection<int, Track> */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Track::class, orphanRemoval: true)]
    private Collection $tracks;

    public function __construct(string $email)
    {
        $this->email = $email;
        $this->key = (string) Uuid::v4();
        $this->tracks = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    /** @return Collection<int, Track> */
    public function getTracks(): Collection
    {
        return $this->tracks;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): void
    {
        $this->locale = $locale;
    }

    public function getLoginCodeHash(): ?string
    {
        return $this->loginCodeHash;
    }

    public function setLoginCodeHash(string $loginCodeHash): void
    {
        $this->loginCodeHash = $loginCodeHash;
    }

    public function getLoginCodeExpiresAt(): ?\DateTimeImmutable
    {
        return $this->loginCodeExpiresAt;
    }

    public function setLoginCodeExpiresAt(\DateTimeImmutable $loginCodeExpiresAt): void
    {
        $this->loginCodeExpiresAt = $loginCodeExpiresAt;
    }

    public function getLoginCodeAttempts(): int
    {
        return $this->loginCodeAttempts;
    }

    public function setLoginCodeAttempts(int $loginCodeAttempts): void
    {
        $this->loginCodeAttempts = $loginCodeAttempts;
    }

    public function incrementLoginCodeAttempts(): void
    {
        ++$this->loginCodeAttempts;
    }

    public function clearLoginCode(): void
    {
        $this->loginCodeHash = null;
        $this->loginCodeExpiresAt = null;
        $this->loginCodeAttempts = 0;
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    /** @return list<string> */
    public function getRoles(): array
    {
        return ['ROLE_USER'];
    }

    public function eraseCredentials(): void
    {
    }
}
