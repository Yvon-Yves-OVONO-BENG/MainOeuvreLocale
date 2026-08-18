<?php

namespace App\Service;

use App\Entity\EmailVerifications;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class AccountActivationService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly EmailVerifierService $emailVerifierService,
    ) {
    }

    public function sendActivationEmail(User $user): void
    {
        if (!$user->getEmail()) {
            throw new \RuntimeException('Impossible d’envoyer le mail d’activation : email utilisateur manquant.');
        }

        $expiresAt = new \DateTime('+24 hours');
        $token = bin2hex(random_bytes(32));

        $user->setIsActive(false);
        $user->setIsEmailVerified(false);
        $user->setEmailVerificationExpiresAt($expiresAt);

        if (method_exists($user, 'setEmailVerificationToken')) {
            $user->setEmailVerificationToken($token);
        }

        $verification = new EmailVerifications();
        $verification->setUser($user);
        $verification->setToken($token);

        // Je garde le nom exact de ta méthode existante : setEspiresAt()
        $verification->setEspiresAt($expiresAt);

        $verification->setIsUsed(false);

        $this->entityManager->persist($user);
        $this->entityManager->persist($verification);
        $this->entityManager->flush();

        $url = $this->urlGenerator->generate(
            'verify_email',
            ['token' => $token],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $this->emailVerifierService->sendEmailConfirmation($user, $url);
    }
}