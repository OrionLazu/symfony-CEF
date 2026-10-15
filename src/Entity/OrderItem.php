<?php

namespace App\Entity;

use App\Enum\Size;
use App\Repository\OrderItemRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Ligne de commande : un sweat-shirt dans une taille donnée.
 *
 * Le nom et le prix sont recopiés au moment de l'achat pour que l'historique reste juste
 * même si le produit est modifié ou supprimé ensuite depuis le back-office.
 */
#[ORM\Entity(repositoryClass: OrderItemRepository::class)]
class OrderItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Order $order = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Product $product;

    #[ORM\Column(length: 100)]
    private string $productName;

    #[ORM\Column(length: 2, enumType: Size::class)]
    private Size $size;

    #[ORM\Column]
    private int $quantity;

    #[ORM\Column]
    private int $unitPriceInCents;

    public function __construct(Product $product, Size $size, int $quantity)
    {
        if ($quantity < 1) {
            throw new \InvalidArgumentException('La quantité doit être au moins égale à 1.');
        }

        $this->product = $product;
        $this->productName = $product->getName();
        $this->size = $size;
        $this->quantity = $quantity;
        $this->unitPriceInCents = $product->getPriceInCents();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrder(): ?Order
    {
        return $this->order;
    }

    public function setOrder(Order $order): static
    {
        $this->order = $order;

        return $this;
    }

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function getProductName(): string
    {
        return $this->productName;
    }

    public function getSize(): Size
    {
        return $this->size;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function getUnitPriceInCents(): int
    {
        return $this->unitPriceInCents;
    }

    public function getSubtotalInCents(): int
    {
        return $this->unitPriceInCents * $this->quantity;
    }
}
