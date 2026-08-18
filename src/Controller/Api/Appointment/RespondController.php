<?php

namespace App\Controller\Api\Appointment;

use App\Entity\Appointment;
use App\Entity\User;
use App\Service\ApplicantCalendarService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class RespondController extends AbstractController
{
    #[Route('/api/appointment/{id}/respond', name: 'api_appointment_respond', methods: ['POST'])]
    public function __invoke(
        Appointment $appointment,
        Request $request,
        ApplicantCalendarService $calendarService
    ): JsonResponse {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        /** @var User $user */
        $user = $this->getUser();

        $payload = json_decode($request->getContent(), true) ?? [];
        $action = $payload['action'] ?? null;

        try {
            return $this->json(
                $calendarService->respondToAppointment($appointment, $user, $action)
            );
        } catch (AccessDeniedHttpException $exception) {
            return $this->json([
                'ok' => false,
                'message' => $exception->getMessage(),
            ], 403);
        } catch (BadRequestHttpException $exception) {
            return $this->json([
                'ok' => false,
                'message' => $exception->getMessage(),
            ], 400);
        }
    }
}