<?php

namespace App\Controller\Web;

use App\Entity\Appointment;
use App\Entity\User;
use App\Repository\AppointmentRepository;
use App\Repository\JobRepository;
use App\Service\AppointmentSchedulerService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/calendar', name: 'calendar_')]
class CalendarControllerCopie extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(JobRepository $jobRepository, AppointmentRepository $appointmentRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        /** @var User $me */
        $me = $this->getUser();

        $jobsWithApplications = $jobRepository->findMyJobsWithApplicationsForCalendar($me);
        $statusStats = $appointmentRepository->countByStatusForParticular($me);

        return $this->render('calendar/calendar.html.twig', [
            'jobsWithApplications' => $jobsWithApplications,
            'statusStats' => $statusStats,
            'eventsFeedUrl' => $this->generateUrl('calendar_events_feed'),
            'createDropUrl' => $this->generateUrl('calendar_drop_create'),
        ]);
    }

    #[Route('/events-feed', name: 'events_feed', methods: ['GET'])]
    public function eventsFeed(Request $request, AppointmentRepository $repo): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var User $me */
        $me = $this->getUser();

        $start = new \DateTimeImmutable((string)$request->query->get('start', 'now'));
        $end   = new \DateTimeImmutable((string)$request->query->get('end', 'now +1 month'));

        $events = [];
        foreach ($repo->findCalendarRangeForParticular($me, $start, $end) as $a) {
            $events[] = [
                'id' => (string)$a->getId(),
                'title' => sprintf(
                    '%s %s',
                    match ($a->getStatus()) {
                        Appointment::STATUS_CONFIRMED => '✅',
                        Appointment::STATUS_REFUSED => '🚫',
                        Appointment::STATUS_CANCELED => '❌',
                        Appointment::STATUS_DONE => '🏁',
                        default => '🕓',
                    },
                    $a->getTalent()?->getUserIdentifier() ?? $a->getTalent()?->getEmail() ?? 'Talent'
                ),
                'start' => $a->getStartAt()->format(\DateTimeInterface::ATOM),
                'end' => $a->getEndAt()->format(\DateTimeInterface::ATOM),
                'editable' => $a->canBeMoved(),
                'backgroundColor' => match ($a->getStatus()) {
                    Appointment::STATUS_CONFIRMED => '#16a34a',
                    Appointment::STATUS_REFUSED => '#dc2626',
                    Appointment::STATUS_CANCELED => '#6b7280',
                    Appointment::STATUS_DONE => '#0ea5e9',
                    default => '#f59e0b', // proposed
                },
                'borderColor' => 'transparent',
                'extendedProps' => [
                    'appointmentId' => $a->getId(),
                    'status' => $a->getStatus(),
                    'type' => $a->getType(),
                    'location' => $a->getLocation(),
                    'notes' => $a->getNotes(),
                    'jobTitle' => $a->getJob()?->getTitle(),
                    'jobId' => $a->getJob()?->getId(),
                    'talentId' => $a->getTalent()?->getId(),
                    'talentName' => $a->getTalent()?->getUserIdentifier() ?? $a->getTalent()?->getEmail(),
                ],
            ];
        }

        return $this->json($events);
    }

    /**
     * Création depuis drag candidat -> calendrier
     */
    #[Route('/appointments/drop-create', name: 'drop_create', methods: ['POST'])]
    public function dropCreate(Request $request, AppointmentSchedulerService $scheduler): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var User $me */
        $me = $this->getUser();

        $data = json_decode($request->getContent(), true) ?: [];

        try {
            $applicationId = (int)($data['applicationId'] ?? 0);
            $startAt = new \DateTimeImmutable((string)($data['startAt'] ?? 'now'));
            $duration = (int)($data['durationMinutes'] ?? 30);

            $appointment = $scheduler->createFromApplicationDrop(
                particular: $me,
                applicationId: $applicationId,
                startAt: $startAt,
                durationMinutes: $duration,
                type: (string)($data['type'] ?? Appointment::TYPE_INTERVIEW),
                location: isset($data['location']) ? (string)$data['location'] : null,
                notes: isset($data['notes']) ? (string)$data['notes'] : null,
            );

            return $this->json([
                'ok' => true,
                'appointmentId' => $appointment->getId(),
                'status' => $appointment->getStatus(),
                'startAt' => $appointment->getStartAt()->format(\DateTimeInterface::ATOM),
                'endAt' => $appointment->getEndAt()->format(\DateTimeInterface::ATOM),
            ]);
        } catch (\Throwable $e) {
            return $this->json(['ok' => false, 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * Édition/drag d’un event existant dans le calendrier
     */
    #[Route('/appointments/{id}/move', name: 'move', methods: ['PATCH'])]
    public function move(int $id, Request $request, AppointmentSchedulerService $scheduler): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var User $me */
        $this->getUser();
        $me = $this->getUser();

        $data = json_decode($request->getContent(), true) ?: [];

        try {
            $appointment = $scheduler->rescheduleByParticular(
                particular: $me,
                appointmentId: $id,
                newStartAt: new \DateTimeImmutable((string)($data['startAt'] ?? 'now')),
                durationMinutes: isset($data['durationMinutes']) ? (int)$data['durationMinutes'] : null,
                location: array_key_exists('location', $data) ? (string)$data['location'] : null,
                notes: array_key_exists('notes', $data) ? (string)$data['notes'] : null,
            );

            return $this->json([
                'ok' => true,
                'appointmentId' => $appointment->getId(),
                'status' => $appointment->getStatus(),
                'startAt' => $appointment->getStartAt()->format(\DateTimeInterface::ATOM),
                'endAt' => $appointment->getEndAt()->format(\DateTimeInterface::ATOM),
            ]);
        } catch (\Throwable $e) {
            return $this->json(['ok' => false, 'message' => $e->getMessage()], 400);
        }
    }

    #[Route('/appointments/{id}/cancel', name: 'cancel', methods: ['POST'])]
    public function cancel(int $id, Request $request, AppointmentSchedulerService $scheduler): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var User $me */
        $me = $this->getUser();

        $data = json_decode($request->getContent(), true) ?: [];

        try {
            $appointment = $scheduler->cancelByParticular(
                particular: $me,
                appointmentId: $id,
                reason: isset($data['reason']) ? trim((string)$data['reason']) : null,
            );

            return $this->json([
                'ok' => true,
                'appointmentId' => $appointment->getId(),
                'status' => $appointment->getStatus(),
            ]);
        } catch (\Throwable $e) {
            return $this->json(['ok' => false, 'message' => $e->getMessage()], 400);
        }
    }

    #[Route('/appointments/{id}/done', name: 'done', methods: ['POST'])]
    public function done(int $id, AppointmentSchedulerService $scheduler): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var User $me */
        $me = $this->getUser();

        try {
            $appointment = $scheduler->markDoneByParticular($me, $id);

            return $this->json([
                'ok' => true,
                'appointmentId' => $appointment->getId(),
                'status' => $appointment->getStatus(),
            ]);
        } catch (\Throwable $e) {
            return $this->json(['ok' => false, 'message' => $e->getMessage()], 400);
        }
    }
}