<?php

namespace App\Controller\Web\Moderateur;

use App\Repository\JobRepository;
use App\Repository\ReportJobRepository;
use App\Repository\ReportRepository;
use App\Repository\UserRepository;
use App\Repository\WatchlistItemRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/moderateur/users/watchlist', name: 'moderateur_watchlist_')]
#[IsGranted(new Expression('is_granted("ROLE_MODERATEUR") or is_granted("ROLE_ADMIN") or is_granted("ROLE_SUPER_ADMIN")'))]
class WatchlistController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(
        Request $request,
        WatchlistItemRepository $watchRepo,
        UserRepository $userRepo
    ): Response {
        $tab   = (string) $request->query->get('tab', 'manual'); // manual | auto
        $q     = trim((string) $request->query->get('q', ''));
        $role  = (string) $request->query->get('role', '');
        $page  = max(1, (int) $request->query->get('page', 1));
        $limit = min(50, max(10, (int) $request->query->get('limit', 10)));
        $offset = ($page - 1) * $limit;

        // Auto config
        $days = max(7, min(180, (int)$request->query->get('days', 30)));
        $tr   = max(1, min(50, (int)$request->query->get('tr', 3))); // reports comptes open >= tr
        $tj   = max(1, min(50, (int)$request->query->get('tj', 2))); // job reports pending/reviewed >= tj

        $manualActiveCount = $watchRepo->countActive();

        if ($tab === 'auto') {
            $res = $userRepo->findAutoWatchlistCandidates($q, $role, $days, $tr, $tj, $limit, $offset);

            return $this->render('moderateur/users_watchlist.html.twig', [
                'tab' => 'auto',
                'q' => $q, 'role' => $role, 'page' => $page, 'limit' => $limit,
                'total' => $res['total'], 'pages' => max(1, (int)ceil($res['total'] / $limit)),
                'manualActiveCount' => $manualActiveCount,
                'rows' => $res['items'],
                'auto' => ['days'=>$days,'tr'=>$tr,'tj'=>$tj],
            ]);
        }

        // manual tab
        $active = (string)$request->query->get('active', '1') === '1';
        $res = $watchRepo->findForIndex($q, $role, $active, $limit, $offset);

        return $this->render('moderateur/users_watchlist.html.twig', [
            'tab' => 'manual',
            'active' => $active,
            'q' => $q, 'role' => $role, 'page' => $page, 'limit' => $limit,
            'total' => $res['total'], 'pages' => max(1, (int)ceil($res['total'] / $limit)),
            'manualActiveCount' => $manualActiveCount,
            'rows' => $res['items'],
            'auto' => ['days'=>$days,'tr'=>$tr,'tj'=>$tj],
        ]);
    }

    #[Route('/add/{id}', name: 'add', requirements: ['id'=>'\d+'], methods: ['POST'])]
    public function add(int $id, Request $request, WatchlistItemRepository $watchRepo, EntityManagerInterface $em): JsonResponse
    {
        $payload = json_decode((string)$request->getContent(), true) ?: [];
        if (!$this->isCsrfTokenValid('watch_add_'.$id, (string)($payload['_token'] ?? ''))) {
            return $this->json(['ok'=>false,'message'=>'CSRF invalide'], 403);
        }

        $reason = trim((string)($payload['reason'] ?? '')) ?: null;

        /**
         * @var User
         */
        $me = $this->getUser();

        $watchRepo->upsertAdd($id, $reason, (int)$me->getId());
        $em->flush();

        return $this->json(['ok'=>true]);
    }

    #[Route('/item/{id}/toggle', name: 'toggle', requirements: ['id'=>'\d+'], methods: ['POST'])]
    public function toggle(int $id, Request $request, WatchlistItemRepository $watchRepo): JsonResponse
    {
        $payload = json_decode((string)$request->getContent(), true) ?: [];
        if (!$this->isCsrfTokenValid('watch_toggle_'.$id, (string)($payload['_token'] ?? ''))) {
            return $this->json(['ok'=>false,'message'=>'CSRF invalide'], 403);
        }

        $active = (bool)($payload['active'] ?? false);
        $watchRepo->toggleActive($id, $active);

        return $this->json(['ok'=>true]);
    }

    #[Route('/item/{id}/note', name: 'note', requirements: ['id'=>'\d+'], methods: ['POST'])]
    public function note(int $id, Request $request, WatchlistItemRepository $watchRepo): JsonResponse
    {
        $payload = json_decode((string)$request->getContent(), true) ?: [];
        if (!$this->isCsrfTokenValid('watch_note_'.$id, (string)($payload['_token'] ?? ''))) {
            return $this->json(['ok'=>false,'message'=>'CSRF invalide'], 403);
        }

        $reason = trim((string)($payload['reason'] ?? '')) ?: null;
        $watchRepo->updateReason($id, $reason);

        return $this->json(['ok'=>true]);
    }

    #[Route('/item/{id}', name: 'show', requirements: ['id'=>'\d+'], methods: ['GET'])]
    public function show(
        int $id,
        WatchlistItemRepository $watchRepo,
        ReportRepository $reportRepo,
        ReportJobRepository $reportJobRepo,
        JobRepository $jobRepo
    ): Response {
        $w = $watchRepo->findOneWithUserProfiles($id);
        if (!$w) throw $this->createNotFoundException('Watchlist item introuvable.');

        $u  = $w->getTargetUser();
        $uid = (int) $u->getId();

        $stats = [
            'reports30d'    => $reportRepo->statsForTargetUser($uid, 30),
            'jobReports30d' => $reportJobRepo->statsForJobOwner($uid, 30),
            'jobs30d'       => $jobRepo->countJobsCreatedBy($uid, 30),
            'lastJobs'      => $jobRepo->lastJobsCreatedBy($uid, 5),
        ];

        return $this->render('moderateur/user_watchlist_show.html.twig', [
            'w' => $w,
            'u' => $u,
            'pp' => $u->getPersonalProfile(),
            'pro' => $u->getProfessionalProfile(),
            'stats' => $stats,
        ]);
    }
}