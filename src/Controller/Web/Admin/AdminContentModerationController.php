<?php

namespace App\Controller\Web\Admin;

use App\Service\AdminContentModerationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin', name: 'admin_')]
class AdminContentModerationController extends AbstractController
{
    #[Route('/content/moderation', name: 'content_moderation_index', methods: ['GET'])]
    public function queue(AdminContentModerationService $moderationService): Response
    {
        $this->denyUnlessModeratorAdminOrSuperAdmin();

        return $this->render('admin/moderation/queue.html.twig', $moderationService->getQueueData());
    }

    #[Route('/keywords', name: 'keywords_index', methods: ['GET'])]
    public function keywords(): Response
    {
        $this->denyUnlessModeratorAdminOrSuperAdmin();

        return $this->render('admin/moderation/keywords.html.twig', [
            'keywords' => [],
        ]);
    }

    #[Route('/spam', name: 'spam_index', methods: ['GET'])]
    public function spam(): Response
    {
        $this->denyUnlessModeratorAdminOrSuperAdmin();

        return $this->render('admin/moderation/spam.html.twig', [
            'rules' => [],
        ]);
    }

    #[Route('/bans', name: 'bans_index', methods: ['GET'])]
    public function bans(): Response
    {
        $this->denyUnlessAdminOrSuperAdmin();

        return $this->render('admin/moderation/bans.html.twig', [
            'bans' => [],
        ]);
    }

    #[Route('/analytics/geo', name: 'geo_analytics_index', methods: ['GET'])]
    public function geoAnalyticsIndex(): Response
    {
        $this->addFlash('info', 'Le module Géo Analytics sera branché ici.');
        return $this->redirectToRoute('tableau_de_bord');
    }

    #[Route('/companies/pending', name: 'companies_pending_index', methods: ['GET'])]
    public function companiesPendingIndex(): Response
    {
        $this->addFlash('info', 'La file KYC / entreprises en attente sera branchée ici.');
        return $this->redirectToRoute('tableau_de_bord');
    }

    #[Route('/payments/failures', name: 'payments_failures_index', methods: ['GET'])]
    public function paymentsFailuresIndex(): Response
    {
        $this->addFlash('info', 'La liste des paiements échoués sera branchée ici.');
        return $this->redirectToRoute('tableau_de_bord');
    }

    #[Route('/payouts', name: 'payouts_index', methods: ['GET'])]
    public function payoutsIndex(): Response
    {
        $this->addFlash('info', 'La file des reversements sera branchée ici.');
        return $this->redirectToRoute('tableau_de_bord');
    }

    #[Route('/infra/services', name: 'infra_services_index', methods: ['GET'])]
    public function infraServicesIndex(): Response
    {
        $this->addFlash('info', 'Le monitoring des services sera branché ici.');
        return $this->redirectToRoute('tableau_de_bord');
    }

    #[Route('/search/global', name: 'search_global', methods: ['GET'])]
    public function globalSearch(Request $request): JsonResponse
    {
        $q = trim((string) $request->query->get('q', ''));

        if (mb_strlen($q) < 2) {
            return $this->json([
                'users' => [],
                'companies' => [],
                'jobs' => [],
                'transactions' => [],
                'tickets' => [],
            ]);
        }

        return $this->json([
            'users' => [],
            'companies' => [],
            'jobs' => [],
            'transactions' => [],
            'tickets' => [],
        ]);
    }

    private function denyUnlessModeratorAdminOrSuperAdmin(): void
    {
        if (
            !$this->isGranted('ROLE_MODERATEUR')
            && !$this->isGranted('ROLE_ADMIN')
            && !$this->isGranted('ROLE_SUPER_ADMIN')
        ) {
            throw $this->createAccessDeniedException('Accès refusé.');
        }
    }

    private function denyUnlessAdminOrSuperAdmin(): void
    {
        if (
            !$this->isGranted('ROLE_ADMIN')
            && !$this->isGranted('ROLE_SUPER_ADMIN')
        ) {
            throw $this->createAccessDeniedException('Accès refusé.');
        }
    }
}