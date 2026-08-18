<?php

namespace App\Controller\Api\Security;

use App\Service\SecurityService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class LogoutController extends AbstractController
{
    #[Route('/api/logout', name: 'api_logout', methods: ['GET', 'POST'])]
    public function __invoke(SecurityService $securityService): JsonResponse
    {
        return $this->json(
            $securityService->getLogoutInterceptPayload()
        );
    }
}