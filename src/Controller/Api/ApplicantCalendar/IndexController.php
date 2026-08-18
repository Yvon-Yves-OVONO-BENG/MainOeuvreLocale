<?php

namespace App\Controller\Api\ApplicantCalendar;

use App\Entity\User;
use App\Service\ApplicantCalendarService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class IndexController extends AbstractController
{
    #[Route('/api/applicant/calendar', name: 'api_applicant_calendar_index', methods: ['GET'])]
    public function __invoke(ApplicantCalendarService $calendarService): JsonResponse
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        /** @var User $user */
        $user = $this->getUser();

        return $this->json($calendarService->getApiCalendarPayload($user));
    }
}