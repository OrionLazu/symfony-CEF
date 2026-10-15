<?php

namespace App\Tests\Service;

use App\Entity\Order;
use App\Entity\Product;
use App\Enum\Size;
use App\Repository\OrderRepository;
use App\Repository\ProductRepository;
use App\Service\CartService;
use App\Service\CheckoutService;
use App\Service\StripeService;
use App\Tests\Support\EntityFactory;
use App\Tests\Support\FakeStripeHttpClient;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Stripe\ApiRequestor;
use Stripe\HttpClient\CurlClient;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Test d'un achat complet : panier -> commande -> paiement Stripe (simulé) -> confirmation.
 */
#[TestDox('Achat test')]
class PurchaseTest extends TestCase
{
    private FakeStripeHttpClient $http;
    private CartService $cart;
    private CheckoutService $checkout;
    private Product $blackbelt;
    private Product $pokeball;

    /** Commandes « enregistrées » par le faux EntityManager. */
    private ?Order $savedOrder = null;

    protected function setUp(): void
    {
        $this->http = new FakeStripeHttpClient();
        ApiRequestor::setHttpClient($this->http);

        $this->blackbelt = EntityFactory::product(1, 'Blackbelt', '29.90', 3);
        $this->pokeball = EntityFactory::product(4, 'Pokeball', '45.00', 3);
        $catalog = [1 => $this->blackbelt, 4 => $this->pokeball];

        $productRepository = $this->createStub(ProductRepository::class);
        $productRepository->method('findBy')->willReturnCallback(
            static fn (array $criteria) => array_values(array_intersect_key($catalog, array_flip($criteria['id']))),
        );

        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));
        $requestStack = new RequestStack();
        $requestStack->push($request);
        $this->cart = new CartService($requestStack, $productRepository);

        // Persistance simulée : l'id 100 est attribué à la commande comme le ferait MySQL.
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('persist')->willReturnCallback(function (object $entity): void {
            if ($entity instanceof Order) {
                $this->savedOrder = EntityFactory::withId($entity, 100);
            }
        });

        $orderRepository = $this->createStub(OrderRepository::class);
        $orderRepository->method('findOneBy')->willReturnCallback(
            fn (array $criteria) => $this->savedOrder?->getStripeSessionId() === $criteria['stripeSessionId'] ? $this->savedOrder : null,
        );

        $this->checkout = new CheckoutService($this->cart, new StripeService('sk_test_fake'), $entityManager, $orderRepository);
    }

    protected function tearDown(): void
    {
        ApiRequestor::setHttpClient(CurlClient::instance());
    }

    #[TestDox('Achat complet : panier, commande, paiement Stripe, confirmation')]
    public function testCompletePurchase(): void
    {
        $customer = EntityFactory::customer();

        // 1. Le client remplit son panier.
        $this->cart->add($this->blackbelt, Size::M);
        $this->cart->add($this->pokeball, Size::L, 2);
        self::assertSame(11990, $this->cart->getTotalInCents());

        // 2. « Finaliser ma commande » : création de la commande en attente.
        $order = $this->checkout->createOrderFromCart($customer);
        self::assertSame(Order::STATUS_PENDING, $order->getStatus());
        self::assertSame(11990, $order->getTotalInCents());
        self::assertCount(2, $order->getItems());
        self::assertSame('8 rue du bac 54100 Nancy', $order->getDeliveryAddress());

        // 3. Redirection vers la page de paiement Stripe (mode test).
        $this->http->queueResponse([
            'id' => 'cs_test_purchase',
            'object' => 'checkout.session',
            'url' => 'https://checkout.stripe.com/c/pay/cs_test_purchase',
            'payment_status' => 'unpaid',
        ]);
        $paymentUrl = $this->checkout->startPayment($order, 'https://shop/success', 'https://shop/cancel');
        self::assertSame('https://checkout.stripe.com/c/pay/cs_test_purchase', $paymentUrl);
        self::assertSame('cs_test_purchase', $order->getStripeSessionId());

        // 4. Retour de Stripe : le paiement est confirmé par l'API.
        $this->http->queueResponse(['id' => 'cs_test_purchase', 'object' => 'checkout.session', 'payment_status' => 'paid']);
        $confirmed = $this->checkout->confirmPayment('cs_test_purchase', $customer);

        self::assertTrue($confirmed->isPaid());
        self::assertNotNull($confirmed->getPaidAt());
        // Le stock est décrémenté de la quantité achetée...
        self::assertSame(2, $this->blackbelt->getStock(Size::M));
        self::assertSame(1, $this->pokeball->getStock(Size::L));
        self::assertSame(3, $this->pokeball->getStock(Size::M));
        // ... et le panier est vidé.
        self::assertTrue($this->cart->isEmpty());
    }

    #[TestDox('Recharger la confirmation ne décompte pas le stock deux fois')]
    public function testConfirmationIsIdempotent(): void
    {
        $customer = EntityFactory::customer();
        $this->cart->add($this->blackbelt, Size::S);
        $order = $this->checkout->createOrderFromCart($customer);
        $this->http->queueResponse(['id' => 'cs_test_twice', 'object' => 'checkout.session', 'url' => 'https://checkout.stripe.com/x']);
        $this->checkout->startPayment($order, 'https://s', 'https://c');
        $this->http->queueResponse(['id' => 'cs_test_twice', 'object' => 'checkout.session', 'payment_status' => 'paid']);

        $this->checkout->confirmPayment('cs_test_twice', $customer);
        // Rechargement de la page de confirmation : pas de second appel à Stripe ni de double décompte.
        $this->checkout->confirmPayment('cs_test_twice', $customer);

        self::assertSame(2, $this->blackbelt->getStock(Size::S));
        self::assertCount(2, $this->http->requests);
    }

    #[TestDox('Un paiement non abouti ne valide pas la commande')]
    public function testUnpaidSessionIsNotConfirmed(): void
    {
        $customer = EntityFactory::customer();
        $this->cart->add($this->blackbelt, Size::M);
        $order = $this->checkout->createOrderFromCart($customer);
        $this->http->queueResponse(['id' => 'cs_test_unpaid', 'object' => 'checkout.session', 'url' => 'https://checkout.stripe.com/x']);
        $this->checkout->startPayment($order, 'https://s', 'https://c');
        $this->http->queueResponse(['id' => 'cs_test_unpaid', 'object' => 'checkout.session', 'payment_status' => 'unpaid']);

        try {
            $this->checkout->confirmPayment('cs_test_unpaid', $customer);
            self::fail('Une session non payée ne doit pas valider la commande.');
        } catch (\DomainException) {
        }

        self::assertTrue($order->isPending());
        self::assertSame(3, $this->blackbelt->getStock(Size::M));
        self::assertFalse($this->cart->isEmpty());
    }

    #[TestDox('Un autre client ne peut pas valider la commande')]
    public function testAnotherCustomerCannotConfirmTheOrder(): void
    {
        $this->cart->add($this->blackbelt, Size::M);
        $order = $this->checkout->createOrderFromCart(EntityFactory::customer(1));
        $this->http->queueResponse(['id' => 'cs_test_owner', 'object' => 'checkout.session', 'url' => 'https://checkout.stripe.com/x']);
        $this->checkout->startPayment($order, 'https://s', 'https://c');

        $this->expectException(\DomainException::class);
        $this->checkout->confirmPayment('cs_test_owner', EntityFactory::customer(2));
    }

    #[TestDox('Un panier vide ne peut pas être commandé')]
    public function testEmptyCartCannotBeOrdered(): void
    {
        $this->expectException(\DomainException::class);
        $this->checkout->createOrderFromCart(EntityFactory::customer());
    }

    #[TestDox('Le stock est revérifié avant le paiement')]
    public function testStockIsCheckedAgainBeforePayment(): void
    {
        $this->cart->add($this->blackbelt, Size::XL, 2);
        // Entre-temps, l'administrateur baisse le stock.
        $this->blackbelt->setStock(Size::XL, 1);

        $this->expectException(\DomainException::class);
        $this->checkout->createOrderFromCart(EntityFactory::customer());
    }
}
