<?php

namespace App\Controller\Api\ApplicantCalendar;

use App\Entity\User;
use App\Service\ApplicantCalendarService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class FeedController extends AbstractController
{
    #[Route('/api/applicant/calendar/feed', name: 'api_applicant_calendar_feed', methods: ['GET'])]
    public function __invoke(Request $request, ApplicantCalendarService $calendarService): JsonResponse
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        /** @var User $user */
        $user = $this->getUser();

        $start = $request->query->get('start');
        $end = $request->query->get('end');

        try {
            $startAt = $start ? new \DateTimeImmutable($start) : null;
            $endAt = $end ? new \DateTimeImmutable($end) : null;
        } catch (\Exception) {
            return $this->json([
                'ok' => false,
                'message' => 'Paramètres de date invalides.',
            ], 400);
        }

        return $this->json([
            'ok' => true,
            'events' => $calendarService->getFeedEvents($user, $startAt, $endAt),
        ]);
    }
}