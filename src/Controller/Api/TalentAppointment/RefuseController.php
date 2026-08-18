<?php

namespace App\Controller\Api\TalentAppointment;

use App\Entity\User;
use App\Service\TalentAppointmentService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/talent/appointments', name: 'api_talent_appointments_')]
final class RefuseController extends AbstractController
{
    #[Route('/{id}/refuse', name: 'refuse', methods: ['POST'])]
    public function __invoke(
        int $id,
        Request $request,
        TalentAppointmentService $talentAppointmentService
    ): JsonResponse {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var User $me */
        $me = $this->getUser();

        $payload = json_decode($request->getContent(), true) ?: [];
        $reason = isset($payload['reason']) ? trim((string) $payload['reason']) : null;

        try {
            return $this->json(
                $talentAppointmentService->refuse($me, $id, $reason)
            );
        } catch (\Throwable $exception) {
            return $this->json([
                'ok' => false,
                'message' => $exception->getMessage(),
            ], 400);
        }
    }
}