<?php

namespace App\Service;

use App\Entity\EmailVerifications;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class RegistrationService
{
    private const CSRF_ID = 'registration';

    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly EntityManagerInterface $entityManager,
        private readonly EmailVerifierService $emailVerifierService,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly UserRepository $userRepository
    ) {
    }

    public function createRegistrationDraft(): User
    {
        return new User();
    }

    public function getRegisterPageData(): array
    {
        return [
            'csrfToken' => $this->generateCsrfToken(),
        ];
    }

    public function getApiRegisterPagePayload(): array
    {
        return [
            'ok' => true,
            'csrfToken' => $this->generateCsrfToken(),
        ];
    }

    public function register(User $user, string $plainPassword, ?string $submittedCsrfToken): array
    {
        if (!$this->isValidCsrfToken($submittedCsrfToken)) {
            return [
                'ok' => false,
                'flashType' => 'danger',
                'message' => $this->translator->trans('Vous avez essayé de créer frauduleusement un compte'),
                'routeName' => 'accueil',
                'routeParams' => [],
            ];
        }
        
        $email = strtolower(trim((string) $user->getEmail()));
        $user->setEmail($email);

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [
                'ok' => false,
                'flashType' => 'danger',
                'message' => $this->translator->trans('Adresse email invalide.'),
                'routeName' => 'app_register',
                'routeParams' => [],
            ];
        }

        if ($this->userRepository->findOneByEmailAddress($email) instanceof User) {
            return [
                'ok' => false,
                'flashType' => 'danger',
                'message' => $this->translator->trans('Cette adresse email est déjà utilisée.'),
                'routeName' => 'app_register',
                'routeParams' => [],
            ];
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));
        $user->setIsActive(false);
        $user->setIsEmailVerified(false);
        $user->setCreatedAt(new \DateTime('now'));
        $user->setEmailVerificationExpiresAt(new \DateTime('+24 hours'));

        $typeCompte = $user->getTypeCompte();
        if ($typeCompte && $typeCompte->getDefaultRole()) {
            $user->setRoles([$typeCompte->getDefaultRole()]);
        } else {
            $user->setRoles(['ROLE_USER']);
        }

        $this->entityManager->persist($user);

        $token = bin2hex(random_bytes(32));

        $verification = new EmailVerifications();
        $verification->setUser($user);
        $verification->setToken($token);
        $verification->setEspiresAt(new \DateTime('+24 hours'));
        $verification->setIsUsed(false);

        $this->entityManager->persist($verification);
        $this->entityManager->flush();

        $url = $this->urlGenerator->generate(
            'verify_email',
            ['token' => $token],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $mailSent = true;
        try {
            $this->emailVerifierService->sendEmailConfirmation($user, $url);
        } catch (\Throwable) {
            // Le compte et le token sont déjà persistés : on ne fait jamais tomber
            // l'inscription à cause d'un incident SMTP. L'utilisateur peut renvoyer
            // le lien depuis l'écran de connexion.
            $mailSent = false;
        }

        return [
            'ok' => true,
            'flashType' => $mailSent ? 'success' : 'warning',
            'message' => $this->translator->trans(
                $mailSent
                    ? 'Votre compte a été créé. Vérifiez votre email pour l\'activer.'
                    : 'Votre compte a été créé, mais l\'email d\'activation n\'a pas pu être envoyé. Utilisez « Renvoyer l\'email d\'activation » sur la page de connexion.'
            ),
            'routeName' => 'app_login',
            'routeParams' => [],
            'user' => $user,
            'token' => $token,
            'verificationUrl' => $url,
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