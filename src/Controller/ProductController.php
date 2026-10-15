<?php

namespace App\Controller;

use App\Entity\Product;
use App\Enum\Size;
use App\Form\AddToCartType;
use App\Model\PriceRange;
use App\Repository\ProductRepository;
use App\Service\CartService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Boutique : liste des sweat-shirts et fiche produit (réservées aux utilisateurs connectés).
 */
class ProductController extends AbstractController
{
    /**
     * Liste de tous les sweat-shirts, filtrable par fourchette de prix (?range=10-29).
     */
    #[Route('/products', name: 'app_products', methods: ['GET'])]
    public function index(Request $request, ProductRepository $productRepository): Response
    {
        $range = PriceRange::fromKey($request->query->getString('range'));

        return $this->render('product/index.html.twig', [
            'products' => $productRepository->findByPriceRange($range),
            'ranges' => PriceRange::all(),
            'currentRange' => $range,
        ]);
    }

    /**
     * Fiche d'un sweat-shirt avec choix de la taille et ajout au panier.
     */
    #[Route('/product/{id<\d+>}', name: 'app_product_show', methods: ['GET', 'POST'])]
    public function show(Product $product, Request $request, CartService $cart): Response
    {
        // Présélectionne M si disponible (comme sur le wireframe), sinon la première taille en stock.
        $inStock = array_values(array_filter(Size::cases(), static fn (Size $size) => $product->getStock($size) > 0));
        $defaultSize = in_array(Size::M, $inStock, true) ? Size::M : ($inStock[0] ?? null);

        $form = $this->createForm(AddToCartType::class, ['size' => $defaultSize], ['product' => $product]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $size = $form->get('size')->getData();
            try {
                $cart->add($product, $size);
                $this->addFlash('success', sprintf('%s (taille %s) a été ajouté à votre panier.', $product->getName(), $size->value));

                return $this->redirectToRoute('app_cart');
            } catch (\DomainException $e) {
                $this->addFlash('error', $e->getMessage());

                return $this->redirectToRoute('app_product_show', ['id' => $product->getId()]);
            }
        }

        return $this->render('product/show.html.twig', [
            'product' => $product,
            'form' => $form,
        ]);
    }
}
