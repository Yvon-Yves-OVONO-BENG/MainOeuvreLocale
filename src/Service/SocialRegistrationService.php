<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class SocialRegistrationService
{
    private const PROVIDER_FIELDS = [
        'google' => 'googleId',
        'facebook' => 'facebookId',
        'apple' => 'appleId',
        'microsoft' => 'microsoftId',
        'tiktok' => 'tiktokId',
    ];

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function findOrCreateUser(
        string $provider,
        string $providerId,
        ?string $email,
        ?string $name = null,
        ?string $avatarUrl = null,
    ): User {
        $provider = strtolower(trim($provider));
        $providerId = trim($providerId);
        $providerField = self::PROVIDER_FIELDS[$provider] ?? null;

        if ($providerField === null) {
            throw new \InvalidArgumentException('Fournisseur social invalide.');
        }

        if ($providerId === '') {
            throw new \RuntimeException(
                'Le fournisseur social n’a pas retourné d’identifiant utilisateur.'
            );
        }

        /*
         * Cette recherche doit précéder le contrôle de l'e-mail. Apple ne
         * transmet généralement l'e-mail et le nom qu'à la première
         * autorisation, mais renvoie toujours son identifiant utilisateur.
         */
        $user = $this->userRepository->findOneBy([
            $providerField => $providerId,
        ]);

        if ($user instanceof User) {
            $this->refreshSocialMetadata($user, $provider, $avatarUrl);

            return $user;
        }

        $email = $this->normalizeEmail($email);

        if ($email === null) {
            throw new \RuntimeException(
                'Le fournisseur social doit partager une adresse e-mail lors de la première connexion.'
            );
        }

        $user = $this->userRepository->findOneBy([
            'email' => $email,
        ]);

        if (!$user instanceof User) {
            $user = new User();

            $user->setEmail($email);
            $user->setRoles(['ROLE_USER']);

            $randomPassword = bin2hex(random_bytes(32));
            $user->setPassword(
                $this->passwordHasher->hashPassword($user, $randomPassword)
            );

            // Important : complété plus tard après activation
            $user->setPhone(null);
            $user->setTypeCompte(null);

            // Important : même avec Google, on exige l’activation email
            $user->setIsActive(false);
            $user->setIsEmailVerified(false);
            $user->setCreatedAt(new DateTime());
        }

        $this->assertProviderCanBeLinked($user, $provider, $providerId);
        $this->setProviderId($user, $provider, $providerId);

        if ($user->getRegistrationProvider() === null) {
            $user->setRegistrationProvider($provider);
        }

        if ($this->isSafeAvatarUrl($avatarUrl)) {
            $user->setAvatarUrl($avatarUrl);
        }

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    private function normalizeEmail(?string $email): ?string
    {
        if ($email === null) {
            return null;
        }

        $email = strtolower(trim($email));

        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return null;
        }

        return $email;
    }

    private function assertProviderCanBeLinked(
        User $user,
        string $provider,
        string $providerId,
    ): void {
        $currentProviderId = match ($provider) {
            'google' => $user->getGoogleId(),
            'facebook' => $user->getFacebookId(),
            'apple' => $user->getAppleId(),
            'microsoft' => $user->getMicrosoftId(),
            'tiktok' => $user->getTiktokId(),
        };

        if ($currentProviderId !== null && $currentProviderId !== $providerId) {
            throw new \RuntimeException(
                'Ce compte est déjà lié à un autre profil du même fournisseur social.'
            );
        }
    }

    private function setProviderId(User $user, string $provider, string $providerId): void
    {
        match ($provider) {
            'google' => $user->setGoogleId($providerId),
            'facebook' => $user->setFacebookId($providerId),
            'apple' => $user->setAppleId($providerId),
            'microsoft' => $user->setMicrosoftId($providerId),
            'tiktok' => $user->setTiktokId($providerId),
        };
    }

    private function refreshSocialMetadata(
        User $user,
        string $provider,
        ?string $avatarUrl,
    ): void {
        if ($user->getRegistrationProvider() === null) {
            $user->setRegistrationProvider($provider);
        }

        if ($this->isSafeAvatarUrl($avatarUrl)) {
            $user->setAvatarUrl($avatarUrl);
        }

        $this->entityManager->flush();
    }

    private function isSafeAvatarUrl(?string $avatarUrl): bool
    {
        if ($avatarUrl === null || filter_var($avatarUrl, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        return strtolower((string) parse_url($avatarUrl, PHP_URL_SCHEME)) === 'https';
    }
}
