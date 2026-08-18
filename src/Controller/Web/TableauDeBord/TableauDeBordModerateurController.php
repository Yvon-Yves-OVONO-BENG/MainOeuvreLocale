<?php

namespace App\Controller\Web\TableauDeBord;

use App\Entity\User;
use App\Entity\UserLog;
use App\Repository\AppealRepository;
use App\Repository\ProfessionalProfileRepository;
use App\Repository\ReportRepository;
use App\Repository\UserLogRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER', message: 'Accès refusé. Connectez-vous')]
class TableauDeBordModerateurController extends AbstractController
{
    #[Route('/tableau-de-bord-moderateur', name: 'tableau_de_bord_moderateur')]
    public function index(
        ReportRepository $reportRepo,
        ProfessionalProfileRepository $profileRepo,
        AppealRepository $appealRepo,
        UserRepository $userRepository,
        EntityManagerInterface $em,
        ?UserLogRepository $logsRepo = null
    ): Response
    {
        $pendingReports    = $reportRepo->countOpenReports();
        $profilesToVerify  = $profileRepo->countUnverifiedProfiles();
        $suspectJobs       = $reportRepo->countOpenReportsWithTargetJob();

        $reports = $reportRepo->countOpenReportsOnUsers();
        $lastDecisions = $reportRepo->findLastDecisions(10);

        $hasLogs = class_exists(UserLog::class);
        $sanctions = [];

        if ($hasLogs && $logsRepo) {
            $sanctions = $logsRepo->findLastSanctions(10);
        }

        $appeals = $appealRepo->countAppeals(30);
        $handled = $reportRepo->countHandledReports(30);
        $appealRate = $handled > 0 ? round(($appeals / $handled) * 100, 1) : 0;

        return $this->render('tableau_de_bord_moderateur/index.html.twig', [
            'analytics' => $this->buildAnalytics($userRepository),
            'kpi' => [
                'pendingReports' => $pendingReports,
                'profilesToVerify' => $profilesToVerify,
                'suspectJobs' => $suspectJobs,
                'appeals' => $appeals,
                'handled' => $handled,
                'appealRate' => $appealRate,
            ],
            'reports' => $reports,
            'lastDecisions' => $lastDecisions,
            'sanctions' => $sanctions,
            'hasLogs' => $hasLogs,
        ]);
    }

    private function buildAnalytics(UserRepository $userRepository): array
    {
        $today = new \DateTimeImmutable('today');
        $start = $today->modify('-6 days');
        $end = $today->modify('+1 day');

        $labels = [];
        $signupsValues = [];

        for ($i = 0; $i < 7; $i++) {
            $day = $start->modify('+' . $i . ' days');
            $key = $day->format('Y-m-d');

            $labels[$key] = $day->format('d/m');
            $signupsValues[$key] = 0;
        }

        $gender7d = [
            'F' => 0,
            'M' => 0,
            'PM' => 0,
        ];

        $roles7d = [
            'ROLE_TALENT' => 0,
            'ROLE_PARTICULIER' => 0,
            'ROLE_COMPANY' => 0,
        ];

        // ✅ DQL depuis UserRepository
        $users7d = $userRepository->findCompletedUsersCreatedBetween($start, $end);

        foreach ($users7d as $user) {
            /** @var User $user */

            $createdAt = $user->getCreatedAt();

            if ($createdAt) {
                $dayKey = $createdAt->format('Y-m-d');

                if (array_key_exists($dayKey, $signupsValues)) {
                    $signupsValues[$dayKey]++;
                }
            }

            // Genre : uniquement comptes avec PersonalProfile
            $gender = $this->extractUserGender($user);

            if ($gender !== null && isset($gender7d[$gender])) {
                $gender7d[$gender]++;
            }

            // Rôles : uniquement comptes avec PersonalProfile
            $roles = $user->getRoles();

            if (in_array('ROLE_TALENT', $roles, true)) {
                $roles7d['ROLE_TALENT']++;
            }

            if (in_array('ROLE_PARTICULIER', $roles, true)) {
                $roles7d['ROLE_PARTICULIER']++;
            }

            if (in_array('ROLE_COMPANY', $roles, true)) {
                $roles7d['ROLE_COMPANY']++;
            }
        }

        // ✅ DQL depuis UserRepository
        $lastUsersEntities = $userRepository->findLastCompletedUsers(4);

        $lastUsers = array_map(function (User $user) {
            return [
                'id' => $user->getId(),
                'name' => method_exists($user, 'getFullName') && $user->getFullName()
                    ? $user->getFullName()
                    : $user->getEmail(),
                'email' => $user->getEmail(),
                'roles' => $user->getRoles(),
                'createdAt' => $user->getCreatedAt()
                    ? $user->getCreatedAt()->format('d/m/Y H:i')
                    : null,
                'avatar' => method_exists($user, 'getPersonalProfile')
                    ? $user->getPersonalProfile()?->getPhoto()
                    : null,
            ];
        }, $lastUsersEntities);

        return [
            'signups7d' => [
                'labels' => array_values($labels),
                'values' => array_values($signupsValues),
            ],
            'gender7d' => $gender7d,
            'roles7d' => $roles7d,
            'lastUsers' => $lastUsers,
        ];
    }

    private function extractUserGender(User $user): ?string
    {
        $profile = $user->getPersonalProfile();

        // ✅ Pas de PersonalProfile = compte incomplet, on ne compte pas dans F/M/PM
        if (!$profile) {
            return null;
        }

        // ✅ PersonalProfile existe mais sexe_id vide = PM
        if (!method_exists($profile, 'getSexe') || !$profile->getSexe()) {
            return 'PM';
        }

        // ✅ PersonalProfile existe avec sexe_id rempli = F ou M
        return $this->normalizeGenderValue($profile->getSexe());
    }

    private function normalizeGenderValue(mixed $value): string
    {
        if ($value === null) {
            return 'PM';
        }

        // ✅ Si c'est une entité App\Entity\Sexe
        if (is_object($value)) {
            $text = null;

            foreach (['getSexe', 'getNom', 'getLibelle', 'getLabel', 'getName', 'getTitre', 'getCode', 'getSlug'] as $method) {
                if (method_exists($value, $method)) {
                    $text = $value->$method();
                    break;
                }
            }

            if ($text === null || is_object($text)) {
                return 'PM';
            }

            $value = $text;
        }

        $value = strtoupper(trim((string) $value));

        return match ($value) {
            'M',
            'HOMME',
            'MASCULIN',
            'MALE',
            'MAN',
            'GARCON',
            'GARÇON' => 'M',

            'F',
            'FEMME',
            'FEMININ',
            'FÉMININ',
            'FEMALE',
            'WOMAN',
            'FILLE' => 'F',

            default => 'PM',
        };
    }

    private function hasCompletedPersonalProfile(User $user): bool
    {
        return method_exists($user, 'getPersonalProfile') && $user->getPersonalProfile() !== null;
    }
}
