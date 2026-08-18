<?php

namespace App\Controller\Api\ResetPassword;

use App\Service\ResetPasswordService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class ResetController extends AbstractController
{
    #[Route('/api/reset-password/{token}', name: 'api_reset_password', methods: ['GET'])]
    public function __invoke(string $token, ResetPasswordService $resetPasswordService): JsonResponse
    {
        $result = $resetPasswordService->getApiResetPagePayload($token);

        return $this->json($result, $result['ok'] ? 200 : 400);
    }
}