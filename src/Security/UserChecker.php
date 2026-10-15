<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Bloque la connexion des comptes dont l'adresse e-mail n'a pas encore été confirmée.
 */
class UserChecker implements UserCheckerInterface
{
    /**
     * Appelé avant la vérification du mot de passe : refuse les comptes non activés.
     */
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        if (!$user->isVerified()) {
            throw new CustomUserMessageAccountStatusException('Votre compte n\'est pas encore activé : cliquez sur le lien reçu par e-mail.');
        }
    }

    public function checkPostAuth(UserInterface $user, ?TokenInterface $token = null): void
    {
    }
}
