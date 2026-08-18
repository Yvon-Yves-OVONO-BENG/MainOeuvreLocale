<?php

namespace App\Controller\Web\ApplicantCalendar;

use App\Entity\User;
use App\Service\ApplicantCalendarService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class IndexController extends AbstractController
{
    #[Route('/applicant/calendar', name: 'applicant_calendar_index', methods: ['GET'])]
    public function __invoke(ApplicantCalendarService $calendarService): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        /** @var User $user */
        $user = $this->getUser();

        return $this->render(
            'applicant_calendar/applicant_calendar.html.twig',
            $calendarService->getCalendarPageData($user)
        );
    }
}