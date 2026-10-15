<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Repository\UserRepository;
use App\Security\EmailVerifier;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;

/**
 * Inscription des clients et activation de leur compte par e-mail.
 */
class RegistrationController extends AbstractController
{
    public function __construct(private readonly EmailVerifier $emailVerifier)
    {
    }

    /**
     * Inscription d'un client : le compte est créé inactif et un lien d'activation est envoyé par e-mail.
     */
    #[Route('/register', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(Request $request, UserPasswordHasherInterface $passwordHasher, EntityManagerInterface $entityManager, LoggerInterface $logger): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var string $plainPassword */
            $plainPassword = $form->get('plainPassword')->getData();
            $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));
            $user->setRoles(['ROLE_USER']);

            $entityManager->persist($user);
            $entityManager->flush();

            try {
                $this->emailVerifier->sendEmailConfirmation($user);
                $this->addFlash('success', sprintf('Votre compte est créé. Un e-mail d\'activation a été envoyé à %s.', $user->getEmail()));
            } catch (TransportExceptionInterface $e) {
                $logger->error('Envoi de l\'e-mail d\'activation impossible.', ['exception' => $e]);
                $this->addFlash('error', 'Votre compte est créé mais l\'e-mail d\'activation n\'a pas pu être envoyé. Réessayez plus tard depuis la page de connexion.');
            }

            return $this->redirectToRoute('app_home');
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form,
        ], new Response(null, $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    /**
     * Lien reçu par e-mail : active le compte, connecte l'utilisateur et le renvoie sur l'accueil.
     */
    #[Route('/verify/email', name: 'app_verify_email', methods: ['GET'])]
    public function verifyUserEmail(Request $request, UserRepository $userRepository, Security $security): Response
    {
        $id = $request->query->get('id');
        $user = null !== $id && ctype_digit($id) ? $userRepository->find((int) $id) : null;

        if (null === $user) {
            $this->addFlash('error', 'Lien d\'activation invalide.');

            return $this->redirectToRoute('app_register');
        }

        // La signature est contrôlée à chaque fois, même pour un compte déjà actif :
        // sans cela, l'URL ?id=… suffirait à se connecter à la place de quelqu'un.
        try {
            $this->emailVerifier->handleEmailConfirmation($request, $user);
        } catch (VerifyEmailExceptionInterface $exception) {
            $this->addFlash('error', 'Ce lien d\'activation est invalide ou a expiré.');

            return $this->redirectToRoute('app_register');
        }

        $security->login($user, 'form_login', 'main');
        $this->addFlash('success', 'Votre compte est activé, bienvenue chez Stubborn !');

        return $this->redirectToRoute('app_home');
    }

    /**
     * Renvoie un lien d'activation (depuis la page de connexion).
     */
    #[Route('/verify/resend', name: 'app_verify_resend', methods: ['POST'])]
    public function resend(Request $request, UserRepository $userRepository, LoggerInterface $logger): Response
    {
        if (!$this->isCsrfTokenValid('verify_resend', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $user = $userRepository->findOneBy(['name' => trim((string) $request->request->get('name'))]);
        // Message identique dans tous les cas pour ne pas révéler quels comptes existent.
        if (null !== $user && !$user->isVerified()) {
            try {
                $this->emailVerifier->sendEmailConfirmation($user);
            } catch (TransportExceptionInterface $e) {
                $logger->error('Renvoi de l\'e-mail d\'activation impossible.', ['exception' => $e]);
            }
        }
        $this->addFlash('success', 'Si ce compte existe et n\'est pas encore activé, un nouvel e-mail vient d\'être envoyé.');

        return $this->redirectToRoute('app_login');
    }
}
