<?php
// src/Service/Job/JobModerationNotificationService.php

namespace App\Service\Job;

use App\Entity\Job;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

class JobModerationNotificationService
{
    public function __construct(
        private MailerInterface $mailer,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {}

    /**
     * Envoie une notification administrative au créateur du job
     * lorsque son offre est modifiée par un modérateur/admin
     */
    public function sendJobModifiedNotification(Job $job, User $moderator, string $modificationNote = ''): bool
    {
        $creator = $job->getCreatedBy();
        
        if (!$creator || !$creator->getEmail()) {
            $this->logger->warning('Notification de modification impossible : créateur sans email', [
                'jobId' => $job->getId(),
            ]);
            return false;
        }

        // 1. Envoyer l'email
        $emailSent = $this->sendModificationEmail($job, $creator, $moderator, $modificationNote);
        
        // 2. Créer un message système interne
        $messageSent = $this->createSystemMessage($job, $creator, $moderator, $modificationNote);
        
        return $emailSent || $messageSent;
    }

    /**
     * Envoie un email diplomatique au créateur du job
     */
    private function sendModificationEmail(Job $job, User $creator, User $moderator, string $modificationNote): bool
    {
        $from = new Address('donotreply@freedomsoftwarepro.com', "Main d'Œuvre Locale");
        
        $email = (new TemplatedEmail())
            ->from($from)
            ->sender($from)
            ->returnPath('donotreply@freedomsoftwarepro.com')
            ->to(new Address($creator->getEmail()))
            ->subject('📝 Votre offre a été optimisée | Your job has been optimized')
            ->htmlTemplate('emails/job_modified_by_moderator.html.twig')
            ->context([
                'job' => $job,
                'creator' => $creator,
                'moderator' => $moderator,
                'modificationNote' => $modificationNote,
                'modifiedAt' => new \DateTimeImmutable(),
            ])
            ->text($this->getPlainTextVersion($job, $creator, $moderator, $modificationNote));

        try {
            $this->mailer->send($email);
            
            $this->logger->info('Email de notification de modification envoyé', [
                'jobId' => $job->getId(),
                'to' => $creator->getEmail(),
                'moderator' => $moderator->getEmail(),
            ]);
            
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Échec envoi email modification job', [
                'jobId' => $job->getId(),
                'error' => $e->getMessage(),
            ]);
            
            return false;
        }
    }

    /**
     * Crée un message système dans la conversation
     */
    private function createSystemMessage(Job $job, User $creator, User $moderator, string $modificationNote): bool
    {
        try {
            // Vérifier si l'entité Message existe
            if (!class_exists('App\Entity\Message') || !class_exists('App\Entity\Conversation')) {
                $this->logger->info('Entités Message/Conversation non disponibles, message système ignoré');
                return false;
            }

            // Trouver ou créer une conversation entre le modérateur et le créateur
            $conversation = $this->findOrCreateConversation($moderator, $creator);
            
            if (!$conversation) {
                return false;
            }

            // Créer le message système
            $message = new \App\Entity\Message();
            $message->setConversation($conversation);
            $message->setSender($moderator);
            $message->setType(\App\Entity\Message::TYPE_SYSTEM);
            $message->setContent($this->buildSystemMessageContent($job, $moderator, $modificationNote));
            $message->setMeta([
                'type' => 'job_modification',
                'jobId' => $job->getId(),
                'jobTitle' => $job->getTitle(),
                'moderatorId' => $moderator->getId(),
                'modificationNote' => $modificationNote,
            ]);

            $this->entityManager->persist($message);
            $this->entityManager->flush();

            $this->logger->info('Message système de modification créé', [
                'jobId' => $job->getId(),
                'messageId' => $message->getId(),
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Échec création message système', [
                'jobId' => $job->getId(),
                'error' => $e->getMessage(),
            ]);
            
            return false;
        }
    }

    /**
     * Trouve ou crée une conversation entre deux utilisateurs
     */
    private function findOrCreateConversation(User $user1, User $user2): ?\App\Entity\Conversation
    {
        // S'assurer que participantA a le plus petit ID
        if ($user1->getId() < $user2->getId()) {
            $participantA = $user1;
            $participantB = $user2;
        } else {
            $participantA = $user2;
            $participantB = $user1;
        }

        // Chercher une conversation existante
        $conversation = $this->entityManager
            ->getRepository(\App\Entity\Conversation::class)
            ->findOneBy([
                'participantA' => $participantA,
                'participantB' => $participantB,
            ]);

        // Créer une nouvelle conversation si nécessaire
        if (!$conversation) {
            $conversation = new \App\Entity\Conversation();
            $conversation->setParticipantA($participantA);
            $conversation->setParticipantB($participantB);
            
            $this->entityManager->persist($conversation);
            $this->entityManager->flush();
        }

        return $conversation;
    }

    /**
     * Construit le contenu du message système (bilingue)
     */
    private function buildSystemMessageContent(Job $job, User $moderator, string $modificationNote): string
    {
        $lines = [
            "📋 **Offre modifiée | Job Updated**",
            "",
            "━━━━━━━━━━━━━━━━━━━━━━━━━━━",
            "🇫🇷 **Français**",
            "━━━━━━━━━━━━━━━━━━━━━━━━━━━",
            "",
            "Bonjour,",
            "",
            "Dans le cadre de notre engagement à maintenir la qualité des offres sur Main d'Œuvre Locale, nous avons apporté quelques ajustements à votre offre **« {$job->getTitle()} »** afin de l'optimiser et de la rendre plus attractive pour les talents.",
            "",
        ];

        if (!empty($modificationNote)) {
            $lines[] = "**💬 Note de l'équipe de modération :**";
            $lines[] = "> {$modificationNote}";
            $lines[] = "";
        }

        $lines = array_merge($lines, [
            "Ces modifications visent à :",
            "✅ Améliorer la visibilité de votre offre",
            "✅ Assurer la conformité avec nos standards de qualité",
            "✅ Faciliter la compréhension par les candidats potentiels",
            "",
            "Nous restons à votre entière disposition pour toute question ou information complémentaire.",
            "",
            "Cordialement,",
            "L'équipe de Modération de Main d'Œuvre Locale 🤝",
            "",
            "━━━━━━━━━━━━━━━━━━━━━━━━━━━",
            "🇬🇧 **English**",
            "━━━━━━━━━━━━━━━━━━━━━━━━━━━",
            "",
            "Hello,",
            "",
            "As part of our commitment to maintaining the quality of listings on Main d'Œuvre Locale, we have made some adjustments to your job posting **« {$job->getTitle()} »** to optimize it and make it more attractive to talents.",
            "",
        ]);

        if (!empty($modificationNote)) {
            $lines[] = "**💬 Note from the moderation team:**";
            $lines[] = "> {$modificationNote}";
            $lines[] = "";
        }

        $lines = array_merge($lines, [
            "These adjustments aim to:",
            "✅ Improve the visibility of your job posting",
            "✅ Ensure compliance with our quality standards",
            "✅ Make it easier for candidates to understand",
            "",
            "We remain at your entire disposal for any questions or further information.",
            "",
            "Best regards,",
            "The Moderation Team of Main d'Œuvre Locale 🤝",
        ]);

        return implode("\n", $lines);
    }

    /**
     * Version texte brut de l'email (bilingue)
     */
    private function getPlainTextVersion(Job $job, User $creator, User $moderator, string $modificationNote): string
    {
        $lines = [
            "═══════════════════════════════════════════",
            "🇫🇷 FRANÇAIS",
            "═══════════════════════════════════════════",
            "",
            "Bonjour {$creator->getEmail()},",
            "",
            "Nous avons apporté quelques ajustements à votre offre \"{$job->getTitle()}\" afin de l'optimiser et de la rendre plus attractive pour les talents.",
            "",
        ];

        if (!empty($modificationNote)) {
            $lines[] = "Note de l'équipe de modération : {$modificationNote}";
            $lines[] = "";
        }

        $lines = array_merge($lines, [
            "Ces modifications visent à :",
            "✅ Améliorer la visibilité de votre offre",
            "✅ Assurer la conformité avec nos standards de qualité",
            "✅ Faciliter la compréhension par les candidats potentiels",
            "",
            "Nous restons à votre entière disposition pour toute question ou information complémentaire.",
            "",
            "Cordialement,",
            "L'équipe de Modération de Main d'Œuvre Locale",
            "",
            "═══════════════════════════════════════════",
            "🇬🇧 ENGLISH",
            "═══════════════════════════════════════════",
            "",
            "Hello {$creator->getEmail()},",
            "",
            "We have made some adjustments to your job posting \"{$job->getTitle()}\" to optimize it and make it more attractive to talents.",
            "",
        ]);

        if (!empty($modificationNote)) {
            $lines[] = "Note from the moderation team: {$modificationNote}";
            $lines[] = "";
        }

        $lines = array_merge($lines, [
            "These adjustments aim to:",
            "✅ Improve the visibility of your job posting",
            "✅ Ensure compliance with our quality standards",
            "✅ Make it easier for candidates to understand",
            "",
            "We remain at your entire disposal for any questions or further information.",
            "",
            "Best regards,",
            "The Moderation Team of Main d'Œuvre Locale",
            "",
            "───────────────────────────────────────────",
            "Réf : {$job->getReference()}",
            "Offre : {$job->getTitle()}",
            "Ville : {$job->getCity()}",
            "───────────────────────────────────────────",
            "Job Ref: {$job->getReference()}",
            "Job Title: {$job->getTitle()}",
            "City: {$job->getCity()}",
            "───────────────────────────────────────────",
        ]);

        return implode("\n", $lines);
    }
}