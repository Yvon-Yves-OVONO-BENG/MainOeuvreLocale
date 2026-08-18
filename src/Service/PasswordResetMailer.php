<?php

namespace App\Service;

use App\Entity\User;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Psr\Log\LoggerInterface;

class PasswordResetMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function sendResetLink(User $user, string $url): bool
    {
        // Utilise le même domaine d'expédition que les courriels d'activation.
        // L'ancienne adresse freedomsoftwarepro.com échouait sur les hébergements
        // qui contrôlent strictement le domaine de l'expéditeur (SPF/DMARC).
        $from = new Address('ne-repondez-pas@maindoeuvrelocale.com', "Main d'Œuvre Locale");
        $email = (new TemplatedEmail())
            ->from($from)
            ->sender($from)
            ->returnPath('ne-repondez-pas@maindoeuvrelocale.com')
            ->to(new Address((string) $user->getEmail()))
            ->subject('Réinitialisation de votre mot de passe')
            ->htmlTemplate('emails/reset_password.html.twig')
            ->context([
                'user' => $user,
                'url'  => $url,
            ])
            ->text(sprintf(
                "Bonjour,\n\nVous avez demandé la réinitialisation de votre mot de passe.\nOuvrez ce lien dans l'heure qui suit :\n%s\n\nSi vous n'êtes pas à l'origine de cette demande, ignorez ce message.",
                $url
            ));

        try {
            $this->mailer->send($email);

            $this->logger->info('Courriel de réinitialisation envoyé', [
                'userId' => $user->getId(),
            ]);

            return true;
        } catch (\Throwable $exception) {
            $this->logger->error('Échec du courriel de réinitialisation', [
                'userId' => $user->getId(),
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }
}
