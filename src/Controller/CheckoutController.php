<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\CheckoutService;
use App\Service\StripeService;
use Psr\Log\LoggerInterface;
use Stripe\Exception\ApiErrorException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Règlement de la commande avec Stripe Checkout.
 */
#[IsGranted('ROLE_USER')]
class CheckoutController extends AbstractController
{
    public function __construct(
        private readonly CheckoutService $checkout,
        private readonly StripeService $stripe,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Bouton « Finaliser ma commande » : crée la commande puis redirige vers la page de paiement Stripe.
     */
    #[Route('/checkout', name: 'app_checkout', methods: ['POST'])]
    public function checkout(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('checkout', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Session expirée, veuillez réessayer.');

            return $this->redirectToRoute('app_cart');
        }

        if (!$this->stripe->isConfigured()) {
            $this->addFlash('error', 'Le paiement en ligne n\'est pas encore configuré (clé Stripe manquante).');

            return $this->redirectToRoute('app_cart');
        }

        try {
            $order = $this->checkout->createOrderFromCart($this->getCustomer());
            $paymentUrl = $this->checkout->startPayment(
                $order,
                // Stripe remplace lui-même {CHECKOUT_SESSION_ID} : l'accolade ne doit pas être encodée.
                $this->generateUrl('app_checkout_success', [], UrlGeneratorInterface::ABSOLUTE_URL).'?session_id={CHECKOUT_SESSION_ID}',
                $this->generateUrl('app_checkout_cancel', [], UrlGeneratorInterface::ABSOLUTE_URL).'?session_id={CHECKOUT_SESSION_ID}',
            );
        } catch (\DomainException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('app_cart');
        } catch (ApiErrorException $e) {
            $this->logger->error('Création de la session Stripe impossible.', ['exception' => $e]);
            $this->addFlash('error', 'Le service de paiement est momentanément indisponible, veuillez réessayer.');

            return $this->redirectToRoute('app_cart');
        }

        // Redirection 303 : le navigateur suit en GET vers la page hébergée par Stripe.
        return $this->redirect($paymentUrl, Response::HTTP_SEE_OTHER);
    }

    /**
     * Retour de Stripe après paiement : vérification auprès de Stripe puis confirmation de la commande.
     */
    #[Route('/checkout/success', name: 'app_checkout_success', methods: ['GET'])]
    public function success(Request $request): Response
    {
        $sessionId = $request->query->getString('session_id');
        if ('' === $sessionId) {
            return $this->redirectToRoute('app_cart');
        }

        try {
            $order = $this->checkout->confirmPayment($sessionId, $this->getCustomer());
        } catch (\DomainException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('app_cart');
        } catch (ApiErrorException $e) {
            $this->logger->error('Vérification du paiement Stripe impossible.', ['exception' => $e]);
            $this->addFlash('error', 'Impossible de vérifier votre paiement pour le moment.');

            return $this->redirectToRoute('app_cart');
        }

        return $this->render('checkout/success.html.twig', [
            'order' => $order,
        ]);
    }

    /**
     * Retour de Stripe quand le client abandonne le paiement : le panier est conservé.
     */
    #[Route('/checkout/cancel', name: 'app_checkout_cancel', methods: ['GET'])]
    public function cancel(Request $request): Response
    {
        $sessionId = $request->query->getString('session_id');
        if ('' !== $sessionId) {
            $this->checkout->cancel($sessionId, $this->getCustomer());
        }
        $this->addFlash('error', 'Le paiement a été annulé, votre panier est conservé.');

        return $this->redirectToRoute('app_cart');
    }

    private function getCustomer(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }
}
