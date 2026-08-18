<?php

namespace App\Controller\Web;

use App\Entity\AccountDeletionRequest;
use App\Repository\AccountDeletionRequestRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class LegalController extends AbstractController
{
    /**
     * Ancienne URL conservée pour compatibilité.
     */
    #[Route(
        '/suppression-donnees',
        name: 'app_suppression_donnees',
        methods: ['GET']
    )]
    public function suppressionDonnees(): Response
    {
        return $this->redirectToRoute('app_suppression_compte', [], Response::HTTP_MOVED_PERMANENTLY);
    }

    /**
     * Page publique demandée par Google Play pour permettre à un utilisateur
     * de demander la suppression de son compte depuis le Web, sans installer
     * l'application mobile.
     */
    #[Route(
        '/suppression-compte',
        name: 'app_suppression_compte',
        methods: ['GET', 'POST']
    )]
    public function suppressionCompte(
        Request $request,
        UserRepository $userRepository,
        AccountDeletionRequestRepository $deletionRequestRepository,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher,
        CsrfTokenManagerInterface $csrfTokenManager
    ): Response {
        $success = false;
        $errors = [];
        $email = '';
        $reason = '';

        $currentUser = $this->getUser();
        if ($currentUser && method_exists($currentUser, 'getEmail')) {
            $email = (string) $currentUser->getEmail();
        }

        if ($request->isMethod('POST')) {
            $email = mb_strtolower(trim((string) $request->request->get('email', '')));
            $password = (string) $request->request->get('password', '');
            $reason = trim((string) $request->request->get('reason', ''));
            $confirmed = (string) $request->request->get('confirm_delete', '') === '1';
            $token = (string) $request->request->get('_token', '');

            if (!$csrfTokenManager->isTokenValid(new CsrfToken('public_account_deletion_request', $token))) {
                $errors[] = 'La session de sécurité a expiré. Rechargez la page puis réessayez.';
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Saisissez une adresse e-mail valide.';
            }

            if ($password === '') {
                $errors[] = 'Saisissez le mot de passe de votre compte.';
            }

            if (!$confirmed) {
                $errors[] = 'Confirmez que vous souhaitez demander la suppression de votre compte.';
            }

            if (mb_strlen($reason) > 1000) {
                $errors[] = 'Le motif ne doit pas dépasser 1 000 caractères.';
            }

            if ($errors === []) {
                $user = $userRepository->findOneByEmailAddress($email);

                // Message volontairement générique : on ne révèle pas si une adresse
                // e-mail est inscrite sur la plateforme.
                if (!$user || !$passwordHasher->isPasswordValid($user, $password)) {
                    $errors[] = 'Adresse e-mail ou mot de passe incorrect.';
                } else {
                    $deletionRequest = $deletionRequestRepository->findOneBy(['user' => $user]);

                    if (!$deletionRequest) {
                        $deletionRequest = (new AccountDeletionRequest())
                            ->setUser($user)
                            ->setReason($reason !== '' ? $reason : null);

                        $em->persist($deletionRequest);
                    } elseif ($deletionRequest->getStatus() === AccountDeletionRequest::STATUS_REJECTED) {
                        // Autorise une nouvelle demande après un éventuel rejet précédent.
                        $deletionRequest
                            ->setStatus(AccountDeletionRequest::STATUS_PENDING)
                            ->setReason($reason !== '' ? $reason : null)
                            ->setApprovedAt(null)
                            ->setRejectedAt(null)
                            ->setScheduledAt(null)
                            ->setProcessedAt(null)
                            ->setHandledBy(null)
                            ->setAdminNote(null);
                    } elseif ($reason !== '' && $deletionRequest->getStatus() === AccountDeletionRequest::STATUS_PENDING) {
                        $deletionRequest->setReason($reason);
                    }

                    $em->flush();
                    $success = true;

                    // On vide les champs sensibles du rendu après succès.
                    $reason = '';
                }
            }
        }

        return $this->render('legal/suppression_compte.html.twig', [
            'success' => $success,
            'errors' => $errors,
            'form_email' => $email,
            'form_reason' => $reason,
            'seo_description' => 'Demandez la suppression de votre compte Main d’Œuvre Locale et consultez les informations relatives à la suppression ou à l’anonymisation de vos données.',
        ]);
    }
}
