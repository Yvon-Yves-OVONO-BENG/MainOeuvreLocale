<?php
// src/Controller/Moderateur/AnalyticsSnapshotController.php

namespace App\Controller\Web\Moderateur;

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/moderateur', name: 'moderateur_')]
#[IsGranted(new Expression('is_granted("ROLE_MODERATEUR") or is_granted("ROLE_ADMIN") or is_granted("ROLE_SUPER_ADMIN")'))]
class AnalyticsSnapshotController extends AbstractController
{
    #[Route('/snapshot', name: 'snapshot', methods: ['GET'])]
    public function snapshot(UserRepository $userRepo): Response
    {
        $s = $userRepo->statsNewUsers7d();

        return $this->render('moderateur/_snapshot_kpis.html.twig', [
            'analytics' => $s['analytics'],
            'roles7d' => $s['roles7d'],
        ]);
    }

    #[Route('/users', name: 'users_range', methods: ['GET'])]
    public function usersRange(Request $request, UserRepository $userRepo): Response
    {
        $range = (string) $request->query->get('range', '');
        $role  = (string) $request->query->get('role', ''); // ROLE_TALENT / ROLE_PARTICULIER / ROLE_COMPANY
        $q     = trim((string) $request->query->get('q', ''));

        $page  = max(1, (int) $request->query->get('page', 1));
        $limit = min(50, max(10, (int) $request->query->get('limit', 10)));
        $offset = ($page - 1) * $limit;

        $res = $userRepo->findUsersRange($range, $role, $q, $limit, $offset);

        return $this->render('moderateur/users_range.html.twig', [
            'items' => $res['items'],
            'total' => $res['total'],
            'page'  => $page,
            'pages' => max(1, (int)ceil($res['total'] / $limit)),
            'limit' => $limit,
            'q'     => $q,
            'range' => $range,
            'role'  => $role,
        ]);
    }
}