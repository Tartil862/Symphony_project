<?php

namespace App\Entity;

use App\Repository\NotificationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NotificationRepository::class)]
class Notification
{
    public const TYPE_ORDER = 'order';
    public const TYPE_STOCK = 'stock';
    public const TYPE_INFO = 'info';
    public const TYPE_WARNING = 'warning';
    public const TYPE_SUCCESS = 'success';
    public const TYPE_WELCOME = 'welcome';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column(length: 255)]
    private ?string $message = null;

    #[ORM\Column(length: 30)]
    private ?string $type = self::TYPE_INFO;

    #[ORM\Column]
    private bool $isRead = false;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $link = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $icon = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(string $message): static
    {
        $this->message = $message;
        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function isRead(): bool
    {
        return $this->isRead;
    }

    public function setIsRead(bool $isRead): static
    {
        $this->isRead = $isRead;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getLink(): ?string
    {
        return $this->link;
    }

    public function setLink(?string $link): static
    {
        $this->link = $link;
        return $this;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function setIcon(?string $icon): static
    {
        $this->icon = $icon;
        return $this;
    }

    /**
     * Get an appropriate icon for the notification type if none set.
     */
    public function getDisplayIcon(): string
    {
        if ($this->icon) {
            return $this->icon;
        }

        return match ($this->type) {
            self::TYPE_ORDER => 'bi-receipt',
            self::TYPE_STOCK => 'bi-exclamation-triangle',
            self::TYPE_WARNING => 'bi-exclamation-circle',
            self::TYPE_SUCCESS => 'bi-check-circle',
            self::TYPE_WELCOME => 'bi-stars',
            default => 'bi-bell',
        };
    }

    /**
     * Get badge color class for the notification type.
     */
    public function getTypeColor(): string
    {
        return match ($this->type) {
            self::TYPE_ORDER => '#2A3638',
            self::TYPE_STOCK => '#F1947C',
            self::TYPE_WARNING => '#E54A5F',
            self::TYPE_SUCCESS => '#91B893',
            self::TYPE_WELCOME => '#E5C6A4',
            default => '#4a6264',
        };
    }

    /**
     * Get a human-friendly time ago string.
     */
    public function getTimeAgo(): string
    {
        $now = new \DateTime();
        $diff = $now->diff($this->createdAt);

        if ($diff->y > 0) return $diff->y . ' an' . ($diff->y > 1 ? 's' : '');
        if ($diff->m > 0) return $diff->m . ' mois';
        if ($diff->d > 0) return $diff->d . ' j';
        if ($diff->h > 0) return $diff->h . ' h';
        if ($diff->i > 0) return $diff->i . ' min';
        return 'À l\'instant';
    }
}
