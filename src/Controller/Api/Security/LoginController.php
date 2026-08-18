<?php

namespace App\Controller\Api\Security;

use App\Service\SecurityService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

final class LoginController extends AbstractController
{
    #[Route('/api/login', name: 'api_login', methods: ['GET'])]
    public function __invoke(
        AuthenticationUtils $authenticationUtils,
        SecurityService $securityService
    ): JsonResponse {
        $data = $securityService->getApiLoginPayload(
            $this->getUser(),
            $authenticationUtils->getLastUsername(),
            $authenticationUtils->getLastAuthenticationError()
        );

        return $this->json(
            $data,
            $data['isAuthenticated'] ? 200 : 401
        );
    }
}