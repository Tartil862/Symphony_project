<?php

namespace App\Entity;

use App\Repository\BoycottProductRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BoycottProductRepository::class)]
class BoycottProduct
{
    public const REASON_ETHICAL = 'ethical';
    public const REASON_POLITICAL = 'political';
    public const REASON_ENVIRONMENTAL = 'environmental';

    public const LEVEL_LOCAL = 'local';
    public const LEVEL_GLOBAL = 'global';

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    private ?string $brand = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image = null;

    #[ORM\Column(length: 50)]
    private ?string $reason = null;

    #[ORM\Column(length: 20)]
    private ?string $boycottLevel = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $description = null;

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column]
    private int $voteScore = 0;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $submittedBy = null;

    /** @var Collection<int, Alternative> */
    #[ORM\OneToMany(targetEntity: Alternative::class, mappedBy: 'boycottProduct', orphanRemoval: true)]
    private Collection $alternatives;

    /** @var Collection<int, Vote> */
    #[ORM\OneToMany(targetEntity: Vote::class, mappedBy: 'boycottProduct', orphanRemoval: true)]
    private Collection $votes;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->alternatives = new ArrayCollection();
        $this->votes = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getBrand(): ?string
    {
        return $this->brand;
    }

    public function setBrand(string $brand): static
    {
        $this->brand = $brand;
        return $this;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): static
    {
        $this->image = $image;
        return $this;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function setReason(string $reason): static
    {
        $this->reason = $reason;
        return $this;
    }

    public function getBoycottLevel(): ?string
    {
        return $this->boycottLevel;
    }

    public function setBoycottLevel(string $boycottLevel): static
    {
        $this->boycottLevel = $boycottLevel;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getVoteScore(): int
    {
        return $this->voteScore;
    }

    public function setVoteScore(int $voteScore): static
    {
        $this->voteScore = $voteScore;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getSubmittedBy(): ?User
    {
        return $this->submittedBy;
    }

    public function setSubmittedBy(?User $submittedBy): static
    {
        $this->submittedBy = $submittedBy;
        return $this;
    }

    /** @return Collection<int, Alternative> */
    public function getAlternatives(): Collection
    {
        return $this->alternatives;
    }

    public function addAlternative(Alternative $alternative): static
    {
        if (!$this->alternatives->contains($alternative)) {
            $this->alternatives->add($alternative);
            $alternative->setBoycottProduct($this);
        }
        return $this;
    }

    public function removeAlternative(Alternative $alternative): static
    {
        if ($this->alternatives->removeElement($alternative)) {
            if ($alternative->getBoycottProduct() === $this) {
                $alternative->setBoycottProduct(null);
            }
        }
        return $this;
    }

    /** @return Collection<int, Vote> */
    public function getVotes(): Collection
    {
        return $this->votes;
    }

    // ── Helper methods ──

    public function getReasonLabel(): string
    {
        return match ($this->reason) {
            self::REASON_ETHICAL => '🤝 Éthique',
            self::REASON_POLITICAL => '🏳️ Politique',
            self::REASON_ENVIRONMENTAL => '🌿 Environnemental',
            default => $this->reason,
        };
    }

    public function getReasonColor(): string
    {
        return match ($this->reason) {
            self::REASON_ETHICAL => '#F1947C',
            self::REASON_POLITICAL => '#E54A5F',
            self::REASON_ENVIRONMENTAL => '#91B893',
            default => '#E5C6A4',
        };
    }

    public function getLevelLabel(): string
    {
        return match ($this->boycottLevel) {
            self::LEVEL_LOCAL => '📍 Local',
            self::LEVEL_GLOBAL => '🌍 Global',
            default => $this->boycottLevel,
        };
    }

    public function getStatusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => '⏳ En attente',
            self::STATUS_APPROVED => '✅ Approuvé',
            self::STATUS_REJECTED => '❌ Rejeté',
            default => $this->status,
        };
    }

    public function getStatusColor(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => '#F1947C',
            self::STATUS_APPROVED => '#91B893',
            self::STATUS_REJECTED => '#E54A5F',
            default => '#E5C6A4',
        };
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function __toString(): string
    {
        return $this->name ?? '';
    }
}
