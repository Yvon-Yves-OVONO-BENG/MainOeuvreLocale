<?php

namespace App\Controller\Web\TableauDeBord;

use App\Entity\CompanyKycCase;
use App\Repository\ApiEndpointMetricRepository;
use App\Repository\CompanyKycCaseRepository;
use App\Repository\AdminAuditLogRepository;
use App\Repository\ApiLatencyLogRepository;
use App\Repository\ApplicationRepository;
use App\Repository\BackupSnapshotRepository;
use App\Repository\JobRepository;
use App\Repository\KycRuleRepository;
use App\Repository\PaymentDisputeRepository;
use App\Repository\PaymentRepository;
use App\Repository\PersonalProfileRepository;
use App\Repository\ProfessionalProfileRepository;
use App\Repository\ReportJobRepository;
use App\Repository\ReportRepository;
use App\Repository\SanctionRepository;
use App\Repository\SecurityEventRepository;
use App\Repository\UptimeCheckRepository;
use App\Repository\UserLogRepository;
use App\Repository\UserRepository;
use App\Repository\CategorieRepository;
use App\Repository\ProfessionRepository;
use App\Repository\UserTwoFactorRepository;
use App\Repository\WebhookDeliveryRepository;
use App\Service\AdminSecurityScoreService;
use App\Service\MaintenanceService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;


#[IsGranted('ROLE_USER', message: 'Accès refusé. Connectez-vous')]
class TableauDeBordAdministrateurController extends AbstractController
{
    private const STAFF_ROLES = [
        'ROLE_ADMIN',
        'ROLE_MODERATOR',
        'ROLE_SUPER_ADMIN',
    ];

    #[Route('/tableau-de-bord-administrateur', name: 'tableau_de_bord_administrateur')]
    public function index(
        EntityManagerInterface $em,
        UserRepository $userRepo,
        ProfessionalProfileRepository $proRepo,
        KycRuleRepository $kycRuleRepository,
        UserRepository $userRepository,
        JobRepository $jobRepository,
        PersonalProfileRepository $personalProfileRepository,
        ProfessionalProfileRepository $professionalProfileRepository,
        SanctionRepository $sanctionRepository,
        PaymentRepository $paymentRepository,
        UptimeCheckRepository $uptimeCheckRepository,
        ApiLatencyLogRepository $apiLatencyLogRepository,
        AdminSecurityScoreService $adminSecurityScoreService,
        PaymentDisputeRepository $paymentDisputeRepository,
        ReportRepository $reportRepository,
        ReportJobRepository $reportJobRepository,
        MaintenanceService $maintenanceService,
        ApplicationRepository $applicationRepository,
        UserLogRepository $userLogRepository,
        SecurityEventRepository $securityEventRepository,
        UserTwoFactorRepository $userTwoFactorRepository,
        CompanyKycCaseRepository $companyKycCaseRepository,
        AdminAuditLogRepository $adminAuditLogRepository,
        WebhookDeliveryRepository $webhookDeliveryRepository,
        BackupSnapshotRepository $backupSnapshotRepository,
        ApiEndpointMetricRepository $apiEndpointMetricRepository,
        CategorieRepository $categoriesRepository,
        ProfessionRepository $professionRepository,
        ?ReportRepository $reportRepo = null,
    ): Response
    {

        $this->denyUnlessAdminOrSuperAdmin();

        // ===== KPI =====
        $activeAccounts  = $userRepo->countByActive(true);
        $blockedAccounts = $userRepo->countByActive(false);

        // Offres actives : géré dans le repository selon les champs existants (isActive / status)
        $activeJobs = $jobRepository->countActiveJobs($em);

        // Signalements OPEN (Report.status = 'open')
        $pendingReports = $reportRepo ? $reportRepo->countOpenReports() : 0;

        // ===== TABLES =====
        $latestUsers = $userRepo->findLatestUsers(5);
        $pendingProProfiles = $proRepo->findPendingVerification(10);

        // Offres “signalées” :
        // - soit via Job.isReported (si ce champ existe)
        // - soit via Report -> targetJob (mais ATTENTION : ton Report actuel ne l’a PAS)
        $flaggedJobs = $jobRepository->findFlaggedJobs($em, 10);

        // ===== Graph : inscriptions 30 jours =====
        $signups30d = $userRepo->countSignupsByDayLastDays(30);

        $now = new \DateTimeImmutable();

        $allowedUserRoles = ['ROLE_TALENT', 'ROLE_PARTICULIER', 'ROLE_COMPANY'];

        $userMonthStats = $userRepo->getCurrentMonthRegistrationStatsForRoles($allowedUserRoles);

        $onlineSince = (new \DateTimeImmutable())->sub(new \DateInterval('PT10M'));
        $signupsSince = $now->sub(new \DateInterval('PT24H'));
        $since7d = $now->sub(new \DateInterval('P7D'));
        
        $onlineUsers   = $userLogRepository->countOnlineUsersSince($onlineSince);
        $signups24h    = $userRepo->countSignupsSince($signupsSince);
        $incidentsOpen = $reportRepo->countByStatuses(['open','investigating']);
        $resolutionRate = $reportRepo->countByStatuses(['resolved','investigating']);
        //$res           = $reportRepo->getResolutionRateSince($since7d);
        $activeJobs = $jobRepository->countActiveJobs();
        $pendingModerationJobs = $jobRepository->count(['moderationStatus' => 'pending']);
        
        $rules = $kycRuleRepository->findAllOrdered();

        $pendingCni = $personalProfileRepository->countPendingCni();
        $pendingProfessional = $professionalProfileRepository->countUnverifiedProfiles();

        $sanctions = $sanctionRepository->findAll();

        $paymentsToday = $paymentRepository->countPaymentsToday();

        $mrrStats = $paymentRepository->getMrrStats();

        $gmvStats = $paymentRepository->getGmvStats();

        $jobMonthStats = $jobRepository->getCurrentMonthJobsStats();

        $realUptime = $uptimeCheckRepository->getUptimePercentLastDays(30);
        $realLatency = $uptimeCheckRepository->getAverageLatencyLastHours(24);

        $realApiLatency = $apiLatencyLogRepository->getAverageLatencyLastHours(24);

        $securityPosture = $adminSecurityScoreService->build();

        $alertsData = $this->buildAlertsData(
            $paymentDisputeRepository,
            $reportRepository,
            $reportJobRepository,
            $kycRuleRepository
        );
        
        $growthStats = $userRepository->getGrowthStatsAllowedRoles();

        $signupChartData = [
            '30' => $userRepository->getSignupChart30DaysAllowedRoles(),
            '90' => $userRepository->getSignupChart90DaysAllowedRoles(),
            '12' => $userRepository->getSignupChartCurrentYearToCurrentMonthAllowedRoles(),
        ];

        $charts = $charts ?? [];
        $charts['usersSplit'] = $userRepository->getUsersSplitStats();

        $moderationChart = $this->buildModerationChart(
            $reportRepository->countOpenGroupedByCategory(),
            $reportJobRepository->countPendingGroupedByCategory()
        );
        
        $topConnectedCities = $userLogRepository->getTopConnectedCities(10);

        $latestPaymentDisputes = $paymentDisputeRepository->findLatestOpenAndReviewDisputes(5);

        $securityEvents = array_map(function (\App\Entity\PaymentDispute $dispute) {
            $level = match ($dispute->getPriority()) {
                \App\Entity\PaymentDispute::PRIORITY_HIGH => 'critical',
                \App\Entity\PaymentDispute::PRIORITY_MEDIUM => 'warning',
                default => 'info',
            };

            $statusLabel = match ($dispute->getStatus()) {
                \App\Entity\PaymentDispute::STATUS_OPEN => 'Open',
                \App\Entity\PaymentDispute::STATUS_REVIEW => 'En revue',
                default => ucfirst($dispute->getStatus()),
            };

            $userEmail = $dispute->getUser()?->getEmail() ?? 'Utilisateur inconnu';
            $amount = $dispute->getPayment()?->getAmount() ?? '0';
            $currency = $dispute->getPayment()?->getCurrency() ?? 'XAF';
            $priority = ucfirst($dispute->getPriority());

            return [
                'level' => $level,
                'title' => 'Litige paiement #' . $dispute->getId(),
                'meta' => sprintf(
                    '%s • Priorité %s • %s %s • %s',
                    $statusLabel,
                    $priority,
                    $amount,
                    $currency,
                    $userEmail
                ),
            ];
        }, $latestPaymentDisputes);

        ////////
        $totalCompanies = $userRepository->countUsersHavingRole('ROLE_COMPANY');
        $approvedCompanies = $companyKycCaseRepository->countApprovedCompanies();
        $pendingCompanies = $companyKycCaseRepository->countPendingCompanies();

        $totalStaff = $userRepository->countUsersHavingAnyRole(self::STAFF_ROLES);
        $staff2faEnabled = $userTwoFactorRepository->countEnabledForAnyUserRole(self::STAFF_ROLES);

        $twoFactorRate = $totalStaff > 0 ? (int) round(($staff2faEnabled * 100) / $totalStaff) : 0;
        $kycRate = $totalCompanies > 0 ? (int) round(($approvedCompanies * 100) / $totalCompanies) : 0;

        $logStats = $adminAuditLogRepository->getSensitiveCoverageRate(30);
        $webhookStats = $webhookDeliveryRepository->getSignatureHealth(30);
        $backupStats = $backupSnapshotRepository->getEncryptionHealth(30);

        $complianceChecklist = [
            [
                'label' => '2FA staff',
                'value' => $twoFactorRate . '%',
                'ok' => $twoFactorRate >= 80,
            ],
            [
                'label' => 'KYC entreprises',
                'value' => $kycRate . '%',
                'ok' => $kycRate >= 75,
            ],
            [
                'label' => 'Logs sensibles',
                'value' => $logStats['rate'] . '%',
                'ok' => $logStats['rate'] >= 80,
            ],
            [
                'label' => 'Webhooks signés',
                'value' => $webhookStats['rate'] . '%',
                'ok' => $webhookStats['rate'] >= 95,
            ],
            [
                'label' => 'Backups encryptés',
                'value' => $backupStats['allEncrypted'] ? 'Oui' : 'Non',
                'ok' => $backupStats['allEncrypted'],
            ],
        ];

        $conformites = array_map(
            static fn($event) => [
                'level' => $event->getSeverity(),
                'title' => $event->getTitle(),
                'meta' => $event->getMessage(),
            ],
            $securityEventRepository->findLatestOpenOrReview(5)
        );

        $pendingKyc = $this->buildPendingKyc($companyKycCaseRepository);

        $revenueChart = $paymentRepository->getRevenueChartLast12Months();

        $revenueRows = $paymentRepository->getRevenueChartSuccessPaymentsByMonth(12);

        $revenueLabels = array_map(
            static fn(array $row) => $row['month_label'],
            $revenueRows
        );

        $revenueValues = array_map(
            static fn(array $row) => (float) $row['total'],
            $revenueRows
        );

        $plansRows = $paymentRepository->getPlanMixSuccessPayments();

        $expectedPlans = ['Découverte', 'Pro', 'Premium'];
        $indexedPlans = [];

        foreach ($plansRows as $row) {
            $indexedPlans[$row['name']] = [
                'name' => $row['name'],
                'count' => (int) $row['count'],
                'mrr' => (float) $row['mrr'],
            ];
        }

        $plansData = [];
        foreach ($expectedPlans as $planName) {
            $plansData[] = $indexedPlans[$planName] ?? [
                'name' => $planName,
                'count' => 0,
                'mrr' => 0,
            ];
        }

        $planLabels = array_map(static fn(array $p) => $p['name'], $plansData);
        $planValues = array_map(static fn(array $p) => $p['count'], $plansData);

        $plansData = $paymentRepository->getPlansPaymentsSummary();


        $providerRows = $paymentRepository->getProviderMixSuccessPayments();

        $expectedProviders = ['MOMO', 'OM', 'CARTE BANCAIRE'];
        $indexedProviders = [];

        foreach ($providerRows as $row) {
            $indexedProviders[$row['name']] = [
                'name' => $row['name'],
                'count' => (int) $row['count'],
                'total' => (float) $row['total'],
            ];
        }

        $providerData = [];
        foreach ($expectedProviders as $providerName) {
            $providerData[] = $indexedProviders[$providerName] ?? [
                'name' => $providerName,
                'count' => 0,
                'total' => 0,
            ];
        }

        $providerLabels = array_map(static fn(array $p) => $p['name'], $providerData);
        $providerValues = array_map(static fn(array $p) => $p['count'], $providerData);

        $latestFailedPayments = array_map(
            static function (\App\Entity\Payment $payment): array {
                $status = strtoupper($payment->getSatusPayment()?->getStatusPayment() ?? 'INCONNU');

                return [
                    'id' => $payment->getId(),
                    'amount' => (float) ($payment->getAmount() ?? 0),
                    'currency' => $payment->getCurrency() ?: 'XAF',
                    'provider' => $payment->getProvider()?->getProvider() ?: '—',
                    'status' => $status,
                    'userEmail' => $payment->getUser()?->getEmail() ?: '—',
                    'plan' => $payment->getSubscription()?->getPlan()?->getPlan() ?: '—',
                    'paidAt' => $payment->getPaidAt(),
                ];
            },
            $paymentRepository->findLatestByStatuses(['ECHEC', 'ANNULE', 'EXPIRE'], 3)
        );

        $latestPayments = $paymentRepository->findLatestPayments(5);

        $userTrendChart = $userRepository->getDailyRegistrationsByMainRolesLastDays(30);

        $topApis = $apiEndpointMetricRepository->getTopEndpoints(30, 3);

        return $this->render('tableau_de_bord_administrateur/tableau_de_bord_administrateur.html.twig', [
            'topApis' => $topApis,
            'userTrendChart' => $userTrendChart,
            'latestPayments' => $latestPayments,
            'latestFailedPayments' => $latestFailedPayments,
            'providerData' => $providerData,
            'providerLabels' => $providerLabels,
            'providerValues' => $providerValues,
            'planLabels' => $planLabels,
            'planValues' => $planValues,
            'plansData' => $plansData,
            'subscriptionPlanLabels' => array_map(static fn(array $p) => $p['name'], $plansData),
            'subscriptionPlanValues' => array_map(static fn(array $p) => $p['count'], $plansData),
            'revenueLabels' => $revenueLabels,
            'revenueValues' => $revenueValues,
            'revenueChart' => $revenueChart,
            'roleCounts' => [
                'superAdmin' => $userRepository->countUsersHavingRole('ROLE_SUPER_ADMIN'),
                'admin' => $userRepository->countUsersHavingRole('ROLE_ADMIN'),
                'moderateur' => $userRepository->countUsersHavingRole('ROLE_MODERATEUR'),
                'company' => $userRepository->countUsersHavingRole('ROLE_COMPANY'),
                'particulier' => $userRepository->countUsersHavingRole('ROLE_PARTICULIER'),
                'talent' => $userRepository->countUsersHavingRole('ROLE_TALENT'),
            ],

            'pendingKyc' => $pendingKyc,
            'complianceChecklist' => $complianceChecklist,
            'conformites' => $conformites,
            'companyKycStats' => [
                'totalCompanies' => $totalCompanies,
                'approvedCompanies' => $approvedCompanies,
                'pendingCompanies' => $pendingCompanies,
                'kycRate' => $kycRate,
            ],
            'pendingKycCases' => $companyKycCaseRepository->findLatestPending(10),
            'latestSensitiveLogs' => $adminAuditLogRepository->findLatestSensitive(10),
            'webhookStats' => $webhookStats,
            'backupStats' => $backupStats,

            'recentConnections' => $userLogRepository->findLatestConnections(6),
            'securityEvents' => $securityEvents,
            'topZones' => $topConnectedCities,
            'chartsModeration' => $moderationChart,
            'chartJobVsRecruits' => [
                'jobsVsRecruits' => [
                    'jobs' => $jobRepository->countNotExpiredJobs(),
                    'recruited' => $applicationRepository->countAcceptedApplicationsForActiveJobs(),
                ],
            ],
            'activationRate' => $userRepository->getActivationRateMainRoles(),
            'retentionRate'  => $userRepository->getRetentionRateMainRoles(),
            'charts' => $charts,
            'usersSplit' => $userRepository->getUsersSplitStats(),
            'alertsData' => $alertsData,
            'kpi' => [
                'categories' => $categoriesRepository->count([]),
                'professions' => $professionRepository->count([]),
                'securityPosture' => $securityPosture,
                'latency' => $realApiLatency,
                'activeAccounts'  => $activeAccounts,
                'blockedAccounts' => $blockedAccounts,
                'activeJobs'      => $activeJobs,
                'pendingModerationJobs' => $pendingModerationJobs,
                'pendingReports'  => $pendingReports,
                'onlineUsers' => $onlineUsers,
                'signups24h' => $signups24h,
                'sanctions' => count($sanctions),
                'incidentsOpen' => $incidentsOpen,
                'paymentsToday' => $paymentsToday,
                'resolutionRate' => $resolutionRate,
                'talents' => $userRepository->countUsersHavingRole('ROLE_TALENT'),
                'companies' => $userRepository->countUsersHavingRole('ROLE_COMPANY'),
                'individuals' => $userRepository->countUsersHavingRole('ROLE_PARTICULIER'),
                'moderators' => $userRepository->countUsersHavingRole('ROLE_MODERATEUR'),
                'pendingProfiles' => $pendingCni + $pendingProfessional,
                'pendingVerifications' => $pendingCni + $pendingProfessional,
                'rules' => number_format(count($rules), 0, ',', ' '),
                'usersCurrentMonthCount' => $userMonthStats['currentMonthCount'],
                'usersCurrentMonthDelta' => $userMonthStats['percentage'],
                'usersPreviousMonthCount' => $userMonthStats['previousMonthCount'],
                
                'mrr' => $mrrStats['current'],
                'mrrDelta' => $mrrStats['delta'],
                'mrrProgress' => $mrrStats['progress'],

                'gmvStats' => $gmvStats,

                'jobsCurrentMonthCount' => $jobMonthStats['currentMonthCount'],
                'jobsPreviousMonthCount' => $jobMonthStats['previousMonthCount'],
                'jobsCurrentMonthDelta' => $jobMonthStats['percentage'],
                'uptime' => $realUptime,
                'latency' => $realLatency,

                
            ],
            'maintenanceEnabled' => $maintenanceService->isEnabled(),
            
            'latestUsers' => $latestUsers,
            'pendingProProfiles' => $pendingProProfiles,
            'flaggedJobs' => $flaggedJobs,
            'charts' => [
                'signups30d' => $signups30d,
                // IMPORTANT: ton entité Report n’a pas targetJob -> donc ici toujours false
                'flaggedJobsIsReportObjects' => false,
            ],

            'growthStats' => $growthStats,
            'signupChartData' => $signupChartData,
        ]);
    }

    private function buildAlertsData(
        PaymentDisputeRepository $paymentDisputeRepository,
        ReportRepository $reportRepository,
        ReportJobRepository $reportJobRepository,
        KycRuleRepository $kycRuleRepository,
    ): array {
        $now = new \DateTimeImmutable();

        $since20Min = $now->sub(new \DateInterval('PT20M'));
        $since30Min = $now->sub(new \DateInterval('PT30M'));

        $alerts = [];

        // 1) ALERT CRITIQUE - PAYMENT DISPUTE
        $recentDisputesCount = $paymentDisputeRepository->countRecentOpenOrReview($since20Min);
        $latestDisputeAt = $paymentDisputeRepository->findLatestRecentOpenOrReview($since20Min);

        if ($recentDisputesCount > 0) {
            $alerts[] = [
                'level' => 'critical',
                'title' => 'Pic de litiges paiement',
                'meta' => sprintf('%d litige(s) ouverts/en revue en 20 min', $recentDisputesCount),
                'time' => $this->formatAgo($latestDisputeAt),
            ];
        }

        // 2) ALERT WARNING - REPORT + REPORTJOB
        $recentUserReportsCount = $reportRepository->countRecentOpenForAlert($since30Min);
        $recentJobReportsCount = $reportJobRepository->countRecentPendingForAlert($since30Min);

        $moderationCount = $recentUserReportsCount + $recentJobReportsCount;

        $latestUserReportAt = $reportRepository->findLatestRecentOpenForAlert($since30Min);
        $latestJobReportAt = $reportJobRepository->findLatestRecentPendingForAlert($since30Min);
        $latestModerationAt = $this->maxDate($latestUserReportAt, $latestJobReportAt);

        if ($moderationCount > 0) {
            $alerts[] = [
                'level' => 'warning',
                'title' => 'File de modération en hausse',
                'meta' => sprintf(
                    '%d signalement(s) à traiter (%d comptes, %d jobs)',
                    $moderationCount,
                    $recentUserReportsCount,
                    $recentJobReportsCount
                ),
                'time' => $this->formatAgo($latestModerationAt),
            ];
        }

        // 3) ALERT INFO - KYC RULE
        // ATTENTION : ce n’est PAS une demande KYC
        $enabledRulesCount = $kycRuleRepository->countEnabledRules();
        $latestKycRuleAt = $kycRuleRepository->findLatestChangedAt();

        if ($enabledRulesCount > 0 && $latestKycRuleAt !== null) {
            $alerts[] = [
                'level' => 'info',
                'title' => 'Configuration KYC active',
                'meta' => sprintf('%d règle(s) KYC activée(s)', $enabledRulesCount),
                'time' => $this->formatAgo($latestKycRuleAt),
            ];
        }

        return $alerts;
    }

    private function formatAgo(?\DateTimeInterface $date): string
    {
        if (!$date) {
            return '—';
        }

        $now = new \DateTimeImmutable();
        $seconds = $now->getTimestamp() - $date->getTimestamp();

        if ($seconds < 60) {
            return 'À l’instant';
        }

        $minutes = (int) floor($seconds / 60);
        if ($minutes < 60) {
            return sprintf('Il y a %d min', $minutes);
        }

        $hours = (int) floor($minutes / 60);
        if ($hours < 24) {
            return sprintf('Il y a %d h', $hours);
        }

        $days = (int) floor($hours / 24);
        return sprintf('Il y a %d j', $days);
    }

    private function maxDate(?\DateTimeInterface $a, ?\DateTimeInterface $b): ?\DateTimeInterface
    {
        if ($a === null) {
            return $b;
        }

        if ($b === null) {
            return $a;
        }

        return $a >= $b ? $a : $b;
    }

    private function denyUnlessAdminOrSuperAdmin(): void
    {
        if (!$this->isGranted('ROLE_ADMIN') && !$this->isGranted('ROLE_SUPER_ADMIN')) {
            throw $this->createAccessDeniedException('Accès refusé.');
        }
    }

    private function buildModerationChart(array $reportRows, array $reportJobRows): array
    {
        $chart = [
            'spam' => 0,
            'abuse' => 0,
            'fraud' => 0,
            'identity' => 0,
            'other' => 0,
        ];

        foreach ($reportRows as $row) {
            $label = $row['label'] ?? $row['categorie'] ?? $row['name'] ?? null;
            $count = (int) ($row['total'] ?? $row['count'] ?? $row['c'] ?? 0);

            $key = $this->normalizeModerationLabel($label);
            $chart[$key] += $count;
        }

        foreach ($reportJobRows as $row) {
            $label = $row['label'] ?? $row['reason'] ?? $row['categorie'] ?? $row['name'] ?? null;
            $count = (int) ($row['total'] ?? $row['count'] ?? $row['c'] ?? 0);

            $key = $this->normalizeModerationLabel($label);
            $chart[$key] += $count;
        }

        return $chart;
    }

    private function normalizeModerationLabel(?string $label): string
    {
        $value = trim((string) $label);

        if ($value === '') {
            return 'other';
        }

        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        $ascii = $ascii !== false ? $ascii : $value;
        $ascii = mb_strtolower($ascii);

        // labels exacts de ta table categorie_report
        if (str_contains($ascii, 'spam') || str_contains($ascii, 'publicite abusive')) {
            return 'spam';
        }

        if (str_contains($ascii, 'arnaque') || str_contains($ascii, 'fraude')) {
            return 'fraud';
        }

        if (str_contains($ascii, 'usurpation') || str_contains($ascii, "identite")) {
            return 'identity';
        }

        if (str_contains($ascii, 'harcelement') || str_contains($ascii, 'menace') || str_contains($ascii, 'abus')) {
            return 'abuse';
        }

        if (str_contains($ascii, 'contenu inapproprie') || str_contains($ascii, 'autre')) {
            return 'other';
        }

        return 'other';
    }


    private function formatRiskLabel(string $risk): string
    {
        return match ($risk) {
            CompanyKycCase::RISK_HIGH => 'Élevé',
            CompanyKycCase::RISK_MEDIUM => 'Moyen',
            default => 'Faible',
        };
    }

    private function buildPendingKyc(CompanyKycCaseRepository $companyKycCaseRepository): array
    {
        $cases = $companyKycCaseRepository->findLatestPending(5);

        return array_map(function (CompanyKycCase $c): array {
            return [
                'id' => $c->getId(),
                'companyName' => $c->getDisplayCompanyName(),
                'city' => $c->getDisplayCity(),
                'risk' => $this->formatRiskLabel($c->getRiskLevel()),
                'status' => $c->getStatus(),
            ];
        }, $cases);
    }

}
