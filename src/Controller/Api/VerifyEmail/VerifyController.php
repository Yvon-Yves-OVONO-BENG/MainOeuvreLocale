<?php

namespace App\Controller\Api\VerifyEmail;

use App\Service\VerifyEmailService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class VerifyController extends AbstractController
{
    #[Route('/api/verify-email/{token}', name: 'api_verify_email', methods: ['GET'])]
    public function __invoke(string $token, VerifyEmailService $verifyEmailService): JsonResponse
    {
        $result = $verifyEmailService->verifyForApi($token);

        return $this->json(
            $result,
            $result['ok'] ? 200 : 400
        );
    }
}