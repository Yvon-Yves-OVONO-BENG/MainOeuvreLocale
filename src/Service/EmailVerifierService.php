<?php

namespace App\Service;

use App\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use App\Entity\Calendrier;

class EmailVerifierService
{
    public function __construct(
        private MailerInterface $mailer,
        private LoggerInterface $logger
    ) {}

    public function sendEmailConfirmation(User $user, string $activationUrl): void
    {
        $from = new Address('ne-repondez-pas@maindoeuvrelocale.com', "Main d'Oeuvre Locale");

        $email = (new TemplatedEmail())
            ->from($from)
            ->sender($from)
            ->returnPath('ne-repondez-pas@maindoeuvrelocale.com')
            ->to(new Address($user->getEmail()))
            ->subject("Main d'Œuvre Locale — confirmez votre adresse e-mail")
            ->htmlTemplate('verify_email/verify_email.html.twig')
            ->context([
                'user' => $user,
                'activationUrl' => $activationUrl,
                'expiresAt' => $user->getEmailVerificationExpiresAt(),
            ])
            ->text(sprintf(
                "Bonjour,\n\nActivez votre compte en cliquant ici : %s\n\nMerci.",
                $activationUrl
            ));

        try {
            $this->mailer->send($email);

            $this->logger->info('Mail activation envoyé', [
                'to' => $user->getEmail(),
            ]);
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Mail activation échec', [
                'to' => $user->getEmail(),
                'error' => $e->getMessage(),
                'debug' => $e->getDebug(),
            ]);

            throw $e;
        }
    }

    public function sendAppointmentProposal(Calendrier $rdv): bool
    {
        $talent = $rdv->getTalent();
        $author = $rdv->getAuthor();
        $job = $rdv->getJob();

        if (!$talent || !$talent->getEmail()) {
            $this->logger->warning('Mail RDV non envoyé : talent ou email manquant', [
                'rdvId' => $rdv->getId(),
            ]);

            return false;
        }

        $from = new Address('ne-repondez-pas@maindoeuvrelocale.com', "Main d'Oeuvre Locale");

        $startAt = $rdv->getStartAt();
        $durationMinutes = $rdv->getDurationMinutes() ?: 30;
        $endAt = $startAt ? $startAt->modify('+' . $durationMinutes . ' minutes') : null;

        $email = (new TemplatedEmail())
            ->from($from)
            ->sender($from)
            ->returnPath('ne-repondez-pas@maindoeuvrelocale.com')
            ->to(new Address($talent->getEmail()))
            ->subject('Nouveau rendez-vous proposé')
            ->htmlTemplate('calendar/email/appointment_proposed.html.twig')
            ->context([
                'rdv' => $rdv,
                'talent' => $talent,
                'author' => $author,
                'job' => $job,
                'startAt' => $startAt,
                'endAt' => $endAt,
                'durationMinutes' => $durationMinutes,
            ])
            ->text(sprintf(
                "Bonjour,\n\nUn rendez-vous vous a été proposé pour la mission : %s.\n\nDate : %s\nDurée : %s minutes\nLieu : %s\n\nMerci.",
                $job ? $job->getTitle() : 'Mission',
                $startAt ? $startAt->format('d/m/Y H:i') : '—',
                $durationMinutes,
                $rdv->getLocation() ?: 'Non précisé'
            ));

        try {
            $this->mailer->send($email);

            $this->logger->info('Mail RDV envoyé', [
                'rdvId' => $rdv->getId(),
                'to' => $talent->getEmail(),
            ]);

            return true;
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Mail RDV échec', [
                'rdvId' => $rdv->getId(),
                'to' => $talent->getEmail(),
                'error' => $e->getMessage(),
                'debug' => $e->getDebug(),
            ]);

            return false;
        }
    }
}
