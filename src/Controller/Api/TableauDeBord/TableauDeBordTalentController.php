<?php

namespace App\Controller\Api\TableauDeBord;

use App\Entity\User;
use App\Service\TableauDeBord\TalentDashboardService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER', message: 'Accès refusé. Connectez-vous')]
class TableauDeBordTalentController extends AbstractController
{
    #[Route('/api/tableau-de-bord-talent', name: 'api_tableau_de_bord_talent', methods: ['GET'])]
    public function index(TalentDashboardService $dashboardService): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json([
                'success' => false,
                'message' => 'Utilisateur non authentifié.',
            ], 401);
        }

        $data = $dashboardService->getDashboardData($user);

        if (($data['hasProfile'] ?? false) === false) {
            return $this->json([
                'success' => false,
                'message' => 'Profil professionnel introuvable.',
                'redirectRoute' => $data['redirectRoute'] ?? 'profile_edit',
            ], 422);
        }

        return $this->json([
            'success' => true,
            'data' => $this->normalizeDashboardData($data),
        ]);
    }

    private function normalizeDashboardData(array $data): array
    {
        return [
            'pendingAppointmentsCount' => $data['pendingAppointmentsCount'] ?? 0,
            'openPaymentDisputesCount' => $data['openPaymentDisputesCount'] ?? 0,
            'activeSanctionsCount' => $data['activeSanctionsCount'] ?? 0,

            'applicationsCount' => $data['applicationsCount'] ?? 0,
            'favoritesCount' => $data['favoritesCount'] ?? 0,
            'favoritedByCount' => $data['favoritedByCount'] ?? 0,
            'unreadCount' => $data['unreadCount'] ?? 0,

            'totalViews' => $data['totalViews'] ?? 0,
            'uniqueViews' => $data['uniqueViews'] ?? 0,

            'totalPaid' => $data['totalPaid'] ?? 0,
            'paymentsCount' => $data['paymentsCount'] ?? 0,
            'payments' => $data['payments'] ?? [],
            'invoices' => $data['invoices'] ?? [],

            'avgRating' => $data['avgRating'] ?? 0,
            'voters' => $data['voters'] ?? 0,
            'profileCompletion' => $data['profileCompletion'] ?? 0,

            'kpi' => $data['kpi'] ?? [],
            'tables' => $data['tables'] ?? [],

            'totalApplicantsOnMyJobs' => $data['totalApplicantsOnMyJobs'] ?? 0,
            'remainingApplicantsOnMyJobs' => $data['remainingApplicantsOnMyJobs'] ?? 0,
        ];
    }
}