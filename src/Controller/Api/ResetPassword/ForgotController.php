<?php

namespace App\Controller\Api\ResetPassword;

use App\Service\ResetPasswordService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class ForgotController extends AbstractController
{
    #[Route('/api/forgot-password', name: 'api_forgot_password', methods: ['GET'])]
    public function __invoke(ResetPasswordService $resetPasswordService): JsonResponse
    {
        return $this->json(
            $resetPasswordService->getApiForgotPagePayload()
        );
    }
}