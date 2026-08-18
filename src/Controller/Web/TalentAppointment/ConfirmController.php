<?php

namespace App\Controller\Web\TalentAppointment;

use App\Entity\User;
use App\Service\TalentAppointmentService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/talent/appointments', name: 'talent_appointments_')]
final class ConfirmController extends AbstractController
{
    #[Route('/{id}/confirm', name: 'confirm', methods: ['POST'])]
    public function __invoke(int $id, TalentAppointmentService $talentAppointmentService): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var User $me */
        $me = $this->getUser();

        try {
            return $this->json(
                $talentAppointmentService->confirm($me, $id)
            );
        } catch (\Throwable $exception) {
            return $this->json([
                'ok' => false,
                'message' => $exception->getMessage(),
            ], 400);
        }
    }
}