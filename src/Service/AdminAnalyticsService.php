<?php

namespace App\Service;

use App\Entity\PaymentDispute;
use App\Entity\Report;
use App\Repository\InvoicesRepository;
use App\Repository\JobRepository;
use App\Repository\PaymentDisputeRepository;
use App\Repository\PaymentRepository;
use App\Repository\ReportJobRepository;
use App\Repository\ReportRepository;
use App\Repository\SubscriptionRepository;
use App\Repository\UserRepository;

class AdminAnalyticsService
{
    public function __construct(
        private UserRepository $userRepository,
        private JobRepository $jobRepository,
        private PaymentRepository $paymentRepository,
        private InvoicesRepository $invoicesRepository,
        private SubscriptionRepository $subscriptionRepository,
        private PaymentDisputeRepository $paymentDisputeRepository,
        private ReportRepository $reportRepository,
        private ReportJobRepository $reportJobRepository,
    ) {
    }

    public function build(string $range = '30d', string $segment = 'global'): array
    {
        $range = in_array($range, ['7d', '30d', '90d', '12m'], true) ? $range : '30d';
        $segment = in_array($segment, ['global', 'users', 'jobs', 'finance', 'risk'], true) ? $segment : 'global';

        $days = match ($range) {
            '7d' => 7,
            '30d' => 30,
            '90d' => 90,
            '12m' => 365,
            default => 30,
        };

        $since = new \DateTimeImmutable('-' . $days . ' days');
        $previousSince = (clone $since)->modify('-' . $days . ' days');
        $previousUntil = $since;

        $users = $this->buildUsersSection($since, $previousSince, $previousUntil);
        $jobs = $this->buildJobsSection($since, $previousSince, $previousUntil);
        $finance = $this->buildFinanceSection($since, $previousSince, $previousUntil);
        $risk = $this->buildRiskSection($since, $previousSince, $previousUntil);

        return [
            'range' => $range,
            'segment' => $segment,
            'users' => $users,
            'jobs' => $jobs,
            'finance' => $finance,
            'risk' => $risk,
            'summary' => [
                'users' => $users['kpis']['totalUsers'],
                'activeJobs' => $jobs['kpis']['activeJobs'],
                'revenue' => $finance['kpis']['revenueTotal'],
                'openDisputes' => $finance['disputes']['kpis']['openDisputes'],
                'openReports' => $risk['reports']['kpis']['openReports'],
                'pendingJobReports' => $risk['jobReports']['kpis']['pendingJobReports'],
            ],
            'alerts' => $this->buildAlerts($users, $jobs, $finance, $risk),
        ];
    }

    private function buildUsersSection(\DateTimeInterface $since, \DateTimeInterface $previousSince, \DateTimeInterface $previousUntil): array
    {
        $totalUsers = $this->userRepository->countAllUsers();
        $activeUsers = $this->userRepository->countActiveUsers();
        $inactiveUsers = max(0, $totalUsers - $activeUsers);
        $verifiedUsers = $this->userRepository->countVerifiedUsers();
        $unverifiedUsers = max(0, $totalUsers - $verifiedUsers);

        $newUsers = $this->userRepository->countNewUsersSince($since);
        $previousNewUsers = $this->countUsersBetween($previousSince, $previousUntil);

        $signupRows = $this->userRepository->getSignupChartSince($since);

        return [
            'kpis' => [
                'totalUsers' => $totalUsers,
                'activeUsers' => $activeUsers,
                'inactiveUsers' => $inactiveUsers,
                'verifiedUsers' => $verifiedUsers,
                'unverifiedUsers' => $unverifiedUsers,
                'newUsers' => $newUsers,
                'newUsersDelta' => $this->delta($newUsers, $previousNewUsers),
            ],
            'charts' => [
                'signups' => array_map(static function (array $row) {
                    return [
                        'label' => (new \DateTime($row['d']))->format('d/m'),
                        'value' => (int) $row['c'],
                    ];
                }, $signupRows),
            ],
            'roleStats' => $this->userRepository->getRoleStats(),
            'recentUsers' => $this->userRepository->findRecentUsers(8),
            'lastLogins' => $this->userRepository->findLastLogins(8),
        ];
    }

    private function buildJobsSection(\DateTimeInterface $since, \DateTimeInterface $previousSince, \DateTimeInterface $previousUntil): array
    {
        $totalJobs = $this->jobRepository->countAllJobs();
        $activeJobs = $this->jobRepository->countActiveJobs();
        $expiredJobs = $this->jobRepository->countExpiredJobs();
        $newJobs = $this->jobRepository->countNewJobsSince($since);
        $previousNewJobs = $this->countJobsBetween($previousSince, $previousUntil);

        $jobRows = $this->jobRepository->getJobsChartSince($since);

        return [
            'kpis' => [
                'totalJobs' => $totalJobs,
                'activeJobs' => $activeJobs,
                'expiredJobs' => $expiredJobs,
                'newJobs' => $newJobs,
                'newJobsDelta' => $this->delta($newJobs, $previousNewJobs),
            ],
            'charts' => [
                'jobs' => array_map(static function (array $row) {
                    return [
                        'label' => (new \DateTime($row['d']))->format('d/m'),
                        'value' => (int) $row['c'],
                    ];
                }, $jobRows),
            ],
            'topCities' => $this->jobRepository->getTopCities(8),
            'topCreators' => $this->jobRepository->getTopCreators(8),
            'applicationStats' => $this->jobRepository->getApplicationStats(8),
            'recentJobs' => $this->jobRepository->findRecentJobs(8),
        ];
    }

    private function buildFinanceSection(\DateTimeInterface $since, \DateTimeInterface $previousSince, \DateTimeInterface $previousUntil): array
    {
        $totalPayments = $this->paymentRepository->countAllPayments();
        $paymentsCount = $this->paymentRepository->countPaymentsSince($since);
        $previousPaymentsCount = $this->countPaymentsBetween($previousSince, $previousUntil);

        $revenueTotal = $this->paymentRepository->getTotalRevenueSince($since);
        $previousRevenueTotal = $this->sumPaymentsBetween($previousSince, $previousUntil);

        $totalInvoices = $this->invoicesRepository->countAllInvoices();
        $invoicesCount = $this->invoicesRepository->countInvoicesSince($since);

        $totalSubscriptions = $this->subscriptionRepository->countAllSubscriptions();
        $activeSubscriptions = $this->subscriptionRepository->countActiveSubscriptions();
        $expiredSubscriptions = $this->subscriptionRepository->countExpiredSubscriptions();
        $newSubscriptions = $this->subscriptionRepository->countNewSubscriptionsSince($since);
        $previousNewSubscriptions = $this->countSubscriptionsBetween($previousSince, $previousUntil);

        $totalDisputes = $this->paymentDisputeRepository->countAllDisputes();
        $openDisputes = $this->paymentDisputeRepository->countByStatus(PaymentDispute::STATUS_OPEN);
        $reviewDisputes = $this->paymentDisputeRepository->countByStatus(PaymentDispute::STATUS_REVIEW);
        $resolvedDisputes = $this->paymentDisputeRepository->countByStatus(PaymentDispute::STATUS_RESOLVED);
        $refundedDisputes = $this->paymentDisputeRepository->countByStatus(PaymentDispute::STATUS_REFUNDED);
        $rejectedDisputes = $this->paymentDisputeRepository->countByStatus(PaymentDispute::STATUS_REJECTED);
        $newDisputes = $this->paymentDisputeRepository->countDisputesSince($since);

        return [
            'kpis' => [
                'totalPayments' => $totalPayments,
                'paymentsCount' => $paymentsCount,
                'paymentsDelta' => $this->delta($paymentsCount, $previousPaymentsCount),
                'revenueTotal' => $revenueTotal,
                'revenueDelta' => $this->delta($revenueTotal, $previousRevenueTotal),
                'totalInvoices' => $totalInvoices,
                'invoicesCount' => $invoicesCount,
                'totalSubscriptions' => $totalSubscriptions,
                'activeSubscriptions' => $activeSubscriptions,
                'expiredSubscriptions' => $expiredSubscriptions,
                'newSubscriptions' => $newSubscriptions,
                'newSubscriptionsDelta' => $this->delta($newSubscriptions, $previousNewSubscriptions),
            ],
            'charts' => [
                'payments' => array_map(static function (array $row) {
                    return [
                        'label' => (new \DateTime($row['d']))->format('d/m'),
                        'value' => (int) $row['c'],
                    ];
                }, $this->paymentRepository->getPaymentsChartSince($since)),
                'revenue' => array_map(static function (array $row) {
                    return [
                        'label' => (new \DateTime($row['d']))->format('d/m'),
                        'value' => (float) $row['total'],
                    ];
                }, $this->paymentRepository->getRevenueChartSince($since)),
                'invoices' => array_map(static function (array $row) {
                    return [
                        'label' => (new \DateTime($row['d']))->format('d/m'),
                        'value' => (int) $row['c'],
                    ];
                }, $this->invoicesRepository->getInvoicesChartSince($since)),
                'subscriptions' => array_map(static function (array $row) {
                    return [
                        'label' => (new \DateTime($row['d']))->format('d/m'),
                        'value' => (int) $row['c'],
                    ];
                }, $this->subscriptionRepository->getSubscriptionsChartSince($since)),
                'disputes' => array_map(static function (array $row) {
                    return [
                        'label' => (new \DateTime($row['dte']))->format('d/m'),
                        'value' => (int) $row['c'],
                    ];
                }, $this->paymentDisputeRepository->getDisputesChartSince($since)),
            ],
            'topProviders' => $this->paymentRepository->getTopProvidersSince($since, 8),
            'topPlans' => $this->subscriptionRepository->getTopPlansSince($since, 8),
            'recentPayments' => $this->paymentRepository->findRecentPayments(8),
            'recentInvoices' => $this->invoicesRepository->findRecentInvoices(8),
            'recentSubscriptions' => $this->subscriptionRepository->findRecentSubscriptions(8),
            'disputes' => [
                'kpis' => [
                    'totalDisputes' => $totalDisputes,
                    'openDisputes' => $openDisputes,
                    'reviewDisputes' => $reviewDisputes,
                    'resolvedDisputes' => $resolvedDisputes,
                    'refundedDisputes' => $refundedDisputes,
                    'rejectedDisputes' => $rejectedDisputes,
                    'newDisputes' => $newDisputes,
                ],
                'recentDisputes' => $this->paymentDisputeRepository->findRecentDisputes(8),
                'sourceStats' => $this->paymentDisputeRepository->getSourceStatsSince($since),
                'priorityStats' => $this->paymentDisputeRepository->getPriorityStatsSince($since),
            ],
        ];
    }

    private function buildRiskSection(\DateTimeInterface $since, \DateTimeInterface $previousSince, \DateTimeInterface $previousUntil): array
    {
        $totalReports = $this->reportRepository->countAllReports();
        $openReports = $this->reportRepository->countByStatus(Report::STATUS_OPEN);
        $resolvedReports = $this->reportRepository->countByStatus(Report::STATUS_RESOLVED);
        $canceledReports = $this->reportRepository->countByStatus(Report::STATUS_CANCELED);
        $newReports = $this->reportRepository->countReportsSince($since);
        $previousNewReports = $this->countReportsBetween($previousSince, $previousUntil);

        $totalJobReports = $this->reportJobRepository->countAllJobReports();
        $pendingJobReports = $this->reportJobRepository->countByStatus('pending');
        $reviewedJobReports = $this->reportJobRepository->countByStatus('reviewed');
        $resolvedJobReports = $this->reportJobRepository->countByStatus('resolved');
        $rejectedJobReports = $this->reportJobRepository->countByStatus('rejected');
        $newJobReports = $this->reportJobRepository->countJobReportsSince($since);
        $previousNewJobReports = $this->countJobReportsBetween($previousSince, $previousUntil);

        return [
            'reports' => [
                'kpis' => [
                    'totalReports' => $totalReports,
                    'openReports' => $openReports,
                    'resolvedReports' => $resolvedReports,
                    'canceledReports' => $canceledReports,
                    'newReports' => $newReports,
                    'newReportsDelta' => $this->delta($newReports, $previousNewReports),
                ],
                'charts' => [
                    'reports' => array_map(static function (array $row) {
                        return [
                            'label' => (new \DateTime($row['dte']))->format('d/m'),
                            'value' => (int) $row['c'],
                        ];
                    }, $this->reportRepository->getReportsChartSince($since)),
                ],
                'recentReports' => $this->reportRepository->findRecentReports(8),
                'topReportedUsers' => $this->reportRepository->getTopReportedUsers(8),
                'topCategories' => $this->reportRepository->getTopCategoriesSince($since, 8),
            ],
            'jobReports' => [
                'kpis' => [
                    'totalJobReports' => $totalJobReports,
                    'pendingJobReports' => $pendingJobReports,
                    'reviewedJobReports' => $reviewedJobReports,
                    'resolvedJobReports' => $resolvedJobReports,
                    'rejectedJobReports' => $rejectedJobReports,
                    'newJobReports' => $newJobReports,
                    'newJobReportsDelta' => $this->delta($newJobReports, $previousNewJobReports),
                ],
                'charts' => [
                    'jobReports' => array_map(static function (array $row) {
                        return [
                            'label' => (new \DateTime($row['dte']))->format('d/m'),
                            'value' => (int) $row['c'],
                        ];
                    }, $this->reportJobRepository->getJobReportsChartSince($since)),
                ],
                'recentJobReports' => $this->reportJobRepository->findRecentJobReports(8),
                'topReportedJobs' => $this->reportJobRepository->getTopReportedJobs(8),
            ],
        ];
    }

    private function buildAlerts(array $users, array $jobs, array $finance, array $risk): array
    {
        $alerts = [];

        if (($finance['disputes']['kpis']['openDisputes'] ?? 0) > 10) {
            $alerts[] = [
                'level' => 'high',
                'title' => 'Litiges ouverts élevés',
                'description' => 'Le volume des litiges ouverts dépasse le seuil de surveillance.',
            ];
        }

        if (($risk['reports']['kpis']['openReports'] ?? 0) > 10) {
            $alerts[] = [
                'level' => 'high',
                'title' => 'Signalements comptes en hausse',
                'description' => 'Les signalements ouverts de comptes demandent une revue prioritaire.',
            ];
        }

        if (($risk['jobReports']['kpis']['pendingJobReports'] ?? 0) > 10) {
            $alerts[] = [
                'level' => 'medium',
                'title' => 'Signalements offres à traiter',
                'description' => 'Des offres signalées restent en attente de traitement.',
            ];
        }

        if (($users['kpis']['unverifiedUsers'] ?? 0) > ($users['kpis']['verifiedUsers'] ?? 0)) {
            $alerts[] = [
                'level' => 'medium',
                'title' => 'Vérification email faible',
                'description' => 'Les comptes non vérifiés dépassent les comptes vérifiés.',
            ];
        }

        if (empty($alerts)) {
            $alerts[] = [
                'level' => 'low',
                'title' => 'Plateforme stable',
                'description' => 'Aucune alerte majeure détectée sur la période.',
            ];
        }

        return $alerts;
    }

    private function delta(float|int $current, float|int $previous): float
    {
        if ((float) $previous === 0.0) {
            return $current > 0 ? 100.0 : 0.0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    private function countUsersBetween(\DateTimeInterface $from, \DateTimeInterface $to): int
    {
        return (int) $this->userRepository->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u.createdAt >= :from')
            ->andWhere('u.createdAt < :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function countJobsBetween(\DateTimeInterface $from, \DateTimeInterface $to): int
    {
        return (int) $this->jobRepository->createQueryBuilder('j')
            ->select('COUNT(j.id)')
            ->andWhere('j.createdAt >= :from')
            ->andWhere('j.createdAt < :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function countPaymentsBetween(\DateTimeInterface $from, \DateTimeInterface $to): int
    {
        return (int) $this->paymentRepository->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.paidAt >= :from')
            ->andWhere('p.paidAt < :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function sumPaymentsBetween(\DateTimeInterface $from, \DateTimeInterface $to): float
    {
        $rows = $this->paymentRepository->createQueryBuilder('p')
            ->select('p.amount AS amount')
            ->andWhere('p.paidAt >= :from')
            ->andWhere('p.paidAt < :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getArrayResult();

        $total = 0.0;
        foreach ($rows as $row) {
            $total += (float) str_replace([' ', ','], ['', '.'], (string) $row['amount']);
        }

        return $total;
    }

    private function countSubscriptionsBetween(\DateTimeInterface $from, \DateTimeInterface $to): int
    {
        return (int) $this->subscriptionRepository->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->andWhere('s.startAt >= :from')
            ->andWhere('s.startAt < :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function countReportsBetween(\DateTimeInterface $from, \DateTimeInterface $to): int
    {
        return (int) $this->reportRepository->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.createdAt >= :from')
            ->andWhere('r.createdAt < :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function countJobReportsBetween(\DateTimeInterface $from, \DateTimeInterface $to): int
    {
        return (int) $this->reportJobRepository->createQueryBuilder('rj')
            ->select('COUNT(rj.id)')
            ->andWhere('rj.createdAt >= :from')
            ->andWhere('rj.createdAt < :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleScalarResult();
    }
}