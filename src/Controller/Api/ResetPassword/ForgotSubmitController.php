<?php

namespace App\Controller\Api\ResetPassword;

use App\Service\ResetPasswordService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class ForgotSubmitController extends AbstractController
{
    #[Route('/api/forgot-password', name: 'api_forgot_password_submit', methods: ['POST'])]
    public function __invoke(Request $request, ResetPasswordService $resetPasswordService): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        $payload = is_array($payload) ? $payload : $request->request->all();

        $email = trim((string) ($payload['email'] ?? ''));
        $csrfToken = $payload['csrfToken'] ?? null;

        $result = $resetPasswordService->requestPasswordReset($email, $csrfToken);

        return $this->json([
            'ok' => $result['ok'],
            'message' => $result['message'],
            'redirect' => [
                'route' => $result['routeName'],
                'params' => $result['routeParams'],
            ],
        ], $result['ok'] ? 200 : 400);
    }
}