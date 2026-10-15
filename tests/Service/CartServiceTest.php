<?php

namespace App\Tests\Service;

use App\Entity\Product;
use App\Enum\Size;
use App\Repository\ProductRepository;
use App\Service\CartService;
use App\Tests\Support\EntityFactory;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Tests unitaires du panier : ajout, regroupement, retrait, calcul du total et contrôles de stock.
 */
#[TestDox('Panier')]
class CartServiceTest extends TestCase
{
    private CartService $cart;
    private Session $session;

    /** @var array<int, Product> */
    private array $catalog = [];

    protected function setUp(): void
    {
        $this->catalog = [
            1 => EntityFactory::product(1, 'Blackbelt', '29.90'),
            4 => EntityFactory::product(4, 'Pokeball', '45.00'),
            9 => EntityFactory::product(9, 'BornInUsa', '59.90', 3),
        ];

        // Le dépôt est simulé : il renvoie les produits du catalogue dont l'id est demandé.
        $repository = $this->createStub(ProductRepository::class);
        $repository->method('findBy')->willReturnCallback(
            fn (array $criteria) => array_values(array_intersect_key($this->catalog, array_flip($criteria['id']))),
        );

        $this->session = new Session(new MockArraySessionStorage());
        $request = new Request();
        $request->setSession($this->session);
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $this->cart = new CartService($requestStack, $repository);
    }

    #[TestDox('Un nouveau panier est vide')]
    public function testNewCartIsEmpty(): void
    {
        self::assertTrue($this->cart->isEmpty());
        self::assertSame([], $this->cart->getItems());
        self::assertSame(0, $this->cart->getTotalInCents());
    }

    #[TestDox('Ajout d\'un sweat-shirt au panier')]
    public function testAddProductToCart(): void
    {
        $this->cart->add($this->catalog[1], Size::M);

        $items = $this->cart->getItems();
        self::assertCount(1, $items);
        self::assertSame('Blackbelt', $items[0]->product->getName());
        self::assertSame(Size::M, $items[0]->size);
        self::assertSame(1, $items[0]->quantity);
        self::assertSame(2990, $this->cart->getTotalInCents());
    }

    #[TestDox('Le même sweat-shirt dans la même taille est regroupé sur une ligne')]
    public function testSameProductAndSizeIsGroupedOnOneLine(): void
    {
        $this->cart->add($this->catalog[1], Size::M);
        $this->cart->add($this->catalog[1], Size::M);

        $items = $this->cart->getItems();
        self::assertCount(1, $items);
        self::assertSame(2, $items[0]->quantity);
        self::assertSame(2, $this->cart->count());
    }

    #[TestDox('Le même sweat-shirt dans deux tailles donne deux lignes')]
    public function testSameProductInTwoSizesMakesTwoLines(): void
    {
        $this->cart->add($this->catalog[1], Size::S);
        $this->cart->add($this->catalog[1], Size::XL);

        self::assertCount(2, $this->cart->getItems());
    }

    #[TestDox('Le total est calculé sur toutes les lignes')]
    public function testTotalIsComputedFromAllLines(): void
    {
        $this->cart->add($this->catalog[1], Size::M);      // 29,90
        $this->cart->add($this->catalog[4], Size::L, 2);   // 2 x 45,00
        $this->cart->add($this->catalog[9], Size::XS);     // 59,90

        // 29,90 + 90,00 + 59,90 = 179,80 € : calcul en centimes, sans erreur d'arrondi.
        self::assertSame(17980, $this->cart->getTotalInCents());
    }

    #[TestDox('Retirer un article met à jour le total')]
    public function testRemoveItemUpdatesTotal(): void
    {
        $this->cart->add($this->catalog[1], Size::M);
        $this->cart->add($this->catalog[4], Size::L);
        self::assertSame(7490, $this->cart->getTotalInCents());

        $this->cart->remove(1, Size::M);

        $items = $this->cart->getItems();
        self::assertCount(1, $items);
        self::assertSame('Pokeball', $items[0]->product->getName());
        self::assertSame(4500, $this->cart->getTotalInCents());
    }

    #[TestDox('Le retrait ne concerne que la taille choisie')]
    public function testRemoveOnlyTargetsTheGivenSize(): void
    {
        $this->cart->add($this->catalog[1], Size::S);
        $this->cart->add($this->catalog[1], Size::M);

        $this->cart->remove(1, Size::S);

        $items = $this->cart->getItems();
        self::assertCount(1, $items);
        self::assertSame(Size::M, $items[0]->size);
    }

    #[TestDox('Impossible d\'ajouter plus que le stock disponible')]
    public function testCannotAddMoreThanAvailableStock(): void
    {
        $this->cart->add($this->catalog[1], Size::M, 2); // stock M = 2

        $this->expectException(\DomainException::class);
        $this->cart->add($this->catalog[1], Size::M);
    }

    #[TestDox('Impossible d\'ajouter une taille en rupture')]
    public function testCannotAddOutOfStockSize(): void
    {
        $this->catalog[4]->setStock(Size::XL, 0);

        $this->expectException(\DomainException::class);
        $this->cart->add($this->catalog[4], Size::XL);
    }

    #[TestDox('La quantité doit être positive')]
    public function testQuantityMustBePositive(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->cart->add($this->catalog[1], Size::M, 0);
    }

    #[TestDox('Le panier est conservé en session')]
    public function testCartIsKeptInSession(): void
    {
        $this->cart->add($this->catalog[9], Size::L);

        // Le panier est stocké en session : il survit d'une page à l'autre.
        self::assertSame(
            ['9-L' => ['product' => 9, 'size' => 'L', 'quantity' => 1]],
            $this->session->get('cart'),
        );
    }

    #[TestDox('Un sweat-shirt supprimé est ignoré')]
    public function testDeletedProductIsIgnored(): void
    {
        $this->cart->add($this->catalog[1], Size::M);
        $this->cart->add($this->catalog[4], Size::M);

        unset($this->catalog[4]); // supprimé depuis le back-office

        self::assertCount(1, $this->cart->getItems());
        self::assertSame(2990, $this->cart->getTotalInCents());
    }

    #[TestDox('Vider le panier')]
    public function testClearEmptiesTheCart(): void
    {
        $this->cart->add($this->catalog[1], Size::M);
        $this->cart->clear();

        self::assertTrue($this->cart->isEmpty());
        self::assertSame(0, $this->cart->getTotalInCents());
    }
}
