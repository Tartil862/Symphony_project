<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private ?string $email = null;

    /**
     * @var list<string> The user roles
     */
    #[ORM\Column]
    private array $roles = [];

    /**
     * @var string The hashed password
     */
    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column]
    private int $reputation = 0;

    #[ORM\Column(nullable: true, options: ['default' => 0])]
    private ?float $walletBalance = 0.0;

    #[ORM\Column(length: 100)]
    private ?string $nom = null;

    #[ORM\Column(length: 100)]
    private ?string $prenom = null;

    /**
     * null  = pre-existing account (treated as verified for backward compat)
     * false = newly registered, awaiting email verification
     * true  = verified
     */
    #[ORM\Column(nullable: true)]
    private ?bool $isVerified = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $emailVerificationToken = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    #[\Deprecated]
    public function eraseCredentials(): void
    {
        // @deprecated, to be removed when upgrading to Symfony 8
    }

    public function getReputation(): int
    {
        return $this->reputation;
    }

    public function setReputation(int $reputation): static
    {
        $this->reputation = $reputation;
        return $this;
    }

    public function addReputation(int $points): static
    {
        $this->reputation += $points;
        return $this;
    }

    public function getWalletBalance(): float
    {
        return $this->walletBalance ?? 0.0;
    }

    public function setWalletBalance(float $walletBalance): static
    {
        $this->walletBalance = max(0.0, $walletBalance);
        return $this;
    }

    public function addWalletBalance(float $amount): static
    {
        $this->walletBalance += $amount;
        return $this;
    }

    public function getNom(): ?string { return $this->nom; }
    public function setNom(string $nom): static { $this->nom = $nom; return $this; }

    public function getPrenom(): ?string { return $this->prenom; }
    public function setPrenom(string $prenom): static { $this->prenom = $prenom; return $this; }

    /** null (old users) and true both count as verified */
    public function isVerified(): bool
    {
        return $this->isVerified !== false;
    }

    public function setIsVerified(bool $isVerified): static
    {
        $this->isVerified = $isVerified;
        return $this;
    }

    public function getEmailVerificationToken(): ?string
    {
        return $this->emailVerificationToken;
    }

    public function setEmailVerificationToken(?string $token): static
    {
        $this->emailVerificationToken = $token;
        return $this;
    }

    public function getFullName(): string
    {
        return trim(($this->prenom ?? '') . ' ' . ($this->nom ?? ''));
    }

    public function getReputationBadge(): string
    {
        return match (true) {
            $this->reputation >= 100 => '🏆 Expert',
            $this->reputation >= 50 => '⭐ Contributeur actif',
            $this->reputation >= 20 => '🌱 Membre engagé',
            $this->reputation >= 5 => '👤 Nouveau membre',
            default => '👋 Débutant',
        };
    }
}
