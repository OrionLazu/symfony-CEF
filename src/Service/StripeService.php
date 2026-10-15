<?php

namespace App\Service;

use App\Entity\Order;
use Stripe\Checkout\Session;
use Stripe\StripeClient;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Service de paiement Stripe.
 *
 * Il utilise Stripe Checkout : la boutique crée une session de paiement décrivant la commande,
 * redirige le client vers la page de paiement hébergée par Stripe, puis vérifie au retour
 * que la session a bien été réglée. Aucune donnée bancaire ne transite par l'application.
 *
 * En développement, il suffit d'utiliser une clé secrète de test (sk_test_...) : les paiements
 * sont simulés avec les cartes de test de Stripe (ex. 4242 4242 4242 4242).
 */
class StripeService
{
    public const CURRENCY = 'eur';

    private ?StripeClient $client;

    public function __construct(
        #[Autowire('%env(STRIPE_SECRET_KEY)%')]
        private readonly string $secretKey,
        ?StripeClient $client = null,
    ) {
        $this->client = $client;
    }

    /**
     * Indique si une clé secrète Stripe est renseignée.
     */
    public function isConfigured(): bool
    {
        return '' !== trim($this->secretKey);
    }

    /**
     * Indique si la clé utilisée est une clé du mode test (bac à sable).
     */
    public function isTestMode(): bool
    {
        return str_starts_with($this->secretKey, 'sk_test_') || str_starts_with($this->secretKey, 'rk_test_');
    }

    /**
     * Crée la session Stripe Checkout correspondant à une commande.
     *
     * @param string $successUrl URL de retour après paiement ; "{CHECKOUT_SESSION_ID}" y est remplacé par Stripe
     * @param string $cancelUrl  URL de retour si le client abandonne le paiement
     *
     * @throws \LogicException            si la commande est vide ou Stripe non configuré
     * @throws \Stripe\Exception\ApiErrorException en cas d'erreur renvoyée par Stripe
     */
    public function createCheckoutSession(Order $order, string $successUrl, string $cancelUrl): Session
    {
        if ($order->getItems()->isEmpty()) {
            throw new \LogicException('Impossible de payer une commande vide.');
        }

        $lineItems = [];
        foreach ($order->getItems() as $item) {
            $lineItems[] = [
                'quantity' => $item->getQuantity(),
                'price_data' => [
                    'currency' => self::CURRENCY,
                    'unit_amount' => $item->getUnitPriceInCents(),
                    'product_data' => [
                        'name' => sprintf('%s - taille %s', $item->getProductName(), $item->getSize()->value),
                    ],
                ],
            ];
        }

        return $this->getClient()->checkout->sessions->create([
            'mode' => 'payment',
            'line_items' => $lineItems,
            'customer_email' => $order->getCustomer()->getEmail(),
            'client_reference_id' => (string) $order->getId(),
            'metadata' => ['order_id' => (string) $order->getId()],
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
        ]);
    }

    /**
     * Relit une session Checkout auprès de Stripe.
     *
     * @throws \Stripe\Exception\ApiErrorException
     */
    public function retrieveCheckoutSession(string $sessionId): Session
    {
        return $this->getClient()->checkout->sessions->retrieve($sessionId);
    }

    /**
     * Vérifie auprès de Stripe que la session a été réglée.
     * L'information vient de Stripe et non de l'URL de retour, qui pourrait être falsifiée.
     */
    public function isCheckoutSessionPaid(string $sessionId): bool
    {
        return 'paid' === $this->retrieveCheckoutSession($sessionId)->payment_status;
    }

    private function getClient(): StripeClient
    {
        if (!$this->isConfigured()) {
            throw new \LogicException('Stripe n\'est pas configuré : renseignez STRIPE_SECRET_KEY dans le fichier .env.local.');
        }
        // Garde-fou : la boutique est une démonstration, une clé réelle (sk_live_) ne doit jamais encaisser de paiement.
        if (!$this->isTestMode()) {
            throw new \LogicException('Seule une clé Stripe de test (sk_test_...) est acceptée.');
        }

        return $this->client ??= new StripeClient($this->secretKey);
    }
}
