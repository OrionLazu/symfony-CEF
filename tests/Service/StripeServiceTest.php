<?php

namespace App\Tests\Service;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Enum\Size;
use App\Service\StripeService;
use App\Tests\Support\EntityFactory;
use App\Tests\Support\FakeStripeHttpClient;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Stripe\ApiRequestor;
use Stripe\HttpClient\CurlClient;

/**
 * Tests du service Stripe : le vrai SDK est utilisé, seules les réponses HTTP sont simulées.
 */
#[TestDox('Service Stripe')]
class StripeServiceTest extends TestCase
{
    private FakeStripeHttpClient $http;

    protected function setUp(): void
    {
        $this->http = new FakeStripeHttpClient();
        ApiRequestor::setHttpClient($this->http);
    }

    protected function tearDown(): void
    {
        ApiRequestor::setHttpClient(CurlClient::instance());
    }

    #[TestDox('Détection de la clé et du mode test')]
    public function testConfigurationDetection(): void
    {
        self::assertFalse((new StripeService(''))->isConfigured());
        self::assertTrue((new StripeService('sk_test_123'))->isConfigured());
        self::assertTrue((new StripeService('sk_test_123'))->isTestMode());
        self::assertFalse((new StripeService('sk_live_123'))->isTestMode());
    }

    #[TestDox('La session de paiement décrit la commande (montants en centimes, EUR)')]
    public function testCheckoutSessionDescribesTheOrder(): void
    {
        $order = $this->makeOrder();
        $this->http->queueResponse([
            'id' => 'cs_test_abc',
            'object' => 'checkout.session',
            'url' => 'https://checkout.stripe.com/c/pay/cs_test_abc',
            'payment_status' => 'unpaid',
        ]);

        $session = (new StripeService('sk_test_fake'))->createCheckoutSession($order, 'https://shop/success', 'https://shop/cancel');

        self::assertSame('cs_test_abc', $session->id);
        self::assertSame('https://checkout.stripe.com/c/pay/cs_test_abc', $session->url);

        self::assertCount(1, $this->http->requests);
        $request = $this->http->requests[0];
        self::assertSame('POST', $request['method']);
        self::assertStringEndsWith('/v1/checkout/sessions', $request['url']);

        $params = $request['params'];
        self::assertSame('payment', $params['mode']);
        self::assertSame('johndoe@stubborn.test', $params['customer_email']);
        self::assertSame('https://shop/success', $params['success_url']);
        self::assertCount(2, $params['line_items']);

        // Montants envoyés en centimes, en euros.
        self::assertSame('eur', $params['line_items'][0]['price_data']['currency']);
        self::assertSame(2990, $params['line_items'][0]['price_data']['unit_amount']);
        self::assertSame(1, $params['line_items'][0]['quantity']);
        self::assertSame('Blackbelt - taille M', $params['line_items'][0]['price_data']['product_data']['name']);
        self::assertSame(4500, $params['line_items'][1]['price_data']['unit_amount']);
        self::assertSame(2, $params['line_items'][1]['quantity']);
    }

    #[TestDox('Une session payée est reconnue')]
    public function testPaidSessionIsDetected(): void
    {
        $this->http->queueResponse(['id' => 'cs_test_abc', 'object' => 'checkout.session', 'payment_status' => 'paid']);

        self::assertTrue((new StripeService('sk_test_fake'))->isCheckoutSessionPaid('cs_test_abc'));
        self::assertSame('GET', $this->http->requests[0]['method']);
        self::assertStringEndsWith('/v1/checkout/sessions/cs_test_abc', $this->http->requests[0]['url']);
    }

    #[TestDox('Une session non payée est refusée')]
    public function testUnpaidSessionIsRejected(): void
    {
        $this->http->queueResponse(['id' => 'cs_test_abc', 'object' => 'checkout.session', 'payment_status' => 'unpaid']);

        self::assertFalse((new StripeService('sk_test_fake'))->isCheckoutSessionPaid('cs_test_abc'));
    }

    #[TestDox('Une commande vide ne peut pas être payée')]
    public function testEmptyOrderCannotBePaid(): void
    {
        $this->expectException(\LogicException::class);

        (new StripeService('sk_test_fake'))->createCheckoutSession(new Order(EntityFactory::customer()), 'https://s', 'https://c');
    }

    #[TestDox('Une clé manquante donne une erreur explicite')]
    public function testMissingKeyGivesAClearError(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('STRIPE_SECRET_KEY');

        (new StripeService(''))->isCheckoutSessionPaid('cs_test_abc');
    }

    private function makeOrder(): Order
    {
        $order = EntityFactory::withId(new Order(EntityFactory::customer()), 42);
        $order->addItem(new OrderItem(EntityFactory::product(1, 'Blackbelt', '29.90'), Size::M, 1));
        $order->addItem(new OrderItem(EntityFactory::product(4, 'Pokeball', '45.00'), Size::L, 2));

        return $order;
    }
}
