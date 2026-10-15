<?php

namespace App\Controller;

use App\Enum\Size;
use App\Service\CartService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Page panier : liste des articles, total, retrait d'un article.
 */
class CartController extends AbstractController
{
    /**
     * Affiche le contenu du panier et son montant total.
     */
    #[Route('/cart', name: 'app_cart', methods: ['GET'])]
    public function index(CartService $cart): Response
    {
        return $this->render('cart/index.html.twig', [
            'items' => $cart->getItems(),
            'totalInCents' => $cart->getTotalInCents(),
        ]);
    }

    /**
     * Retire une ligne du panier (un sweat-shirt dans une taille) ; le total est recalculé à l'affichage.
     */
    #[Route('/cart/remove/{id<\d+>}/{size}', name: 'app_cart_remove', methods: ['POST'])]
    public function remove(int $id, string $size, Request $request, CartService $cart): Response
    {
        $sizeEnum = Size::tryFrom($size);
        if (null === $sizeEnum || !$this->isCsrfTokenValid('cart_remove_'.$id.'_'.$size, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Action impossible, veuillez réessayer.');

            return $this->redirectToRoute('app_cart');
        }

        $cart->remove($id, $sizeEnum);
        $this->addFlash('success', 'L\'article a été retiré du panier.');

        return $this->redirectToRoute('app_cart');
    }
}
