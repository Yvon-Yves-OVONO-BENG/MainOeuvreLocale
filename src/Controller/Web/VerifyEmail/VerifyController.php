<?php

namespace App\Controller\Web\VerifyEmail;

use App\Entity\User;
use App\Security\UserAuthenticator;
use App\Service\VerifyEmailService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;

final class VerifyController extends AbstractController
{
    #[Route('/verify-email/{token}', name: 'verify_email', methods: ['GET'])]
    public function __invoke(
        string $token,
        Request $request,
        VerifyEmailService $verifyEmailService,
        UserAuthenticatorInterface $userAuthenticator,
        UserAuthenticator $authenticator
    ): Response
    {
        $result = $verifyEmailService->verify($token);

        $this->addFlash($result['flashType'], $result['message']);

        if (($result['ok'] ?? false) && ($result['user'] ?? null) instanceof User) {
            try {
                $userAuthenticator->authenticateUser($result['user'], $authenticator, $request);
            } catch (\Throwable) {
                $this->addFlash('warning', 'Votre compte est activé. Connectez-vous pour choisir votre profil.');

                return $this->redirectToRoute('app_login', [
                    'email' => $result['user']->getEmail(),
                ]);
            }
        }

        return $this->redirectToRoute(
            $result['routeName'],
            $result['routeParams']
        );
    }
}
