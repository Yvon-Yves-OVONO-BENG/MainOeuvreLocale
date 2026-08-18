<?php
// src/Controller/Moderateur/SanctionsController.php

namespace App\Controller\Web\Moderateur;

use App\Repository\SanctionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/moderateur/sanctions', name: 'moderateur_sanctions_')]
#[IsGranted(new Expression('is_granted("ROLE_MODERATEUR") or is_granted("ROLE_ADMIN") or is_granted("ROLE_SUPER_ADMIN")'))]
class SanctionsController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, SanctionRepository $repo): Response
    {
        $q = trim((string)$request->query->get('q',''));
        $type = (string)$request->query->get('type','all');
        $days = max(7, min(180, (int)$request->query->get('days', 30)));

        $page = max(1, (int)$request->query->get('page', 1));
        $limit = min(50, max(10, (int)$request->query->get('limit', 10)));
        $offset = ($page - 1) * $limit;

        $res = $repo->findForIndex($q, $type, $days, $limit, $offset);
        $stats = $repo->stats($days);

        return $this->render('moderateur/sanctions.html.twig', [
            'items' => $res['items'],
            'total' => $res['total'],
            'page' => $page,
            'pages' => max(1, (int)ceil($res['total'] / $limit)),
            'limit' => $limit,
            'q' => $q,
            'type' => $type,
            'days' => $days,
            'stats' => $stats,
        ]);
    }
}