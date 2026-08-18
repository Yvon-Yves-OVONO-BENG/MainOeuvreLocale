<?php

namespace App\Controller\Web\TableauDeBord;

use App\Repository\JobRepository;
use App\Repository\PaymentRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_SUPER_ADMIN', message: 'Accès refusé. Connectez-vous')]
class TableauDeBordSuperAdministrateurController extends AbstractController
{
    /**
     * Tableau de bord Super Admin
     * - Ne contient AUCUNE requête DQL/QueryBuilder
     * - Toute la data est récupérée via des méthodes de Repository dédiées
     */
    #[Route('/tableau-de-bord-super-administrateur', name: 'tableau_de_bord_super_administrateur')]
    public function index(
        UserRepository $userRepo,
        JobRepository $jobRepo,
        PaymentRepository $paymentRepo,
        ?\App\Repository\ReportRepository $reportRepo = null, // optionnel si tu as l'entité Report
    ): Response {
        // ===== KPI =====
        $totalUsers     = $userRepo->countAll();
        $totalTalents   = $userRepo->countTalentsWithProfessionalProfile(); // talens = users avec professionalProfile
        $totalJobs      = $jobRepo->countAll();
        $totalRevenue   = $paymentRepo->sumAllRevenue(); // SUM(p.amount)
        $activeReports  = $reportRepo ? $reportRepo->countOpenReports('OPEN') : 0;

        // ===== TABLES =====
        $latestUsers    = $userRepo->findLatest(10);
        $latestPayments = $paymentRepo->findLatest(10);

        // ===== GRAPHIQUES =====
        $signupsByMonth = $userRepo->countSignupsByMonth(12);

        // split roles en PHP (mais les données viennent d’une méthode repo)
        $allUsersRoles = $userRepo->findAllUserRoles();
        $countCompany = 0; 
        $countTalent = 0; 
        $countParticulier = 0;
        $countAdmin = 0;

        foreach ($allUsersRoles as $row) {
            $roles = $row['roles'] ?? [];
            if (in_array('ROLE_COMPANY', $roles, true)) $countCompany++;
            elseif (in_array('ROLE_TALENT', $roles, true)) $countTalent++;
            elseif (in_array('ROLE_PARTICULIER', $roles, true)) $countParticulier++;
            elseif (in_array('ROLE_ADMIN', $roles, true)) $countAdmin++;
        }

        // Dans ton entité Job, status est une relation ManyToOne vers StatusJob
        // => on compte par "code" (ou "name") du status selon ton entité StatusJob.
        // Ici je suppose un champ "code" = 'RECRUITED'. Adapte si tu as "name" ou "label".
        $jobsRecruited = $jobRepo->countByStatusLabel('RECRUITED'); // adapte la valeur exacte


        return $this->render('tableau_de_bord_super_administrateur/tableau_de_bord_super_administrateur.html.twig', [
            'kpi' => [
                'totalUsers'     => $totalUsers,
                'totalCompanies' => 0, // laissé à 0 car ton code company est commenté
                'totalTalents'   => $totalTalents,
                'totalJobs'      => $totalJobs,
                'totalRevenue'   => $totalRevenue,
                'activeReports'  => $activeReports,
            ],
            'latestUsers'      => $latestUsers,
            'pendingCompanies' => [],
            'latestPayments'   => $latestPayments,
            'charts' => [
                'signupsByMonth' => $signupsByMonth,
                'usersSplit' => [
                    'company'     => $countCompany,
                    'talent'      => $countTalent,
                    'particulier' => $countParticulier,
                ],
                'jobsVsRecruits' => [
                    'jobs'      => $totalJobs,
                    'recruited' => $jobsRecruited,
                ],
            ],
        ]);
    }
}
