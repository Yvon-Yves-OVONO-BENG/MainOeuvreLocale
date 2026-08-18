<?php

namespace App\Service;

use App\Entity\Message;
use App\Entity\Notification;
use App\Entity\User;
use App\Repository\ConversationRepository;
use App\Repository\NotificationRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class ProfileCompletionReminderService
{
    private const REMINDER_TITLE = 'Rappel automatique : profil incomplet';

    /**
     * Initialise les services nécessaires aux relances par chat et e-mail.
     */
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly NotificationRepository $notificationRepository,
        private readonly ConversationRepository $conversationRepository,
        private readonly ProfileCompletionService $profileCompletionService,
        private readonly EntityManagerInterface $entityManager,
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Envoie les relances dues et retourne un bilan exploitable par la commande CRON.
     *
     * @return array{scanned:int,due:int,attempted:int,sent:int,chat:int,email:int,failed:int,complete:int,cooldown:int,dryRun:bool}
     */
    public function sendDueReminders(
        int $afterHours = 24,
        int $cooldownDays = 7,
        int $limit = 200,
        bool $dryRun = false,
    ): array {
        $afterHours = max(1, $afterHours);
        $cooldownDays = max(1, $cooldownDays);
        $limit = max(1, min(1000, $limit));
        $now = new \DateTimeImmutable();
        $registeredBefore = $now->modify(sprintf('-%d hours', $afterHours));
        $cooldownSince = $now->modify(sprintf('-%d days', $cooldownDays));
        $chatSender = $this->userRepository->findPlatformNotificationSender();

        $stats = [
            'scanned' => 0,
            'due' => 0,
            'attempted' => 0,
            'sent' => 0,
            'chat' => 0,
            'email' => 0,
            'failed' => 0,
            'complete' => 0,
            'cooldown' => 0,
            'dryRun' => $dryRun,
        ];

        foreach ($this->userRepository->iterateProfileReminderCandidates($registeredBefore) as $user) {
            $stats['scanned']++;

            if (!$user instanceof User || $this->profileCompletionService->isReadyForPublicVisibility($user)) {
                $stats['complete']++;
                continue;
            }

            if ($this->notificationRepository->hasRecentReminder($user, self::REMINDER_TITLE, $cooldownSince)) {
                $stats['cooldown']++;
                continue;
            }

            $stats['due']++;

            if ($dryRun) {
                if ($stats['due'] >= $limit) {
                    break;
                }
                continue;
            }

            $stats['attempted']++;
            $missingParts = $this->profileCompletionService->getMissingVisibilityParts($user);
            $profileUrl = $this->generateProfileUrl();
            $chatSent = $this->sendChatReminder($chatSender, $user, $missingParts, $profileUrl);
            $emailSent = $this->sendEmailReminder($user, $missingParts, $profileUrl);

            $stats['chat'] += $chatSent ? 1 : 0;
            $stats['email'] += $emailSent ? 1 : 0;

            if ($chatSent || $emailSent) {
                $this->recordReminder($user, $missingParts);
                $stats['sent']++;
            } else {
                $stats['failed']++;
            }

            if ($stats['attempted'] >= $limit) {
                break;
            }
        }

        if ($chatSender === null && !$dryRun && $stats['due'] > 0) {
            $this->logger->warning('Aucun administrateur actif trouvé pour envoyer les rappels de profil dans le chat.');
        }

        return $stats;
    }

    /**
     * Crée un message système dans la conversation entre la plateforme et l'utilisateur.
     *
     * @param list<string> $missingParts
     */
    private function sendChatReminder(
        ?User $sender,
        User $recipient,
        array $missingParts,
        string $profileUrl,
    ): bool {
        if ($sender === null || $sender->getId() === $recipient->getId()) {
            return false;
        }

        try {
            $conversation = $this->conversationRepository->findOrCreateBetween($sender, $recipient);
            $message = (new Message())
                ->setConversation($conversation)
                ->setSender($sender)
                ->setType(Message::TYPE_SYSTEM)
                ->setContent($this->buildChatMessage($missingParts, $profileUrl))
                ->setMeta([
                    'kind' => 'profile_completion_reminder',
                    'missingParts' => $missingParts,
                    'profileUrl' => $profileUrl,
                ]);

            $conversation->addMessage($message);
            $this->entityManager->persist($message);
            $this->entityManager->persist($conversation);
            $this->entityManager->flush();

            return true;
        } catch (\Throwable $exception) {
            $this->logger->error('Échec de la relance de profil par chat.', [
                'recipientId' => $recipient->getId(),
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Envoie l'e-mail de relance avec le lien direct vers l'édition du profil.
     *
     * @param list<string> $missingParts
     */
    private function sendEmailReminder(User $recipient, array $missingParts, string $profileUrl): bool
    {
        $emailAddress = trim((string) $recipient->getEmail());
        if ($emailAddress === '' || filter_var($emailAddress, FILTER_VALIDATE_EMAIL) === false) {
            return false;
        }

        try {
            $from = new Address('no-reply@maindoeuvrelocale.com', "Main d'Œuvre Locale");
            $email = (new TemplatedEmail())
                ->from($from)
                ->to(new Address($emailAddress))
                ->subject('Complétez votre profil pour être visible')
                ->htmlTemplate('emails/complete_profile.html.twig')
                ->context([
                    'user' => $recipient,
                    'missingParts' => $missingParts,
                    'profileUrl' => $profileUrl,
                ]);

            $this->mailer->send($email);

            return true;
        } catch (\Throwable $exception) {
            $this->logger->error('Échec de la relance de profil par e-mail.', [
                'recipientId' => $recipient->getId(),
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Mémorise la relance dans les notifications afin de respecter le délai de repos.
     *
     * @param list<string> $missingParts
     */
    private function recordReminder(User $recipient, array $missingParts): void
    {
        try {
            $message = 'Votre profil reste invisible. À compléter : ' . implode(', ', $missingParts) . '.';
            $notification = (new Notification())
                ->setUser($recipient)
                ->setTitle(self::REMINDER_TITLE)
                ->setMessage(mb_substr($message, 0, 255))
                ->setIsRead(false)
                ->setCreatedAt(new \DateTime());

            $this->entityManager->persist($notification);
            $this->entityManager->flush();
        } catch (\Throwable $exception) {
            $this->logger->error('La relance a été envoyée mais son délai de repos n’a pas pu être mémorisé.', [
                'recipientId' => $recipient->getId(),
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Construit le texte concis et actionnable affiché dans le chat interne.
     *
     * @param list<string> $missingParts
     */
    private function buildChatMessage(array $missingParts, string $profileUrl): string
    {
        $missing = $missingParts !== [] ? implode(', ', $missingParts) : 'informations du profil';

        return implode("\n", [
            '👤 **Complétez votre profil**',
            '',
            "Votre profil n’est pas encore visible sur Main d’Œuvre Locale.",
            "Éléments à compléter : {$missing}.",
            '',
            "Compléter maintenant : {$profileUrl}",
        ]);
    }

    /**
     * Génère l'URL absolue de la page d'édition, y compris depuis une commande serveur.
     */
    private function generateProfileUrl(): string
    {
        return $this->urlGenerator->generate('profile_edit', [], UrlGeneratorInterface::ABSOLUTE_URL);
    }
}
