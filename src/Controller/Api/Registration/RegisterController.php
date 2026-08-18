<?php

namespace App\Controller\Api\Registration;

use App\Service\RegistrationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class RegisterController extends AbstractController
{
    #[Route('/api/register', name: 'api_register', methods: ['GET'])]
    public function __invoke(RegistrationService $registrationService): JsonResponse
    {
        return $this->json(
            $registrationService->getApiRegisterPagePayload()
        );
    }
}