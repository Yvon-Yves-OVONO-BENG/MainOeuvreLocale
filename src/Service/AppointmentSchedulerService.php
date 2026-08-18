<?php

namespace App\Service;

use App\Entity\Application;
use App\Entity\Appointment;
use App\Entity\Conversation;
use App\Entity\Message;
use App\Entity\User;
use App\Repository\ApplicationRepository;
use App\Repository\AppointmentRepository;
use App\Repository\ConversationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mime\Address;

class AppointmentSchedulerService
{
    public function __construct(
        private EntityManagerInterface $em,
        private AppointmentRepository $appointmentRepo,
        private ApplicationRepository $applicationRepo,
        private ConversationRepository $conversationRepo,
        private MailerInterface $mailer,
        private ?HubInterface $mercureHub = null,
    ) {}

    /**
     * Crée un appointment depuis un drag/drop d'une candidature sur le calendrier.
     * $particular = user connecté (propriétaire mission)
     */
    public function createFromApplicationDrop(
        User $particular,
        int $applicationId,
        \DateTimeImmutable $startAt,
        int $durationMinutes = 30,
        string $type = Appointment::TYPE_INTERVIEW,
        ?string $location = null,
        ?string $notes = null
    ): Appointment {
        /** @var Application|null $application */
        $application = $this->applicationRepo->find($applicationId);
        if (!$application) {
            throw new \InvalidArgumentException('Candidature introuvable.');
        }

        // ⚠️ Adapte ces getters à ton entité Application
        $job = $application->getJob();
        if (!$job) {
            throw new \RuntimeException('La candidature n’est liée à aucune mission.');
        }

        if ($job->getCreatedBy()?->getId() !== $particular->getId()) {
            throw new \RuntimeException('Vous ne pouvez pas planifier un rendez-vous sur cette mission.');
        }

        // ⚠️ Adapte selon ton modèle: $application->getUser() / getCandidate() / getApplicant()
        $talent = $application->getUser();
        if (!$talent instanceof User) {
            throw new \RuntimeException('Talent introuvable sur la candidature.');
        }

        $endAt = $startAt->modify(sprintf('+%d minutes', max(15, $durationMinutes)));

        $conversation = $this->findOrCreateConversation($particular, $talent, $application);

        $appointment = (new Appointment())
            ->setParticular($particular)
            ->setTalent($talent)
            ->setJob($job)
            ->setApplication($application)
            ->setConversation($conversation)
            ->setType($type)
            ->setStatus(Appointment::STATUS_PROPOSED)
            ->setStartAt($startAt)
            ->setEndAt($endAt)
            ->setLocation($location)
            ->setNotes($notes)
            ->touch();

        $this->em->persist($appointment);

        $this->addSystemMessage(
            $conversation,
            $particular,
            sprintf(
                "📅 Proposition de rendez-vous d’entretien pour la mission \"%s\".\nDate: %s\nDurée: %d min%s%s",
                (string) ($job->getTitle() ?? 'Mission'),
                $startAt->format('d/m/Y à H:i'),
                $durationMinutes,
                $location ? "\nLieu: ".$location : '',
                $notes ? "\nNote: ".$notes : ''
            ),
            [
                'kind' => 'appointment_proposed',
                'appointment_temp' => true,
            ]
        );

        $this->em->flush();

        $this->sendAppointmentMail(
            to: $talent,
            subject: 'Proposition de rendez-vous d’entretien',
            line1: 'Un rendez-vous vous a été proposé.',
            appointment: $appointment
        );

        $this->publishRealtimeNotification($appointment, 'appointment.proposed');

        return $appointment;
    }

    /**
     * Déplacement/édition via drag d’un event sur le calendrier.
     */
    public function rescheduleByParticular(
        User $particular,
        int $appointmentId,
        \DateTimeImmutable $newStartAt,
        ?int $durationMinutes = null,
        ?string $location = null,
        ?string $notes = null
    ): Appointment {
        $appointment = $this->appointmentRepo->findOneOwnedByParticular($appointmentId, $particular);
        if (!$appointment) {
            throw new \RuntimeException('Rendez-vous introuvable.');
        }

        if (!$appointment->canBeMoved()) {
            throw new \RuntimeException('Ce rendez-vous ne peut plus être modifié.');
        }

        $oldStart = $appointment->getStartAt();
        $oldEnd = $appointment->getEndAt();

        $minutes = $durationMinutes ?? $appointment->getDurationMinutes();
        $newEndAt = $newStartAt->modify(sprintf('+%d minutes', max(15, $minutes)));

        $appointment
            ->setStartAt($newStartAt)
            ->setEndAt($newEndAt)
            ->touch();

        if ($location !== null) {
            $appointment->setLocation($location);
        }
        if ($notes !== null) {
            $appointment->setNotes($notes);
        }

        // reset rappels si on déplace
        $appointment->setReminderJ1SentAt(null);
        $appointment->setReminderH1SentAt(null);

        $conversation = $appointment->getConversation();
        if ($conversation) {
            $this->addSystemMessage(
                $conversation,
                $particular,
                sprintf(
                    "🔁 Rendez-vous reprogrammé.\nAncienne date: %s\nNouvelle date: %s",
                    $oldStart->format('d/m/Y à H:i'),
                    $newStartAt->format('d/m/Y à H:i')
                ),
                [
                    'kind' => 'appointment_rescheduled',
                    'appointment_id' => $appointment->getId(),
                    'old_start' => $oldStart->format(\DateTimeInterface::ATOM),
                    'old_end' => $oldEnd->format(\DateTimeInterface::ATOM),
                    'new_start' => $newStartAt->format(\DateTimeInterface::ATOM),
                    'new_end' => $newEndAt->format(\DateTimeInterface::ATOM),
                ]
            );
        }

        $this->em->flush();

        $this->sendAppointmentMail(
            to: $appointment->getTalent(),
            subject: 'Rendez-vous reprogrammé',
            line1: 'La date/heure de votre rendez-vous a été modifiée.',
            appointment: $appointment
        );

        $this->publishRealtimeNotification($appointment, 'appointment.rescheduled');

        return $appointment;
    }

    public function cancelByParticular(User $particular, int $appointmentId, ?string $reason = null): Appointment
    {
        $appointment = $this->appointmentRepo->findOneOwnedByParticular($appointmentId, $particular);
        if (!$appointment) {
            throw new \RuntimeException('Rendez-vous introuvable.');
        }

        if ($appointment->isCanceledOrRefused() || $appointment->getStatus() === Appointment::STATUS_DONE) {
            throw new \RuntimeException('Ce rendez-vous ne peut plus être annulé.');
        }

        $appointment
            ->setStatus(Appointment::STATUS_CANCELED)
            ->setCanceledAt(new \DateTimeImmutable())
            ->setCanceledBy($particular)
            ->setCancelReason($reason)
            ->touch();

        if ($appointment->getConversation()) {
            $this->addSystemMessage(
                $appointment->getConversation(),
                $particular,
                "❌ Rendez-vous annulé." . ($reason ? "\nMotif: ".$reason : ''),
                [
                    'kind' => 'appointment_canceled',
                    'appointment_id' => $appointment->getId(),
                    'reason' => $reason,
                ]
            );
        }

        $this->em->flush();

        $this->sendAppointmentMail(
            to: $appointment->getTalent(),
            subject: 'Rendez-vous annulé',
            line1: 'Le rendez-vous a été annulé.',
            appointment: $appointment
        );

        $this->publishRealtimeNotification($appointment, 'appointment.canceled');

        return $appointment;
    }

    public function confirmByTalent(User $talent, int $appointmentId): Appointment
    {
        $appointment = $this->appointmentRepo->findOneForTalent($appointmentId, $talent);
        if (!$appointment) {
            throw new \RuntimeException('Rendez-vous introuvable.');
        }

        if ($appointment->getStatus() !== Appointment::STATUS_PROPOSED) {
            throw new \RuntimeException('Ce rendez-vous n’est plus en attente de confirmation.');
        }

        $appointment
            ->setStatus(Appointment::STATUS_CONFIRMED)
            ->setConfirmedAt(new \DateTimeImmutable())
            ->touch();

        if ($appointment->getConversation()) {
            $this->addSystemMessage(
                $appointment->getConversation(),
                $talent,
                "✅ Rendez-vous confirmé par le talent.",
                [
                    'kind' => 'appointment_confirmed',
                    'appointment_id' => $appointment->getId(),
                ]
            );
        }

        $this->em->flush();

        $this->sendAppointmentMail(
            to: $appointment->getParticular(),
            subject: 'Le talent a confirmé le rendez-vous',
            line1: 'Le talent a confirmé le rendez-vous.',
            appointment: $appointment
        );

        $this->publishRealtimeNotification($appointment, 'appointment.confirmed');

        return $appointment;
    }

    public function refuseByTalent(User $talent, int $appointmentId, ?string $reason = null): Appointment
    {
        $appointment = $this->appointmentRepo->findOneForTalent($appointmentId, $talent);
        if (!$appointment) {
            throw new \RuntimeException('Rendez-vous introuvable.');
        }

        if (!\in_array($appointment->getStatus(), [Appointment::STATUS_PROPOSED, Appointment::STATUS_CONFIRMED], true)) {
            throw new \RuntimeException('Ce rendez-vous ne peut pas être refusé.');
        }

        $appointment
            ->setStatus(Appointment::STATUS_REFUSED)
            ->setRefusedAt(new \DateTimeImmutable())
            ->touch();

        if ($appointment->getConversation()) {
            $this->addSystemMessage(
                $appointment->getConversation(),
                $talent,
                "🚫 Rendez-vous refusé par le talent." . ($reason ? "\nMotif: ".$reason : ''),
                [
                    'kind' => 'appointment_refused',
                    'appointment_id' => $appointment->getId(),
                    'reason' => $reason,
                ]
            );
        }

        $this->em->flush();

        $this->sendAppointmentMail(
            to: $appointment->getParticular(),
            subject: 'Le talent a refusé le rendez-vous',
            line1: 'Le talent a refusé le rendez-vous.',
            appointment: $appointment
        );

        $this->publishRealtimeNotification($appointment, 'appointment.refused');

        return $appointment;
    }

    public function markDoneByParticular(User $particular, int $appointmentId): Appointment
    {
        $appointment = $this->appointmentRepo->findOneOwnedByParticular($appointmentId, $particular);
        if (!$appointment) {
            throw new \RuntimeException('Rendez-vous introuvable.');
        }

        if (!\in_array($appointment->getStatus(), [Appointment::STATUS_CONFIRMED, Appointment::STATUS_PROPOSED], true)) {
            throw new \RuntimeException('Ce rendez-vous ne peut pas être terminé.');
        }

        $appointment
            ->setStatus(Appointment::STATUS_DONE)
            ->setDoneAt(new \DateTimeImmutable())
            ->touch();

        if ($appointment->getConversation()) {
            $this->addSystemMessage(
                $appointment->getConversation(),
                $particular,
                "🏁 Rendez-vous marqué comme terminé.",
                [
                    'kind' => 'appointment_done',
                    'appointment_id' => $appointment->getId(),
                ]
            );
        }

        $this->em->flush();
        $this->publishRealtimeNotification($appointment, 'appointment.done');

        return $appointment;
    }

    /**
     * Rappels auto J-1 / H-1 (appelé depuis une Command cron)
     */
    public function sendAutomaticReminders(): array
    {
        $now = new \DateTimeImmutable();

        $j1From = $now->modify('+23 hours');
        $j1To   = $now->modify('+25 hours');

        $h1From = $now->modify('+50 minutes');
        $h1To   = $now->modify('+70 minutes');

        $j1Count = 0;
        $h1Count = 0;

        foreach ($this->appointmentRepo->findForReminderJ1($j1From, $j1To) as $a) {
            $this->sendReminderForAppointment($a, 'J-1');
            $a->setReminderJ1SentAt(new \DateTimeImmutable())->touch();
            $j1Count++;
        }

        foreach ($this->appointmentRepo->findForReminderH1($h1From, $h1To) as $a) {
            $this->sendReminderForAppointment($a, 'H-1');
            $a->setReminderH1SentAt(new \DateTimeImmutable())->touch();
            $h1Count++;
        }

        $this->em->flush();

        return ['j1' => $j1Count, 'h1' => $h1Count];
    }

    private function sendReminderForAppointment(Appointment $a, string $kind): void
    {
        $txt = $kind === 'J-1'
            ? "⏰ Rappel J-1 : rendez-vous demain à ".$a->getStartAt()->format('H:i')."."
            : "⏰ Rappel H-1 : rendez-vous dans environ 1 heure.";

        if ($a->getConversation()) {
            // Message système au nom du particulier (ou crée un user système si tu préfères)
            $this->addSystemMessage(
                $a->getConversation(),
                $a->getParticular(),
                $txt,
                [
                    'kind' => 'appointment_reminder',
                    'reminder' => $kind,
                    'appointment_id' => $a->getId(),
                ]
            );
        }

        // Email au talent
        $this->sendAppointmentMail(
            to: $a->getTalent(),
            subject: sprintf('Rappel %s de rendez-vous', $kind),
            line1: $txt,
            appointment: $a
        );

        // Email au particulier (facultatif mais utile)
        $this->sendAppointmentMail(
            to: $a->getParticular(),
            subject: sprintf('Rappel %s de rendez-vous', $kind),
            line1: $txt,
            appointment: $a
        );

        $this->publishRealtimeNotification($a, 'appointment.reminder', ['reminder' => $kind]);
    }

    private function findOrCreateConversation(User $particular, User $talent, ?Application $application = null): Conversation
    {
        $conversation = $this->conversationRepo->findOneBetweenUsers($particular, $talent);
        if ($conversation) {
            return $conversation;
        }

        // Astuce: ordonner les participants pour garantir l’unicité logique du duo
        $a = $particular;
        $b = $talent;
        if (($a->getId() ?? 0) > ($b->getId() ?? 0)) {
            [$a, $b] = [$b, $a];
        }

        $conversation = (new Conversation())
            ->setParticipantA($a)
            ->setParticipantB($b);

        // Si ton Application a un professionalProfile -> lie-le à la conversation
        if ($application && method_exists($application, 'getProfessionalProfile')) {
            $conversation->setProfessionalProfile($application->getUser()->getProfessionalProfile());
        }

        $this->em->persist($conversation);

        return $conversation;
    }

    private function addSystemMessage(Conversation $conversation, User $sender, string $content, array $meta = []): void
    {
        $message = (new Message())
            ->setConversation($conversation)
            ->setSender($sender) // tu peux remplacer par un user système si tu en as un
            ->setType(Message::TYPE_SYSTEM)
            ->setContent($content)
            ->setMeta($meta);

        $this->em->persist($message);
        $conversation->addMessage($message);
    }

    private function sendAppointmentMail(User $to, string $subject, string $line1, Appointment $appointment): void
    {
        $email = (new TemplatedEmail())
            ->from(new Address('no-reply@maindoeuvrelocale.com', 'Main d’oeuvre locale'))
            ->to((string) $to->getEmail())
            ->subject($subject)
            ->htmlTemplate('emails/appointment_notification.html.twig')
            ->context([
                'line1' => $line1,
                'appointment' => $appointment,
                'job' => $appointment->getJob(),
            ]);

        $this->mailer->send($email);
    }

    private function publishRealtimeNotification(Appointment $appointment, string $event, array $extra = []): void
    {
        if (!$this->mercureHub) {
            return;
        }

        $payload = array_merge([
            'event' => $event,
            'appointmentId' => $appointment->getId(),
            'status' => $appointment->getStatus(),
            'startAt' => $appointment->getStartAt()->format(\DateTimeInterface::ATOM),
            'endAt' => $appointment->getEndAt()->format(\DateTimeInterface::ATOM),
            'jobTitle' => $appointment->getJob()?->getTitle(),
            'talentId' => $appointment->getTalent()?->getId(),
            'particularId' => $appointment->getParticular()?->getId(),
        ], $extra);

        // Topic user particulier
        $topics = [
            sprintf('/users/%d/notifications', $appointment->getParticular()?->getId()),
            sprintf('/users/%d/notifications', $appointment->getTalent()?->getId()),
            sprintf('/appointments/%d', $appointment->getId()),
        ];

        foreach ($topics as $topic) {
            $this->mercureHub->publish(new Update($topic, json_encode($payload, JSON_UNESCAPED_UNICODE)));
        }
    }
}