<?php

namespace App\Service;

use App\Entity\Appointment;
use App\Entity\User;
use App\Repository\AppointmentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class ApplicantCalendarService
{
    public function __construct(
        private readonly AppointmentRepository $appointmentRepository,
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function getCalendarPageData(User $user): array
    {
        $appointments = $this->appointmentRepository->findCalendarAppointmentsForTalent($user);
        $counts = $this->appointmentRepository->countCalendarStatusesForTalent($user);
        $nextAppointments = $this->appointmentRepository->findUpcomingCalendarAppointmentsForTalent(
            $user,
            new \DateTimeImmutable('-1 day'),
            8
        );

        return [
            'appointments' => $appointments,
            'nextAppointments' => $nextAppointments,
            'proposedCount' => $counts['proposedCount'],
            'confirmedCount' => $counts['confirmedCount'],
            'doneCount' => $counts['doneCount'],
        ];
    }

    public function getFeedEvents(
        User $user,
        ?\DateTimeInterface $start = null,
        ?\DateTimeInterface $end = null
    ): array {
        $appointments = $this->appointmentRepository->findCalendarAppointmentsForTalentBetween(
            $user,
            $start,
            $end
        );

        return array_map(fn (Appointment $appointment) => $this->formatCalendarEvent($appointment), $appointments);
    }

    public function getApiCalendarPayload(User $user): array
    {
        $pageData = $this->getCalendarPageData($user);

        return [
            'ok' => true,
            'counts' => [
                'proposed' => $pageData['proposedCount'],
                'confirmed' => $pageData['confirmedCount'],
                'done' => $pageData['doneCount'],
            ],
            'appointments' => array_map(
                fn (Appointment $appointment) => $this->formatAppointmentForApi($appointment),
                $pageData['appointments']
            ),
            'nextAppointments' => array_map(
                fn (Appointment $appointment) => $this->formatAppointmentForApi($appointment),
                $pageData['nextAppointments']
            ),
        ];
    }

    public function respondToAppointment(Appointment $appointment, User $user, ?string $action): array
    {
        $this->assertOwnership($appointment, $user);
        $this->assertRespondableStatus($appointment);

        if ($action === 'accept') {
            $appointment->setStatus(Appointment::STATUS_CONFIRMED);
            $appointment->setConfirmedAt(new \DateTimeImmutable());
        } elseif ($action === 'refuse') {
            $appointment->setStatus(Appointment::STATUS_REFUSED);
            $appointment->setRefusedAt(new \DateTimeImmutable());
        } else {
            throw new BadRequestHttpException('Action invalide.');
        }

        $appointment->touch();
        $this->entityManager->flush();

        return [
            'ok' => true,
            'status' => $appointment->getStatus(),
            'message' => $action === 'accept'
                ? 'Rendez-vous approuvé avec succès.'
                : 'Rendez-vous refusé avec succès.',
        ];
    }

    private function assertOwnership(Appointment $appointment, User $user): void
    {
        if ($appointment->getTalent()?->getId() !== $user->getId()) {
            throw new AccessDeniedHttpException('Accès refusé.');
        }
    }

    private function assertRespondableStatus(Appointment $appointment): void
    {
        if (!in_array($appointment->getStatus(), [
            Appointment::STATUS_PROPOSED,
            Appointment::STATUS_CONFIRMED,
        ], true)) {
            throw new BadRequestHttpException('Ce rendez-vous ne peut plus être modifié.');
        }
    }

    private function formatCalendarEvent(Appointment $appointment): array
    {
        $recruiterName = $appointment->getParticular()?->getPersonalProfile()?->getFullName()
            ?? $appointment->getParticular()?->getEmail()
            ?? 'Recruteur';

        $jobTitle = $appointment->getJob()?->getTitle() ?? 'Rendez-vous';

        return [
            'id' => $appointment->getId(),
            'title' => $jobTitle,
            'start' => $appointment->getStartAt()->format(\DateTimeInterface::ATOM),
            'end' => $appointment->getEndAt()->format(\DateTimeInterface::ATOM),
            'extendedProps' => [
                'status' => $appointment->getStatus(),
                'type' => $appointment->getType(),
                'location' => $appointment->getLocation(),
                'notes' => $appointment->getNotes(),
                'jobTitle' => $jobTitle,
                'recruiterName' => $recruiterName,
                'durationMinutes' => $appointment->getDurationMinutes(),
            ],
        ];
    }

    private function formatAppointmentForApi(Appointment $appointment): array
    {
        return [
            'id' => $appointment->getId(),
            'jobTitle' => $appointment->getJob()?->getTitle() ?? 'Rendez-vous',
            'recruiterName' => $appointment->getParticular()?->getPersonalProfile()?->getFullName()
                ?? $appointment->getParticular()?->getEmail()
                ?? 'Recruteur',
            'status' => $appointment->getStatus(),
            'type' => $appointment->getType(),
            'location' => $appointment->getLocation(),
            'notes' => $appointment->getNotes(),
            'startAt' => $appointment->getStartAt()?->format(\DateTimeInterface::ATOM),
            'endAt' => $appointment->getEndAt()?->format(\DateTimeInterface::ATOM),
            'durationMinutes' => $appointment->getDurationMinutes(),
        ];
    }
}