<?php

namespace App\Service;

use App\Entity\Product;
use App\Enum\Size;
use App\Model\CartItem;
use App\Repository\ProductRepository;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Panier de l'utilisateur, conservé en session pendant toute l'expérience d'achat.
 *
 * La session ne contient que des identifiants, des tailles et des quantités :
 * les produits sont relus en base à chaque affichage, ce qui garantit des prix à jour
 * et ignore proprement un sweat-shirt supprimé entre-temps par l'administrateur.
 */
class CartService
{
    private const SESSION_KEY = 'cart';

    /** Garde-fou : quantité maximale d'une même ligne. */
    public const MAX_QUANTITY_PER_LINE = 10;

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly ProductRepository $productRepository,
    ) {
    }

    /**
     * Ajoute un sweat-shirt dans la taille choisie.
     *
     * @throws \InvalidArgumentException si la quantité est invalide
     * @throws \DomainException          si le stock ne permet pas l'ajout
     */
    public function add(Product $product, Size $size, int $quantity = 1): void
    {
        if (null === $product->getId()) {
            throw new \InvalidArgumentException('Impossible d\'ajouter un produit non enregistré.');
        }
        if ($quantity < 1) {
            throw new \InvalidArgumentException('La quantité doit être au moins égale à 1.');
        }

        $cart = $this->getRawCart();
        $key = CartItem::key($product->getId(), $size);
        $newQuantity = ($cart[$key]['quantity'] ?? 0) + $quantity;

        if ($newQuantity > $product->getStock($size)) {
            throw new \DomainException(sprintf('Il ne reste que %d exemplaire(s) de %s en taille %s.', $product->getStock($size), $product->getName(), $size->value));
        }
        if ($newQuantity > self::MAX_QUANTITY_PER_LINE) {
            throw new \DomainException(sprintf('Vous ne pouvez pas commander plus de %d exemplaires d\'un même article.', self::MAX_QUANTITY_PER_LINE));
        }

        $cart[$key] = ['product' => $product->getId(), 'size' => $size->value, 'quantity' => $newQuantity];
        $this->saveRawCart($cart);
    }

    /**
     * Retire une ligne du panier (toutes les quantités de ce produit dans cette taille).
     */
    public function remove(int $productId, Size $size): void
    {
        $cart = $this->getRawCart();
        unset($cart[CartItem::key($productId, $size)]);
        $this->saveRawCart($cart);
    }

    /**
     * Lignes du panier avec leurs produits chargés depuis la base.
     *
     * @return list<CartItem>
     */
    public function getItems(): array
    {
        $cart = $this->getRawCart();
        if ([] === $cart) {
            return [];
        }

        $ids = array_values(array_unique(array_column($cart, 'product')));
        $products = [];
        foreach ($this->productRepository->findBy(['id' => $ids]) as $product) {
            $products[$product->getId()] = $product;
        }

        $items = [];
        $cleaned = [];
        foreach ($cart as $key => $line) {
            $product = $products[$line['product']] ?? null;
            $size = Size::tryFrom((string) $line['size']);
            // Données incohérentes (produit supprimé, taille inconnue) : la ligne est écartée.
            if (null === $product || null === $size || $line['quantity'] < 1) {
                continue;
            }
            $items[] = new CartItem($product, $size, (int) $line['quantity']);
            $cleaned[$key] = $line;
        }

        if (count($cleaned) !== count($cart)) {
            $this->saveRawCart($cleaned);
        }

        return $items;
    }

    /**
     * Montant total du panier en centimes.
     *
     * @param list<CartItem>|null $items lignes déjà chargées, pour éviter de relire la base
     */
    public function getTotalInCents(?array $items = null): int
    {
        return array_sum(array_map(static fn (CartItem $item) => $item->getSubtotalInCents(), $items ?? $this->getItems()));
    }

    /**
     * Nombre d'articles (somme des quantités).
     */
    public function count(): int
    {
        return array_sum(array_column($this->getRawCart(), 'quantity'));
    }

    public function isEmpty(): bool
    {
        return [] === $this->getRawCart();
    }

    /**
     * Vide entièrement le panier (après un paiement confirmé).
     */
    public function clear(): void
    {
        $this->getSession()->remove(self::SESSION_KEY);
    }

    /**
     * @return array<string, array{product: int, size: string, quantity: int}>
     */
    private function getRawCart(): array
    {
        $cart = $this->getSession()->get(self::SESSION_KEY, []);

        return is_array($cart) ? $cart : [];
    }

    /**
     * @param array<string, array{product: int, size: string, quantity: int}> $cart
     */
    private function saveRawCart(array $cart): void
    {
        $this->getSession()->set(self::SESSION_KEY, $cart);
    }

    private function getSession(): SessionInterface
    {
        return $this->requestStack->getSession();
    }
}
