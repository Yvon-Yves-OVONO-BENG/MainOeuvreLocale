<?php
// src/Controller/Moderateur/DirectoryController.php

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
class DirectoryController extends AbstractController
{
    private function renderDirectory(
        Request $request,
        UserRepository $userRepo,
        string $scope,
        string $title,
        string $routeName
    ): Response {
        $q      = trim((string) $request->query->get('q', ''));
        $page   = max(1, (int) $request->query->get('page', 1));
        $limit  = min(50, max(10, (int) $request->query->get('limit', 10)));
        $offset = ($page - 1) * $limit;

        $res   = $userRepo->findForModeratorDirectory($scope, $q, $limit, $offset);
        $total = $res['total'];
        $pages = max(1, (int) ceil($total / $limit));

        return $this->render('moderateur/users_list.html.twig', [
            'title' => $title,
            'scope' => $scope,
            'routeName' => $routeName,

            'items' => $res['items'],
            'total' => $total,
            'page'  => $page,
            'pages' => $pages,
            'limit' => $limit,
            'q'     => $q,
        ]);
    }

    #[Route('/users', name: 'users_index', methods: ['GET'])]
    public function users(Request $request, UserRepository $userRepo): Response
    {
        return $this->renderDirectory($request, $userRepo, 'all', '👥 Tous les utilisateurs', 'moderateur_users_index');
    }

    #[Route('/companies', name: 'companies_index', methods: ['GET'])]
    public function companies(Request $request, UserRepository $userRepo): Response
    {
        return $this->renderDirectory($request, $userRepo, 'company', '🏢 Compagnies', 'moderateur_companies_index');
    }

    #[Route('/particuliers', name: 'particuliers_index', methods: ['GET'])]
    public function particuliers(Request $request, UserRepository $userRepo): Response
    {
        return $this->renderDirectory($request, $userRepo, 'particulier', '🧑‍💼 Particuliers', 'moderateur_particuliers_index');
    }

    #[Route('/talents', name: 'talents_index', methods: ['GET'])]
    public function talents(Request $request, UserRepository $userRepo): Response
    {
        return $this->renderDirectory($request, $userRepo, 'talent', '🧑‍💼 Talents', 'moderateur_talents_index');
    }
}