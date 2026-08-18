<?php

namespace App\Controller\Web\Admin;

use App\Entity\Job;
use App\Entity\User;
use App\Repository\JobRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin', name: 'admin_')]
class AdminDirectoryController extends AbstractController
{
    private const ROLE_TALENT = 'ROLE_TALENT';
    private const ROLE_COMPANY = 'ROLE_COMPANY';
    private const ROLE_PARTICULIER = 'ROLE_PARTICULIER';
    private const ROLE_MODERATEUR = 'ROLE_MODERATEUR';
    private const PER_PAGE = 10;

    #[Route('/talents', name: 'talents_index', methods: ['GET'])]
    public function talents(Request $request, UserRepository $userRepository): Response
    {
        return $this->renderUserDirectory(
            request: $request,
            userRepository: $userRepository,
            role: self::ROLE_TALENT,
            pageTitle: 'Talents',
            pageKicker: 'Admin • Talents',
            pageDescription: 'Vue des comptes talent actifs sur la plateforme.',
            currentSection: 'talents'
        );
    }

    #[Route('/companies', name: 'companies_index', methods: ['GET'])]
    public function companies(Request $request, UserRepository $userRepository): Response
    {
        return $this->renderUserDirectory(
            request: $request,
            userRepository: $userRepository,
            role: self::ROLE_COMPANY,
            pageTitle: 'Compagnies',
            pageKicker: 'Admin • Compagnies',
            pageDescription: 'Vue des comptes entreprises enregistrés.',
            currentSection: 'companies'
        );
    }

    #[Route('/individuals', name: 'individuals_index', methods: ['GET'])]
    public function individuals(Request $request, UserRepository $userRepository): Response
    {
        return $this->renderUserDirectory(
            request: $request,
            userRepository: $userRepository,
            role: self::ROLE_PARTICULIER,
            pageTitle: 'Particuliers',
            pageKicker: 'Admin • Particuliers',
            pageDescription: 'Vue des comptes particuliers de la plateforme.',
            currentSection: 'individuals'
        );
    }

    #[Route('/moderators', name: 'moderators_index', methods: ['GET'])]
    public function moderators(Request $request, UserRepository $userRepository): Response
    {
        return $this->renderUserDirectory(
            request: $request,
            userRepository: $userRepository,
            role: self::ROLE_MODERATEUR,
            pageTitle: 'Modérateurs',
            pageKicker: 'Admin • Modérateurs',
            pageDescription: 'Vue des comptes de modération et contrôle.',
            currentSection: 'moderators'
        );
    }

    #[Route('/jobs', name: 'jobs_index', methods: ['GET'])]
    public function jobs(Request $request, JobRepository $jobRepository): Response
    {
        $this->denyUnlessAdminOrSuperAdmin();

        $page = max(1, $request->query->getInt('page', 1));
        $q = trim((string) $request->query->get('q', ''));

        $jobs = $jobRepository->findLatestActiveJobsPaginated($page, self::PER_PAGE, $q);
        $total = $jobRepository->countActiveJobsFiltered($q);
        $pages = max(1, (int) ceil($total / self::PER_PAGE));

        return $this->render('admin/directory/jobs.html.twig', [
            'pageTitle' => 'Offres actives',
            'pageKicker' => 'Admin • Offres',
            'pageDescription' => 'Vue des offres encore actives selon la date d’expiration.',
            'jobs' => array_map(fn(Job $j) => $this->normalizeJob($j), $jobs),
            'stats' => [
                'total' => $total,
                'expiringSoon' => count(array_filter($jobs, function (Job $j): bool {
                    $exp = $j->getDateExpirationAt();
                    if (!$exp) {
                        return false;
                    }

                    $now = new \DateTimeImmutable();
                    $limit = $now->modify('+7 days');

                    return $exp >= $now && $exp <= $limit;
                })),
                'withReports' => count(array_filter($jobs, fn(Job $j) => $j->getReports()->count() > 0)),
            ],
            'currentSection' => 'jobs',
            'filters' => [
                'q' => $q,
            ],
            'pagination' => [
                'page' => $page,
                'pages' => $pages,
                'perPage' => self::PER_PAGE,
                'total' => $total,
            ],
        ]);
    }

    private function renderUserDirectory(
        Request $request,
        UserRepository $userRepository,
        string $role,
        string $pageTitle,
        string $pageKicker,
        string $pageDescription,
        string $currentSection
    ): Response {
        $this->denyUnlessAdminOrSuperAdmin();

        $page = max(1, $request->query->getInt('page', 1));
        $q = trim((string) $request->query->get('q', ''));

        $users = $userRepository->findUsersHavingRolePaginated($role, $page, self::PER_PAGE, $q);
        $total = $userRepository->countUsersHavingRoleFiltered($role, $q);
        $pages = max(1, (int) ceil($total / self::PER_PAGE));

        return $this->render('admin/directory/users.html.twig', [
            'pageTitle' => $pageTitle,
            'pageKicker' => $pageKicker,
            'pageDescription' => $pageDescription,
            'users' => array_map(fn(User $u) => $this->normalizeUser($u), $users),
            'stats' => [
                'total' => $total,
                'active' => count(array_filter($users, fn(User $u) => $u->isActive() === true)),
                'verified' => count(array_filter($users, fn(User $u) => $u->isEmailVerified() === true)),
            ],
            'currentSection' => $currentSection,
            'filters' => [
                'q' => $q,
            ],
            'pagination' => [
                'page' => $page,
                'pages' => $pages,
                'perPage' => self::PER_PAGE,
                'total' => $total,
            ],
        ]);
    }

    private function normalizeUser(User $user): array
    {
        return [
            'id' => $user->getId(),
            'name' => $this->resolveUserName($user),
            'email' => $user->getEmail(),
            'phone' => $user->getPhone(),
            'country' => $this->extractEntityLabel($user->getCountry()) ?? '—',
            'typeCompte' => $this->extractEntityLabel($user->getTypeCompte()) ?? '—',
            'roles' => implode(', ', $user->getRoles()),
            'active' => $user->isActive() === true,
            'verified' => $user->isEmailVerified() === true,
            'createdAt' => $user->getCreatedAt(),
        ];
    }

    private function normalizeJob(Job $job): array
    {
        return [
            'id' => $job->getId(),
            'title' => $job->getTitle(),
            'company' => $this->resolveUserName($job->getCreatedBy()),
            'city' => $job->getCity(),
            'profession' => $this->extractEntityLabel($job->getProfession()) ?? '—',
            'typeJob' => $this->extractEntityLabel($job->getTypeJob()) ?? '—',
            'status' => $this->extractEntityLabel($job->getStatus()) ?? '—',
            'salary' => number_format((int) $job->getSalaireMin(), 0, ',', ' ') . ' - ' . number_format((int) $job->getSalaireMax(), 0, ',', ' '),
            'reports' => $job->getReports()->count(),
            'createdAt' => $job->getCreatedAt(),
            'expiresAt' => $job->getDateExpirationAt(),
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

        return $user->getEmail() ?? ('User #' . $user->getId());
    }

    private function extractEntityLabel(object|null $entity): ?string
    {
        if ($entity === null) {
            return null;
        }

        foreach (['getNom', 'getName', 'getLabel', 'getLibelle', '__toString'] as $method) {
            if (method_exists($entity, $method)) {
                $value = $entity->{$method}();
                if (is_string($value) && trim($value) !== '') {
                    return $value;
                }
            }
        }

        return null;
    }

    private function denyUnlessAdminOrSuperAdmin(): void
    {
        if (!$this->isGranted('ROLE_ADMIN') && !$this->isGranted('ROLE_SUPER_ADMIN')) {
            throw $this->createAccessDeniedException('Accès refusé.');
        }
    }
}