<?php

namespace App\Service;

use App\Exception\EmailVerification\InvalidTokenException;
use App\Exception\EmailVerification\TokenAlreadyUsedException;
use App\Exception\EmailVerification\TokenExpiredException;
use App\Exception\EmailVerification\UserNotFoundException;
use Symfony\Contracts\Translation\TranslatorInterface;

class VerifyEmailService
{
    public function __construct(
        private readonly EmailVerificationManager $emailVerificationManager,
        private readonly TranslatorInterface $translator
    ) {
    }

    public function verify(string $token): array
    {
        try {
            $user = $this->emailVerificationManager->verifyAndActivate($token);

            return [
                'ok' => true,
                'flashType' => 'success',
                'message' => $this->translator->trans(
                    'Compte activé avec succès. Choisissez maintenant votre profil.'
                ),
                'routeName' => 'profile_type_choice',
                'routeParams' => [],
                'user' => $user,
            ];
        } catch (InvalidTokenException) {
            return $this->buildFailureResult(
                'danger',
                "Lien d'activation invalide."
            );
        } catch (TokenAlreadyUsedException) {
            return $this->buildFailureResult(
                'warning',
                "Ce lien d'activation a déjà été utilisé."
            );
        } catch (TokenExpiredException) {
            return $this->buildFailureResult(
                'danger',
                "Lien d'activation expiré. Veuillez demander un nouveau lien."
            );
        } catch (UserNotFoundException) {
            return $this->buildFailureResult(
                'danger',
                'Utilisateur introuvable.'
            );
        }
    }

    public function verifyForApi(string $token): array
    {
        $result = $this->verify($token);

        return [
            'ok' => $result['ok'],
            'message' => $result['message'],
            'redirect' => [
                'route' => $result['routeName'],
                'params' => $result['routeParams'],
            ],
            'flashType' => $result['flashType'],
            'email' => $result['user']?->getEmail(),
        ];
    }

    private function buildFailureResult(string $flashType, string $message): array
    {
        return [
            'ok' => false,
            'flashType' => $flashType,
            'message' => $this->translator->trans($message),
            'routeName' => 'app_login',
            'routeParams' => [],
            'user' => null,
        ];
    }
}
