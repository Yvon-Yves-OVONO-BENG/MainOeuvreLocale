<?php
// src/Controller/Moderateur/SuspectsContentController.php

namespace App\Controller\Web\Moderateur;

use App\Repository\JobRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/moderateur/content', name: 'moderateur_content_')]
#[IsGranted(new Expression('is_granted("ROLE_MODERATEUR") or is_granted("ROLE_ADMIN") or is_granted("ROLE_SUPER_ADMIN")'))]
class SuspectsContentController extends AbstractController
{
    #[Route('/suspects', name: 'suspects', methods: ['GET'])]
    public function suspects(Request $request, JobRepository $jobRepo): Response
    {
        $q = trim((string)$request->query->get('q', ''));
        $page = max(1, (int)$request->query->get('page', 1));
        $limit = min(50, max(10, (int)$request->query->get('limit', 10)));
        $offset = ($page - 1) * $limit;

        $res = $jobRepo->findSuspectJobs($q, $limit, $offset);
        $pages = max(1, (int)ceil($res['total'] / $limit));

        return $this->render('moderateur/content_suspects.html.twig', [
            'items' => $res['items'],
            'total' => $res['total'],
            'page' => $page,
            'pages' => $pages,
            'limit' => $limit,
            'q' => $q,
        ]);
    }
}