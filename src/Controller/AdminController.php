<?php

namespace App\Controller;

use App\Entity\Product;
use App\Form\ProductType;
use App\Repository\ProductRepository;
use App\Service\ProductImageUploader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Back-office : ajout, modification et suppression des sweat-shirts.
 *
 * L'accès est doublement protégé : règle access_control sur ^/admin et attribut IsGranted.
 */
#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
class AdminController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ProductImageUploader $imageUploader,
    ) {
    }

    /**
     * Une seule page affiche le formulaire d'ajout et un formulaire par sweat-shirt.
     * Chaque formulaire porte un nom distinct (product_new, product_12…) : seul celui envoyé est traité.
     */
    #[Route('', name: 'app_admin', methods: ['GET', 'POST'])]
    public function index(Request $request, ProductRepository $productRepository): Response
    {
        $newProduct = new Product();
        $newForm = $this->createNamedProductForm('product_new', $newProduct, true);
        $newForm->handleRequest($request);

        if ($newForm->isSubmitted() && $newForm->isValid()) {
            $this->handleImage($newForm, $newProduct);
            $this->entityManager->persist($newProduct);
            $this->entityManager->flush();
            $this->addFlash('success', sprintf('Le sweat-shirt %s a été ajouté.', $newProduct->getName()));

            return $this->redirectToRoute('app_admin');
        }

        $editForms = [];
        $hasError = $newForm->isSubmitted();
        foreach ($productRepository->findBy([], ['id' => 'ASC']) as $product) {
            $form = $this->createNamedProductForm('product_'.$product->getId(), $product, false);
            $form->handleRequest($request);

            if ($form->isSubmitted()) {
                if ($form->isValid()) {
                    $this->handleImage($form, $product);
                    $this->entityManager->flush();
                    $this->addFlash('success', sprintf('Le sweat-shirt %s a été modifié.', $product->getName()));

                    return $this->redirectToRoute('app_admin');
                }
                $hasError = true;
            }

            $editForms[] = ['product' => $product, 'form' => $form->createView()];
        }

        return $this->render('admin/index.html.twig', [
            'newForm' => $newForm->createView(),
            'editForms' => $editForms,
        ], new Response(null, $hasError ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    /**
     * Supprime un sweat-shirt (formulaire POST protégé par un jeton CSRF) et son image.
     */
    #[Route('/product/{id<\d+>}/delete', name: 'app_admin_product_delete', methods: ['POST'])]
    public function delete(Product $product, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('delete_product_'.$product->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide, la suppression a été annulée.');

            return $this->redirectToRoute('app_admin');
        }

        $name = $product->getName();
        $image = $product->getImage();
        $this->entityManager->remove($product);
        $this->entityManager->flush();
        $this->imageUploader->remove($image);

        $this->addFlash('success', sprintf('Le sweat-shirt %s a été supprimé.', $name));

        return $this->redirectToRoute('app_admin');
    }

    private function createNamedProductForm(string $name, Product $product, bool $imageRequired): FormInterface
    {
        return $this->container->get('form.factory')->createNamed($name, ProductType::class, $product, [
            'action' => $this->generateUrl('app_admin'),
            'image_required' => $imageRequired,
        ]);
    }

    /**
     * Enregistre la nouvelle image éventuelle et supprime l'ancienne.
     */
    private function handleImage(FormInterface $form, Product $product): void
    {
        $file = $form->get('imageFile')->getData();
        if (!$file instanceof UploadedFile) {
            return;
        }

        $previous = $product->getImage();
        $product->setImage($this->imageUploader->upload($file));
        $this->imageUploader->remove($previous);
    }
}
