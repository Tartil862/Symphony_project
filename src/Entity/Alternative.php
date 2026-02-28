<?php

namespace App\Entity;

use App\Repository\AlternativeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AlternativeRepository::class)]
class Alternative
{
    public const STATUS_PENDING  = 'pending';
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

    #[ORM\Column(nullable: true)]
    private ?float $price = null;

    #[ORM\Column]
    private int $qualityRating = 3;

    #[ORM\Column(length: 100)]
    private ?string $origin = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image = null;

    #[ORM\Column]
    private int $voteScore = 0;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_PENDING;

    #[ORM\ManyToOne(inversedBy: 'alternatives')]
    #[ORM\JoinColumn(nullable: false)]
    private ?BoycottProduct $boycottProduct = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $suggestedBy = null;

    #[ORM\OneToOne(cascade: ['persist'])]
    #[ORM\JoinColumn(nullable: true)]
    private ?Product $product = null;

    /** @var Collection<int, Vote> */
    #[ORM\OneToMany(targetEntity: Vote::class, mappedBy: 'alternative', orphanRemoval: true)]
    private Collection $votes;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
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

    public function getPrice(): ?float
    {
        return $this->price;
    }

    public function setPrice(?float $price): static
    {
        $this->price = $price;
        return $this;
    }

    public function getQualityRating(): int
    {
        return $this->qualityRating;
    }

    public function setQualityRating(int $qualityRating): static
    {
        $this->qualityRating = max(1, min(5, $qualityRating));
        return $this;
    }

    public function getOrigin(): ?string
    {
        return $this->origin;
    }

    public function setOrigin(string $origin): static
    {
        $this->origin = $origin;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
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

    public function getBoycottProduct(): ?BoycottProduct
    {
        return $this->boycottProduct;
    }

    public function setBoycottProduct(?BoycottProduct $boycottProduct): static
    {
        $this->boycottProduct = $boycottProduct;
        return $this;
    }

    public function getSuggestedBy(): ?User
    {
        return $this->suggestedBy;
    }

    public function setSuggestedBy(?User $suggestedBy): static
    {
        $this->suggestedBy = $suggestedBy;
        return $this;
    }

    /** @return Collection<int, Vote> */
    public function getVotes(): Collection
    {
        return $this->votes;
    }

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function setProduct(?Product $product): static
    {
        $this->product = $product;
        return $this;
    }

    // ── Helpers ──

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function getStatusLabel(): string
    {
        return match($this->status) {
            self::STATUS_APPROVED => 'Approuvée',
            self::STATUS_REJECTED => 'Rejetée',
            default               => 'En attente',
        };
    }

    public function getStatusColor(): string
    {
        return match($this->status) {
            self::STATUS_APPROVED => '#91B893',
            self::STATUS_REJECTED => '#E54A5F',
            default               => '#F1947C',
        };
    }

    public function getQualityStars(): string
    {
        return str_repeat('★', $this->qualityRating) . str_repeat('☆', 5 - $this->qualityRating);
    }

    public function __toString(): string
    {
        return $this->name ?? '';
    }
}
