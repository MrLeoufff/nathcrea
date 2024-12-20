<?php

namespace App\Entity;

use App\Repository\OrderItemRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OrderItemRepository::class)]
class OrderItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $quantity = null;

    #[ORM\Column]
    private ?float $unitPrice = null;

    #[ORM\Column]
    private ?float $totalPrice = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $paypalFee = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $productName = null;

    #[ORM\ManyToOne(inversedBy: 'orderItems', cascade: ['persist', 'remove'])]
    private ?Product $product = null;

    #[ORM\ManyToOne(inversedBy: 'orderItems', cascade: ['persist'])]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private ?Order $orderRef = null;

    public function calculateTotalPrice(): void
    {
        if ($this->quantity !== null && $this->unitPrice !== null) {
            $this->totalPrice = $this->quantity * $this->unitPrice;
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getQuantity(): ?int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): self
    {
        $this->quantity = $quantity;
        $this->calculateTotalPrice();
        return $this;
    }

    public function getUnitPrice(): ?float
    {
        return $this->unitPrice;
    }

    public function setUnitPrice(float $unitPrice): self
    {
        $this->unitPrice = $unitPrice;
        $this->calculateTotalPrice();
        return $this;
    }

    public function getTotalPrice(): ?float
    {
        return $this->totalPrice;
    }

    public function setTotalPrice(float $totalPrice): self
    {
        $this->totalPrice = $totalPrice;
        return $this;
    }

    public function getPaypalFee(): ?float
    {
        return $this->paypalFee;
    }

    public function setPaypalFee(?float $paypalFee): self
    {
        $this->paypalFee = $paypalFee;
        return $this;
    }

    public function getProductName(): ?string
    {
        return $this->productName;
    }

    public function setProductName(string $productName): self
    {
        $this->productName = $productName;
        return $this;
    }

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function setProduct(?Product $product): self
    {
        $this->product = $product;
        return $this;
    }

    public function getOrderRef(): ?Order
    {
        return $this->orderRef;
    }

    public function setOrderRef(?Order $orderRef): self
    {
        $this->orderRef = $orderRef;
        return $this;
    }

    public function __toString(): string
    {
        return sprintf(
            'OrderItem: [id: %d, productName: %s, quantity: %d, unitPrice: %.2f]',
            $this->id,
            $this->productName ?? 'N/A',
            $this->quantity,
            $this->unitPrice
        );
    }
}
