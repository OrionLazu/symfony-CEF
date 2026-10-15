<?php

namespace App\Service;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\User;
use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Orchestration d'un achat : panier -> commande -> paiement Stripe -> confirmation.
 */
class CheckoutService
{
    public function __construct(
        private readonly CartService $cart,
        private readonly StripeService $stripe,
        private readonly EntityManagerInterface $entityManager,
        private readonly OrderRepository $orderRepository,
    ) {
    }

    /**
     * Transforme le panier en commande « en attente de paiement ».
     *
     * @throws \DomainException si le panier est vide ou si un article n'est plus en stock
     */
    public function createOrderFromCart(User $customer): Order
    {
        $items = $this->cart->getItems();
        if ([] === $items) {
            throw new \DomainException('Votre panier est vide.');
        }

        // Une commande précédente restée « en attente » (paiement abandonné) est remplacée par la nouvelle.
        foreach ($this->orderRepository->findBy(['customer' => $customer, 'status' => Order::STATUS_PENDING]) as $previous) {
            $previous->markAsCanceled();
        }

        $order = new Order($customer);
        foreach ($items as $item) {
            // Le stock a pu baisser depuis l'ajout au panier : on revérifie avant de payer.
            if ($item->quantity > $item->product->getStock($item->size)) {
                throw new \DomainException(sprintf('%s en taille %s n\'est plus disponible dans la quantité demandée.', $item->product->getName(), $item->size->value));
            }
            $order->addItem(new OrderItem($item->product, $item->size, $item->quantity));
        }

        $this->entityManager->persist($order);
        $this->entityManager->flush();

        return $order;
    }

    /**
     * Crée la session de paiement Stripe et retourne l'URL vers laquelle rediriger le client.
     */
    public function startPayment(Order $order, string $successUrl, string $cancelUrl): string
    {
        $session = $this->stripe->createCheckoutSession($order, $successUrl, $cancelUrl);

        $order->setStripeSessionId($session->id);
        $this->entityManager->flush();

        return (string) $session->url;
    }

    /**
     * Valide la commande au retour de Stripe : contrôle du paiement, décrément du stock, articles retirés du panier.
     *
     * L'opération est idempotente : recharger la page de confirmation ne décompte pas le stock deux fois.
     *
     * @throws \DomainException si la commande est introuvable, n'appartient pas au client ou n'est pas payée
     */
    public function confirmPayment(string $stripeSessionId, User $customer): Order
    {
        $order = $this->orderRepository->findOneBy(['stripeSessionId' => $stripeSessionId]);
        if (null === $order || $order->getCustomer()->getId() !== $customer->getId()) {
            throw new \DomainException('Commande introuvable.');
        }

        if ($order->isPaid()) {
            return $order;
        }

        if (!$this->stripe->isCheckoutSessionPaid($stripeSessionId)) {
            throw new \DomainException('Le paiement n\'a pas été confirmé par Stripe.');
        }

        foreach ($order->getItems() as $item) {
            $product = $item->getProduct();
            if (null === $product) {
                continue; // produit supprimé depuis : la ligne de commande garde son nom et son prix
            }
            // Le paiement est acquis : on ne bloque pas la commande, le stock ne descend simplement pas sous zéro.
            $product->setStock($item->getSize(), max(0, $product->getStock($item->getSize()) - $item->getQuantity()));
        }
        $order->markAsPaid();
        $this->entityManager->flush();

        // Seuls les articles achetés quittent le panier (un article ajouté entre-temps y reste).
        foreach ($order->getItems() as $item) {
            if (null !== $item->getProduct()?->getId()) {
                $this->cart->remove($item->getProduct()->getId(), $item->getSize());
            }
        }

        return $order;
    }

    /**
     * Annule une commande qui ne pourra pas être payée (erreur lors de la création du paiement).
     */
    public function cancelOrder(Order $order): void
    {
        if ($order->isPending()) {
            $order->markAsCanceled();
            $this->entityManager->flush();
        }
    }

    /**
     * Annule une commande en attente quand le client revient de Stripe sans payer.
     */
    public function cancel(string $stripeSessionId, User $customer): void
    {
        $order = $this->orderRepository->findOneBy(['stripeSessionId' => $stripeSessionId]);
        if (null !== $order && $order->isPending() && $order->getCustomer()->getId() === $customer->getId()) {
            $order->markAsCanceled();
            $this->entityManager->flush();
        }
    }
}
