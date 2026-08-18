<?php

namespace App\Controller\Web\Moderateur;

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/moderateur/users', name: 'moderateur_users_')]
#[IsGranted(new Expression('is_granted("ROLE_MODERATEUR") or is_granted("ROLE_ADMIN") or is_granted("ROLE_SUPER_ADMIN")'))]
class UsersController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, UserRepository $userRepo): Response
    {
        $filter = (string) $request->query->get('filter', '');
        $q      = trim((string) $request->query->get('q', ''));
        $role   = (string) $request->query->get('role', '');
        $page   = max(1, (int) $request->query->get('page', 1));
        $limit  = min(50, max(10, (int) $request->query->get('limit', 10)));
        $offset = ($page - 1) * $limit;

        $res = $userRepo->findUsersForModerator($filter, $q, $role, $limit, $offset);

        return $this->render('moderateur/users.html.twig', [
            'items' => $res['items'],
            'total' => $res['total'],
            'page'  => $page,
            'pages' => max(1, (int)ceil($res['total'] / $limit)),
            'limit' => $limit,
            'q'     => $q,
            'role'  => $role,
            'filter'=> $filter,
        ]);
    }
}