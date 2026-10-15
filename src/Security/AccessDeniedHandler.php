<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Authorization\AccessDeniedHandlerInterface;

/**
 * Utilisateur connecté sans les droits suffisants (ex. un client sur /admin) :
 * il est renvoyé vers l'accueil avec un message au lieu d'une page d'erreur 403.
 */
class AccessDeniedHandler implements AccessDeniedHandlerInterface
{
    public function __construct(private readonly UrlGeneratorInterface $urlGenerator)
    {
    }

    public function handle(Request $request, AccessDeniedException $accessDeniedException): ?Response
    {
        $session = $request->hasSession() ? $request->getSession() : null;
        if ($session instanceof FlashBagAwareSessionInterface) {
            $session->getFlashBag()->add('error', 'Vous n\'avez pas les droits nécessaires pour accéder à cette page.');
        }

        return new RedirectResponse($this->urlGenerator->generate('app_home'));
    }
}
