<?php

namespace App\Entity;

use App\Repository\VoteRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: VoteRepository::class)]
#[ORM\UniqueConstraint(name: 'unique_user_boycott_vote', columns: ['user_id', 'boycott_product_id'])]
#[ORM\UniqueConstraint(name: 'unique_user_alternative_vote', columns: ['user_id', 'alternative_id'])]
class Vote
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private int $value = 1; // +1 (upvote) or -1 (downvote)

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\ManyToOne(inversedBy: 'votes')]
    private ?BoycottProduct $boycottProduct = null;

    #[ORM\ManyToOne(inversedBy: 'votes')]
    private ?Alternative $alternative = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getValue(): int
    {
        return $this->value;
    }

    public function setValue(int $value): static
    {
        $this->value = $value >= 0 ? 1 : -1;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
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

    public function getAlternative(): ?Alternative
    {
        return $this->alternative;
    }

    public function setAlternative(?Alternative $alternative): static
    {
        $this->alternative = $alternative;
        return $this;
    }

    public function isUpvote(): bool
    {
        return $this->value === 1;
    }

    public function isDownvote(): bool
    {
        return $this->value === -1;
    }
}
