<?php

namespace App\Service;

use App\Entity\RoleDefinition;
use App\Entity\SecurityIncident;
use App\Entity\SecurityPermission;
use App\Entity\User;
use App\Entity\UserLog;
use App\Entity\UserTwoFactor;
use App\Repository\RoleDefinitionRepository;
use App\Repository\SecurityIncidentRepository;
use App\Repository\SecurityPermissionRepository;
use App\Repository\UserLogRepository;
use App\Repository\UserRepository;
use App\Repository\UserTwoFactorRepository;

class AdminSecurityService
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly UserLogRepository $userLogRepository,
        private readonly RoleDefinitionRepository $roleDefinitionRepository,
        private readonly SecurityPermissionRepository $securityPermissionRepository,
        private readonly SecurityIncidentRepository $securityIncidentRepository,
        private readonly UserTwoFactorRepository $userTwoFactorRepository,
    ) {
    }

    public function getRolesPageData(): array
    {
        $definitions = $this->roleDefinitionRepository->findIndexedByCode();
        $distinctRoles = $this->userRepository->findDistinctRoles();

        $rows = [];

        foreach ($distinctRoles as $roleCode) {
            $definition = $definitions[$roleCode] ?? null;

            $rows[] = [
                'key' => $roleCode,
                'label' => $definition?->getLabel() ?? $roleCode,
                'scope' => $definition?->getScope() ?? 'Non défini',
                'members' => $this->userRepository->countUsersHavingRole($roleCode),
                'permissionsCount' => $definition ? $definition->getPermissions()->count() : 0,
                'risk' => $definition?->getRiskLevel() ?? 'low',
                'description' => $definition?->getDescription() ?? 'Aucune description enregistrée pour ce rôle.',
            ];
        }

        usort($rows, static fn(array $a, array $b): int => strcmp($a['key'], $b['key']));

        $latestReview = null;
        foreach ($definitions as $definition) {
            $updatedAt = $definition->getUpdatedAt();
            if ($updatedAt !== null && ($latestReview === null || $updatedAt > $latestReview)) {
                $latestReview = $updatedAt;
            }
        }

        return [
            'roles' => $rows,
            'stats' => [
                'roles' => count($rows),
                'privileged' => count(array_filter($rows, static fn(array $r): bool => in_array($r['risk'], ['high', 'critical'], true))),
                'coveredUsers' => $this->userRepository->count([]),
                'lastReview' => $latestReview,
            ],
        ];
    }

    public function getPermissionsPageData(): array
    {
        $permissions = $this->securityPermissionRepository->findAllOrdered();

        $groups = [];
        $sensitive = 0;

        foreach ($permissions as $permission) {
            if (in_array($permission->getRiskLevel(), [SecurityPermission::RISK_HIGH, SecurityPermission::RISK_CRITICAL], true)) {
                $sensitive++;
            }

            $domain = $permission->getDomain() ?? 'Autres';
            $groups[$domain] ??= [
                'domain' => $domain,
                'permissions' => [],
            ];

            $groups[$domain]['permissions'][] = [
                'code' => $permission->getCode(),
                'label' => $permission->getLabel(),
                'description' => $permission->getDescription(),
                'risk' => $permission->getRiskLevel(),
                'roles' => array_map(
                    static fn(RoleDefinition $role): string => $role->getCode() ?? '',
                    $permission->getRoles()->toArray()
                ),
            ];
        }

        $groupRows = array_values($groups);

        return [
            'groups' => $groupRows,
            'stats' => [
                'domains' => count($groupRows),
                'permissions' => count($permissions),
                'sensitive' => $sensitive,
                'drift' => 'Sous contrôle',
            ],
        ];
    }

    public function getSessionsPageData(): array
    {
        $sessions = $this->userLogRepository->findActiveSessions();
        $closedSessions = $this->userLogRepository->findClosedSessionsSince(new \DateTimeImmutable('-30 days'));
       
        $rows = array_map(fn(UserLog $log): array => [
            'user' => $log->getUser()->getPersonalProfile()?$log->getUser()->getPersonalProfile()->getFullName():"Pas encore renseigné",
            'email' => $log->getUser()?->getEmail() ?? '—',
            'device' => $log->getDeviceType()->getDeviceType(),
            'logoDevice' => $log->getDeviceType()->getLogo(),
            'location' => $log->getVille(),
            'country' => $log->getCountry() ? $log->getCountry()->getCountry() :"-",
            'flagCountry' => $log->getCountry() ? $log->getCountry()->getFlagFile() :"-",
            'operatingSystem' => $log->getOperatingSystem()->getOperatingSystem(),
            'logoOperatingSystem' => $log->getOperatingSystem()->getLogo(),
            'browser' => $log->getBrowser()->getBrowser(),
            'logoBrowser' => $log->getBrowser()->getLogo(),
            'agent' => $log->getUserAgent(),
            'ip' => $log->getIp(),
            'lastSeen' => $log->getLastSeenAt() ?? $log->getLogedAt(),
            'risk' => $this->computeLogRisk($log),
            'status' => $this->computeLogRisk($log) === 'high' ? 'flagged' : 'active',
        ], $sessions);
        
        return [
            'sessions' => $rows,
            'stats' => [
                'active' => $this->userLogRepository->countActiveSessions(),
                'flagged' => count(array_filter($rows, static fn(array $r): bool => $r['status'] === 'flagged')),
                'newDevices24h' => $this->userLogRepository->countNewSessionsSince(new \DateTimeImmutable('-24 hours')),
                'avgDuration' => $this->formatDuration($this->computeAverageDurationSeconds($closedSessions)),
            ],
        ];
    }

    public function getIncidentsPageData(): array
    {
        $incidents = $this->securityIncidentRepository->findLatest();

        $rows = array_map(fn(SecurityIncident $incident): array => [
            'id' => $incident->getReference(),
            'title' => $incident->getTitle(),
            'type' => $incident->getType(),
            'severity' => $incident->getSeverity(),
            'status' => $incident->getStatus(),
            'sourceIp' => $incident->getSourceIp(),
            'owner' => $this->resolveUserName($incident->getAssignedTo()),
            'createdAt' => $incident->getCreatedAt(),
        ], $incidents);

        return [
            'incidents' => $rows,
            'stats' => [
                'open' => $this->securityIncidentRepository->countByStatus(SecurityIncident::STATUS_OPEN),
                'critical' => $this->securityIncidentRepository->countBySeverity(SecurityIncident::SEVERITY_CRITICAL),
                'mitigated24h' => $this->securityIncidentRepository->countMitigatedSince(new \DateTimeImmutable('-24 hours')),
                'blockedIps' => 0,
            ],
        ];
    }

    public function getTwoFactorPageData(): array
    {
        $records = $this->userTwoFactorRepository->findAllWithUsers();

        $rows = array_map(fn(UserTwoFactor $twoFactor): array => [
            'name' => $this->resolveUserName($twoFactor->getUser()),
            'email' => $twoFactor->getUser()?->getEmail() ?? '—',
            'role' => implode(', ', $twoFactor->getUser()?->getRoles() ?? []),
            'status' => $twoFactor->getUiStatus(),
            'lastChallenge' => $twoFactor->getLastChallengeAt(),
        ], $records);

        return [
            'users' => $rows,
            'stats' => [
                'enabled' => count(array_filter($rows, static fn(array $r): bool => $r['status'] === 'enabled')),
                'pending' => count(array_filter($rows, static fn(array $r): bool => $r['status'] === 'pending')),
                'disabled' => count(array_filter($rows, static fn(array $r): bool => $r['status'] === 'disabled')),
                'policy' => 'Obligatoire pour comptes sensibles',
            ],
        ];
    }

    private function resolveUserName(?User $user): string
    {
        if ($user === null) {
            return '—';
        }

        $profile = $user->getPersonalProfile();

        if ($profile !== null && method_exists($profile, 'getFullName')) {
            $fullName = $profile->getFullName();
            if (is_string($fullName) && trim($fullName) !== '') {
                return $fullName;
            }
        }

        return $user->getEmail() ?? 'Utilisateur';
    }

    private function buildDeviceLabel(UserLog $log): string
    {
        $parts = [];

        $os = $this->extractEntityLabel($log->getOperatingSystem());
        $browser = $this->extractEntityLabel($log->getBrowser());
        $deviceType = $this->extractEntityLabel($log->getDeviceType());

        if ($deviceType) {
            $parts[] = $deviceType;
        }

        if ($os) {
            $parts[] = $os;
        }

        if ($browser) {
            $parts[] = $browser;
        }

        return $parts !== [] ? implode(' • ', $parts) : 'Appareil inconnu';
    }

    private function buildLocationLabel(UserLog $log): string
    {
        $parts = [];

        if ($log->getVille()) {
            $parts[] = $log->getVille();
        }

        $country = $this->extractEntityLabel($log->getCountry());
        if ($country) {
            $parts[] = $country;
        }

        return $parts !== [] ? implode(', ', $parts) : 'Localisation inconnue';
    }

    private function extractEntityLabel(object|null $entity): ?string
    {
        if ($entity === null) {
            return null;
        }

        foreach (['getName', 'getLabel', 'getLibelle', 'getNom', '__toString'] as $method) {
            if (method_exists($entity, $method)) {
                $value = $entity->{$method}();
                if (is_string($value) && trim($value) !== '') {
                    return $value;
                }
            }
        }

        return null;
    }

    private function computeLogRisk(UserLog $log): string
    {
        $action = strtolower((string) $log->getAction());
        $userAgent = strtolower((string) $log->getUserAgent());

        if (
            str_contains($action, 'failed') ||
            str_contains($action, 'blocked') ||
            str_contains($action, 'suspicious') ||
            str_contains($action, 'brute') ||
            $log->getUser() === null
        ) {
            return 'high';
        }

        if ($userAgent === '' || $log->getVille() === null || $log->getVille() === '') {
            return 'medium';
        }

        return 'low';
    }

    private function computeAverageDurationSeconds(array $closedSessions): ?int
    {
        $durations = [];

        foreach ($closedSessions as $log) {
            if (!$log instanceof UserLog) {
                continue;
            }

            $start = $log->getLogedAt();
            $end = $log->getDisconnectedAt();

            if ($start !== null && $end !== null && $end > $start) {
                $durations[] = $end->getTimestamp() - $start->getTimestamp();
            }
        }

        if ($durations === []) {
            return null;
        }

        return (int) round(array_sum($durations) / count($durations));
    }

    private function formatDuration(?int $seconds): string
    {
        if ($seconds === null) {
            return '—';
        }

        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        return sprintf('%dh %02dm', $hours, $minutes);
    }
}