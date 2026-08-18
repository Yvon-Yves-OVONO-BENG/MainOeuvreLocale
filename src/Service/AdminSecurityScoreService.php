<?php

namespace App\Service;

use App\Repository\PersonalProfileRepository;
use App\Repository\ProfessionalProfileRepository;
use App\Repository\ReportRepository;
use App\Repository\UserLogRepository;
use App\Repository\UserRepository;
use App\Repository\UserTwoFactorRepository;

class AdminSecurityScoreService
{
    public function __construct(
        private readonly UserTwoFactorRepository $userTwoFactorRepository,
        private readonly PersonalProfileRepository $personalProfileRepository,
        private readonly ProfessionalProfileRepository $professionalProfileRepository,
        private readonly UserRepository $userRepository,
        private readonly UserLogRepository $userLogRepository,
        private readonly ?ReportRepository $reportRepository = null,
    ) {
    }

    public function build(): array
    {
        $staffRoles = ['ROLE_ADMIN', 'ROLE_MODERATEUR'];

        $staffCount = $this->userRepository->countUsersHavingAnyRole($staffRoles);
        $staffWith2fa = $this->userTwoFactorRepository->countEnabledForRoles($staffRoles);

        $twoFactorRate = $staffCount > 0
            ? (int) round(($staffWith2fa / $staffCount) * 100)
            : 0;

        $companiesCount = $this->userRepository->countUsersHavingRole('ROLE_COMPANY');
        $pendingCompanyKyc = $this->professionalProfileRepository->countUnverifiedProfiles();

        $verifiedCompanies = max(0, $companiesCount - $pendingCompanyKyc);
        $kycRate = $companiesCount > 0
            ? (int) round(($verifiedCompanies / $companiesCount) * 100)
            : 0;

        $logs24h = $this->userLogRepository->countLogsSince(
            (new \DateTimeImmutable())->sub(new \DateInterval('P1D'))
        );

        $targetLogs = max(1, $staffCount * 5);
        $auditCoverageRate = (int) min(100, round(($logs24h / $targetLogs) * 100));

        $openIncidents = $this->reportRepository
            ? $this->reportRepository->countByStatuses(['open', 'investigating'])
            : 0;

        $incidentHealthRate = max(0, 100 - ($openIncidents * 12));

        $pendingCni = $this->personalProfileRepository->countPendingCni();
        $pendingProfessional = $this->professionalProfileRepository->countUnverifiedProfiles();
        $pendingVerificationTotal = $pendingCni + $pendingProfessional;

        $verificationBacklogRate = max(0, 100 - ($pendingVerificationTotal * 4));

        $score = (int) round(
            ($twoFactorRate * 0.30) +
            ($kycRate * 0.25) +
            ($auditCoverageRate * 0.20) +
            ($incidentHealthRate * 0.15) +
            ($verificationBacklogRate * 0.10)
        );

        $score = max(0, min(100, $score));

        return [
            'score' => $score,
            'label' => $this->getLabel($score),
            'tone' => $this->getTone($score),
            'metrics' => [
                'twoFactorStaff' => [
                    'label' => '2FA staff',
                    'rate' => $twoFactorRate,
                    'value' => sprintf('%d/%d', $staffWith2fa, $staffCount),
                ],
                'kycCompanies' => [
                    'label' => 'KYC entreprises',
                    'rate' => $kycRate,
                    'value' => sprintf('%d/%d', $verifiedCompanies, $companiesCount),
                ],
                'auditCoverage' => [
                    'label' => 'Audit coverage',
                    'rate' => $auditCoverageRate,
                    'value' => (string) $logs24h,
                ],
                'incidentHealth' => [
                    'label' => 'Santé incidents',
                    'rate' => $incidentHealthRate,
                    'value' => (string) $openIncidents,
                ],
                'verificationBacklog' => [
                    'label' => 'File vérifications',
                    'rate' => $verificationBacklogRate,
                    'value' => (string) $pendingVerificationTotal,
                ],
            ],
            'raw' => [
                'staffCount' => $staffCount,
                'staffWith2fa' => $staffWith2fa,
                'companiesCount' => $companiesCount,
                'verifiedCompanies' => $verifiedCompanies,
                'pendingCompanyKyc' => $pendingCompanyKyc,
                'logs24h' => $logs24h,
                'openIncidents' => $openIncidents,
                'pendingVerificationTotal' => $pendingVerificationTotal,
            ],
            'recommendations' => $this->getRecommendations(
                $twoFactorRate,
                $kycRate,
                $auditCoverageRate,
                $incidentHealthRate,
                $verificationBacklogRate
            ),
        ];
    }

    private function getLabel(int $score): string
    {
        return match (true) {
            $score >= 90 => 'Excellent',
            $score >= 75 => 'Bon',
            $score >= 60 => 'À surveiller',
            default => 'Risque élevé',
        };
    }

    private function getTone(int $score): string
    {
        return match (true) {
            $score >= 90 => 'emerald',
            $score >= 75 => 'cyan',
            $score >= 60 => 'amber',
            default => 'rose',
        };
    }

    private function getRecommendations(
        int $twoFactorRate,
        int $kycRate,
        int $auditCoverageRate,
        int $incidentHealthRate,
        int $verificationBacklogRate
    ): array {
        $items = [];

        if ($twoFactorRate < 80) {
            $items[] = 'Activer le 2FA sur tous les comptes staff.';
        }

        if ($kycRate < 75) {
            $items[] = 'Réduire la file KYC entreprises en priorité.';
        }

        if ($auditCoverageRate < 70) {
            $items[] = 'Améliorer la couverture des traces et logs d’audit.';
        }

        if ($incidentHealthRate < 70) {
            $items[] = 'Traiter les incidents ouverts avant qu’ils n’impactent la confiance globale.';
        }

        if ($verificationBacklogRate < 70) {
            $items[] = 'Absorber la file de vérifications en attente.';
        }

        if (empty($items)) {
            $items[] = 'Le niveau global est bon. Maintenir la discipline de sécurité actuelle.';
        }

        return $items;
    }
}