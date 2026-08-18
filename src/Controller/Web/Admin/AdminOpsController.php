<?php

namespace App\Controller\Web\Admin;

use App\Service\AdminOpsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/ops', name: 'admin_ops_')]
#[IsGranted('ROLE_ADMIN')]
final class AdminOpsController extends AbstractController
{
    public function __construct(
        private readonly AdminOpsService $adminOpsService,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/ops/ops.html.twig', [
            'ops' => $this->adminOpsService->getDashboardData(),
        ]);
    }

    #[Route('/status', name: 'status', methods: ['GET'])]
    public function status(): JsonResponse
    {
        return $this->json([
            'ok' => true,
            'data' => $this->adminOpsService->getDashboardData(),
        ]);
    }

    #[Route('/action/{action}', name: 'action', methods: ['POST'])]
    public function action(string $action, Request $request): JsonResponse
    {
        $token = (string) ($request->headers->get('X-CSRF-TOKEN') ?? '');

        if (!$this->isCsrfTokenValid('admin_ops_action', $token)) {
            return $this->json([
                'ok' => false,
                'message' => 'Jeton CSRF invalide.',
            ], Response::HTTP_FORBIDDEN);
        }

        $userIdentifier = $this->getUser()?->getUserIdentifier() ?? 'unknown';

        try {
            $result = match ($action) {
                'reindex_search' => $this->adminOpsService->reindexSearch($userIdentifier),
                'restart_notifications_queue' => $this->adminOpsService->restartNotificationsQueue($userIdentifier),
                'retry_webhooks' => $this->adminOpsService->retryFailedWebhooks($userIdentifier),
                'toggle_maintenance' => $this->adminOpsService->toggleMaintenance($userIdentifier),
                default => null,
            };

            if ($result === null) {
                return $this->json([
                    'ok' => false,
                    'message' => 'Action inconnue.',
                ], Response::HTTP_NOT_FOUND);
            }

            return $this->json([
                'ok' => true,
                'message' => $result['message'],
                'data' => $this->adminOpsService->getDashboardData(),
            ]);
        } catch (\Throwable $e) {
            return $this->json([
                'ok' => false,
                'message' => 'Une erreur est survenue pendant l’opération.',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}