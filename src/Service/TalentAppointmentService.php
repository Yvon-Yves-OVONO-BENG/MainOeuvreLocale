<?php

namespace App\Service;

use App\Entity\User;

class TalentAppointmentService
{
    public function __construct(
        private readonly AppointmentSchedulerService $appointmentSchedulerService
    ) {
    }

    public function confirm(User $talent, int $appointmentId): array
    {
        $appointment = $this->appointmentSchedulerService->confirmByTalent($talent, $appointmentId);

        return [
            'ok' => true,
            'appointmentId' => $appointment->getId(),
            'status' => $appointment->getStatus(),
        ];
    }

    public function refuse(User $talent, int $appointmentId, ?string $reason = null): array
    {
        $appointment = $this->appointmentSchedulerService->refuseByTalent(
            talent: $talent,
            appointmentId: $appointmentId,
            reason: $reason !== null ? trim($reason) : null
        );

        return [
            'ok' => true,
            'appointmentId' => $appointment->getId(),
            'status' => $appointment->getStatus(),
        ];
    }
}