<?php

namespace App\Controller;

use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Page d'accueil de la boutique.
 */
class HomeController extends AbstractController
{
    /**
     * Page d'accueil : présentation de Stubborn et sweat-shirts mis en avant.
     * Les boutons « Voir » ne sont affichés qu'aux utilisateurs connectés (voir le template).
     */
    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function index(ProductRepository $productRepository): Response
    {
        return $this->render('home/index.html.twig', [
            'featuredProducts' => $productRepository->findFeatured(3),
        ]);
    }
}
