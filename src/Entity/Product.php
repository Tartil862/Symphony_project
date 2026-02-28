<?php

namespace App\Entity;

use App\Repository\ProductRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProductRepository::class)]
class Product
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $label = null;

    #[ORM\Column(nullable: true)]
    private ?float $price = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image = null;

    #[ORM\Column]
    private ?int $quantity = 0;

    #[ORM\ManyToOne(inversedBy: 'products')]
    #[ORM\JoinColumn(nullable: false)]

    private ?Category $category = null;

    #[ORM\ManyToOne(inversedBy: 'products')]
    private ?Supplier $supplier_id = null;

    #[ORM\Column]
    private bool $isAlternative = false;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $alternativeFor = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(string $label): static
    {
        $this->label = $label;

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

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function getSupplierId(): ?supplier
    {
        return $this->supplier_id;
    }

    public function setSupplierId(?supplier $supplier_id): static
    {
        $this->supplier_id = $supplier_id;

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

    public function getQuantity(): ?int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): static
    {
        $this->quantity = $quantity;

        return $this;
    }

    public function isAlternative(): bool
    {
        return $this->isAlternative;
    }

    public function setIsAlternative(bool $isAlternative): static
    {
        $this->isAlternative = $isAlternative;
        return $this;
    }

    public function getAlternativeFor(): ?string
    {
        return $this->alternativeFor;
    }

    public function setAlternativeFor(?string $alternativeFor): static
    {
        $this->alternativeFor = $alternativeFor;
        return $this;
    }

    /**
     * Returns the correct upload folder depending on whether this product
     * was created from a boycott alternative or uploaded normally.
     */
    public function getImageFolder(): string
    {
        return $this->isAlternative ? 'uploads/boycott' : 'uploads/products';
    }
}
