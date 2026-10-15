<?php

namespace App\Model;

use App\Entity\Product;
use App\Enum\Size;

/**
 * Ligne du panier telle qu'affichée à l'utilisateur.
 */
final class CartItem
{
    public function __construct(
        public readonly Product $product,
        public readonly Size $size,
        public readonly int $quantity,
    ) {
    }

    /**
     * Clé unique d'une ligne : un même sweat-shirt dans deux tailles fait deux lignes.
     */
    public static function key(int $productId, Size $size): string
    {
        return $productId.'-'.$size->value;
    }

    public function getKey(): string
    {
        return self::key((int) $this->product->getId(), $this->size);
    }

    public function getSubtotalInCents(): int
    {
        return $this->product->getPriceInCents() * $this->quantity;
    }
}
