<?php

namespace App\Controller\Web;

use App\Entity\Application;
use App\Entity\Calendrier;
use App\Entity\User;
use App\Repository\ApplicationRepository;
use App\Repository\CalendrierRepository;
use App\Repository\JobRepository;
use App\Service\EmailVerifierService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/calendar')]
class CalendarController extends AbstractController
{
    #[Route('', name: 'calendar_index', methods: ['GET'])]
    public function index(
        JobRepository $jobRepository,
        CalendrierRepository $calendrierRepository
    ): Response {
        /** @var User|null $author */
        $author = $this->getUser();

        if (!$author instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('calendar/index.html.twig', [
            'jobsWithApplications' => $jobRepository->findJobsWithApplicationsForAuthor($author),
            'statusStats' => $calendrierRepository->getStatusStatsForAuthor($author),
            'eventsFeedUrl' => $this->generateUrl('calendar_events_feed'),
            'createDropUrl' => $this->generateUrl('calendar_create_drop'),
        ]);
    }

    #[Route('/events-feed', name: 'calendar_events_feed', methods: ['GET'])]
    public function feed(Request $request, CalendrierRepository $calendrierRepository): JsonResponse
    {
        /** @var User|null $author */
        $author = $this->getUser();

        if (!$author instanceof User) {
            return $this->json([], 403);
        }

        $start = new \DateTimeImmutable($request->query->get('start', 'now'));
        $end = new \DateTimeImmutable($request->query->get('end', '+1 month'));

        $items = $calendrierRepository->findBetweenForAuthor($author, $start, $end);

        $events = [];
        foreach ($items as $item) {
            $status = $item->getStatus();

            $talent = $item->getTalent();
            $talentName = $talent?->getPersonalProfile()?->getFullName() ?: $talent?->getEmail() ?: 'Talent';

            $events[] = [
                'id' => $item->getId(),
                'title' => sprintf('%s • %s', $talentName, $item->getJob()?->getTitle() ?? 'Mission'),
                'start' => $item->getStartAt()?->format(\DateTimeInterface::ATOM),
                'end' => $item->getEndAt()?->format(\DateTimeInterface::ATOM),
                'backgroundColor' => match ($status) {
                    Calendrier::STATUS_CONFIRMED => '#16a34a',
                    Calendrier::STATUS_REFUSED => '#dc2626',
                    Calendrier::STATUS_DONE => '#0ea5e9',
                    Calendrier::STATUS_CANCELED => '#64748b',
                    default => '#f59e0b',
                },
                'extendedProps' => [
                    'status' => $status,
                    'type' => $item->getType(),
                    'location' => $item->getLocation(),
                    'notes' => $item->getNotes(),
                    'jobTitle' => $item->getJob()?->getTitle(),
                    'candidateName' => $talentName,
                    'talentName' => $talentName,
                ],
            ];
        }

        return $this->json($events);
    }

    #[Route('/create-from-drop', name: 'calendar_create_drop', methods: ['POST'])]
    public function createFromDrop(
        Request $request,
        ApplicationRepository $applicationRepository,
        EntityManagerInterface $entityManager,
        EmailVerifierService $emailVerifierService
    ): JsonResponse {
        /** @var User|null $author */
        $author = $this->getUser();

        if (!$author instanceof User) {
            return $this->json(['ok' => false, 'message' => 'Accès refusé.'], 403);
        }

        $payload = json_decode($request->getContent(), true) ?? [];

        $applicationId = (int) ($payload['applicationId'] ?? 0);
        $startAtRaw = (string) ($payload['startAt'] ?? '');
        $durationMinutes = max(15, (int) ($payload['durationMinutes'] ?? 30));
        $type = (string) ($payload['type'] ?? Calendrier::TYPE_INTERVIEW);
        $location = isset($payload['location']) ? trim((string) $payload['location']) : null;
        $notes = isset($payload['notes']) ? trim((string) $payload['notes']) : null;

        /** @var Application|null $application */
        $application = $applicationRepository->find($applicationId);

        if (!$application) {
            return $this->json(['ok' => false, 'message' => 'Candidature introuvable.'], 404);
        }

        $job = $application->getJob();
        $talent = $application->getUser();

        if (!$job || !$talent) {
            return $this->json(['ok' => false, 'message' => 'Données invalides.'], 400);
        }

        if ($job->getCreatedBy()?->getId() !== $author->getId()) {
            return $this->json(['ok' => false, 'message' => 'Vous ne pouvez planifier que sur vos propres missions.'], 403);
        }

        try {
            $startAt = new \DateTimeImmutable($startAtRaw);
        } catch (\Throwable) {
            return $this->json(['ok' => false, 'message' => 'Date invalide.'], 400);
        }

        $rdv = new Calendrier();
        $rdv->setAuthor($author);
        $rdv->setTalent($talent);
        $rdv->setJob($job);
        $rdv->setApplication($application);
        $rdv->setStartAt($startAt);
        $rdv->setDurationMinutes($durationMinutes);
        $rdv->setType($type);
        $rdv->setLocation($location ?: null);
        $rdv->setNotes($notes ?: null);
        $rdv->setStatus(Calendrier::STATUS_PROPOSED);

        $entityManager->persist($rdv);
        $entityManager->flush();

        // ✅ Envoi du mail après enregistrement du RDV
        // Ne casse pas la création du RDV si le mail échoue.
        $mailSent = $emailVerifierService->sendAppointmentProposal($rdv);

        return $this->json([
            'ok' => true,
            'id' => $rdv->getId(),
            'mailSent' => $mailSent,
            'message' => 'Rendez-vous enregistré avec succès.',
        ]);
    }

    #[Route('/{id}/move', name: 'calendar_move', methods: ['PATCH'])]
    public function move(
        Calendrier $calendrier,
        Request $request,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        /** @var User|null $author */
        $author = $this->getUser();

        if (!$author instanceof User || $calendrier->getAuthor()?->getId() !== $author->getId()) {
            return $this->json(['ok' => false, 'message' => 'Accès refusé.'], 403);
        }

        $payload = json_decode($request->getContent(), true) ?? [];

        try {
            $startAt = new \DateTimeImmutable((string) ($payload['startAt'] ?? ''));
        } catch (\Throwable) {
            return $this->json(['ok' => false, 'message' => 'Date invalide.'], 400);
        }

        $durationMinutes = max(15, (int) ($payload['durationMinutes'] ?? $calendrier->getDurationMinutes()));
        $location = array_key_exists('location', $payload) ? trim((string) $payload['location']) : $calendrier->getLocation();
        $notes = array_key_exists('notes', $payload) ? trim((string) $payload['notes']) : $calendrier->getNotes();
        $type = (string) ($payload['type'] ?? $calendrier->getType());

        $calendrier
            ->setStartAt($startAt)
            ->setDurationMinutes($durationMinutes)
            ->setLocation($location ?: null)
            ->setNotes($notes ?: null)
            ->setType($type)
            ->touch();

        $entityManager->flush();

        return $this->json(['ok' => true, 'message' => 'Rendez-vous mis à jour.']);
    }

    #[Route('/{id}/cancel', name: 'calendar_cancel', methods: ['POST'])]
    public function cancel(
        Calendrier $calendrier,
        Request $request,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        /** @var User|null $author */
        $author = $this->getUser();

        if (!$author instanceof User || $calendrier->getAuthor()?->getId() !== $author->getId()) {
            return $this->json(['ok' => false, 'message' => 'Accès refusé.'], 403);
        }

        $payload = json_decode($request->getContent(), true) ?? [];
        $reason = isset($payload['reason']) ? trim((string) $payload['reason']) : null;

        $calendrier
            ->setStatus(Calendrier::STATUS_CANCELED)
            ->setCancelReason($reason ?: null)
            ->touch();

        $entityManager->flush();

        return $this->json(['ok' => true, 'message' => 'Rendez-vous annulé.']);
    }

    #[Route('/{id}/done', name: 'calendar_done', methods: ['POST'])]
    public function done(
        Calendrier $calendrier,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        /** @var User|null $author */
        $author = $this->getUser();

        if (!$author instanceof User || $calendrier->getAuthor()?->getId() !== $author->getId()) {
            return $this->json(['ok' => false, 'message' => 'Accès refusé.'], 403);
        }

        $calendrier
            ->setStatus(Calendrier::STATUS_DONE)
            ->touch();

        $entityManager->flush();

        return $this->json(['ok' => true, 'message' => 'Rendez-vous terminé.']);
    }
}