<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class ResetPasswordService
{
    private const CSRF_ID = 'reset_password';

    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly PasswordResetMailer $passwordResetMailer,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function getForgotPageData(): array
    {
        return [
            'csrfToken' => $this->generateCsrfToken(),
        ];
    }

    public function getApiForgotPagePayload(): array
    {
        return [
            'ok' => true,
            'csrfToken' => $this->generateCsrfToken(),
        ];
    }

    public function requestPasswordReset(string $email, ?string $submittedCsrfToken): array
    {
        if (!$this->isValidCsrfToken($submittedCsrfToken)) {
            return [
                'ok' => false,
                'flashType' => 'danger',
                'message' => $this->translator->trans("La demande n'a pas été traitée. \n Veuillez réesayer"),
                'routeName' => 'app_forgot_password',
                'routeParams' => [],
            ];
        }

        $user = $this->userRepository->findOneByEmailAddress($email);

        if ($user instanceof User) {
            $token = bin2hex(random_bytes(32));

            $user->setResetPasswordToken($token);
            $user->setResetPasswordExpiresAt(new \DateTime('+1 hour'));

            $this->entityManager->flush();

            $url = $this->urlGenerator->generate(
                'app_reset_password',
                ['token' => $token],
                UrlGeneratorInterface::ABSOLUTE_URL
            );

            if (!$this->passwordResetMailer->sendResetLink($user, $url)) {
                // Un lien qui n'a pas été envoyé ne doit pas rester utilisable.
                $user->setResetPasswordToken(null);
                $user->setResetPasswordExpiresAt(null);
                $this->entityManager->flush();

                return [
                    'ok' => false,
                    'flashType' => 'danger',
                    'message' => $this->translator->trans(
                        "Le courriel n'a pas pu être envoyé pour le moment. Vérifiez la configuration MAILER_DSN puis réessayez."
                    ),
                    'routeName' => 'app_forgot_password',
                    'routeParams' => [],
                ];
            }
        }

        return [
            'ok' => true,
            'flashType' => 'success',
            'message' => $this->translator->trans(
                'Si un compte existe avec cet email, un lien de réinitialisation a été envoyé.'
            ),
            'routeName' => 'app_forgot_password',
            'routeParams' => [],
        ];
    }

    public function getResetPageData(string $token): array
    {
        $user = $this->userRepository->findOneByResetPasswordToken($token);

        if (!$user instanceof User) {
            return [
                'ok' => false,
                'flashType' => 'danger',
                'message' => $this->translator->trans('Lien invalide ou déjà utilisé.'),
                'routeName' => 'app_login',
                'routeParams' => [],
            ];
        }

        $expiresAt = $user->getResetPasswordExpiresAt();

        if ($expiresAt && $expiresAt < new \DateTime()) {
            return [
                'ok' => false,
                'flashType' => 'danger',
                'message' => $this->translator->trans('Lien expiré. Veuillez refaire la demande.'),
                'routeName' => 'app_forgot_password',
                'routeParams' => [],
            ];
        }

        return [
            'ok' => true,
            'token' => $token,
            'csrfToken' => $this->generateCsrfToken(),
            'user' => $user,
        ];
    }

    public function getApiResetPagePayload(string $token): array
    {
        $data = $this->getResetPageData($token);

        if (!$data['ok']) {
            return [
                'ok' => false,
                'message' => $data['message'],
                'redirect' => [
                    'route' => $data['routeName'],
                    'params' => $data['routeParams'],
                ],
            ];
        }

        return [
            'ok' => true,
            'token' => $token,
            'csrfToken' => $data['csrfToken'],
        ];
    }

    public function resetPassword(string $token, string $plainPassword, ?string $submittedCsrfToken): array
    {
        if (!$this->isValidCsrfToken($submittedCsrfToken)) {
            return [
                'ok' => false,
                'flashType' => 'danger',
                'message' => $this->translator->trans("La demande n'a pas été traitée. \n Veuillez réesayer"),
                'routeName' => 'app_reset_password',
                'routeParams' => [
                    'token' => $token,
                ],
            ];
        }

        $user = $this->userRepository->findOneByResetPasswordToken($token);

        if (!$user instanceof User) {
            return [
                'ok' => false,
                'flashType' => 'danger',
                'message' => $this->translator->trans('Lien invalide ou déjà utilisé.'),
                'routeName' => 'app_login',
                'routeParams' => [],
            ];
        }

        $expiresAt = $user->getResetPasswordExpiresAt();

        if ($expiresAt && $expiresAt < new \DateTime()) {
            return [
                'ok' => false,
                'flashType' => 'danger',
                'message' => $this->translator->trans('Lien expiré. Veuillez refaire la demande.'),
                'routeName' => 'app_forgot_password',
                'routeParams' => [],
            ];
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));
        $user->setResetPasswordToken(null);
        $user->setResetPasswordExpiresAt(null);

        $this->entityManager->flush();

        return [
            'ok' => true,
            'flashType' => 'success',
            'message' => $this->translator->trans('Mot de passe réinitialisé avec succès. Connectez-vous.'),
            'routeName' => 'app_login',
            'routeParams' => [],
        ];
    }

    public function getApiResetResult(string $token, string $plainPassword): array
    {
        $result = $this->resetPassword($token, $plainPassword, $this->generateCsrfToken());

        return [
            'ok' => $result['ok'],
            'message' => $result['message'],
            'redirect' => [
                'route' => $result['routeName'],
                'params' => $result['routeParams'],
            ],
        ];
    }

    public function generateCsrfToken(): string
    {
        return $this->csrfTokenManager->getToken(self::CSRF_ID)->getValue();
    }

    public function isValidCsrfToken(?string $submittedToken): bool
    {
        return $this->csrfTokenManager->isTokenValid(
            new CsrfToken(self::CSRF_ID, (string) $submittedToken)
        );
    }
}
