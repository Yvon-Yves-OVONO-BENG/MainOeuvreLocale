<?php
// src/Controller/Admin/AdminUsersController.php

namespace App\Controller\Web\Admin;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response, JsonResponse};
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/users', name: 'admin_users_')]
#[IsGranted('ROLE_ADMIN')]
class AdminUsersController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, UserRepository $repo): Response
    {
        $filters = [
            'q'      => trim((string) $request->query->get('q', '')),
            'role'   => trim((string) $request->query->get('role', '')),
            'status' => trim((string) $request->query->get('status', '')),
            'sort'   => (string) $request->query->get('sort', 'createdAt'),
            'dir'    => strtoupper((string) $request->query->get('dir', 'DESC')) === 'ASC' ? 'ASC' : 'DESC',
        ];

        $page    = max(1, (int) $request->query->get('page', 1));
        $perPage = min(100, max(10, (int) $request->query->get('perPage', 20)));
        $offset  = ($page - 1) * $perPage;

        $qb = $repo->qbAdminSearch($filters);

        $total = (int) (clone $qb)->select('COUNT(u.id)')->getQuery()->getSingleScalarResult();
        $items = $qb->setFirstResult($offset)->setMaxResults($perPage)->getQuery()->getResult();

        $pages = max(1, (int) ceil($total / $perPage));

        return $this->render('admin/users_index.html.twig', [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'perPage' => $perPage,
            'filters' => $filters,
            'rolesList' => ['ROLE_ADMIN','ROLE_MODERATOR','ROLE_TALENT','ROLE_COMPANY','ROLE_PARTICULIER'],
            'csrf' => $this->container->get('security.csrf.token_manager')->getToken('admin_users')->getValue(),
        ]);
    }

    #[Route('/{slug}', name: 'show', requirements: ['slug' => '[a-f0-9]{64}'], methods: ['GET'])]
    public function show(
        #[MapEntity(mapping: ['slug' => 'slug'])]
        User $user
    ): Response
    {
        return $this->render('admin/users_show.html.twig', ['u' => $user]);
    }

    #[Route('/{slug}/toggle-active', name: 'toggle_active', requirements: ['slug' => '[a-f0-9]{64}'], methods: ['POST'])]
    public function toggleActive(
        #[MapEntity(mapping: ['slug' => 'slug'])]
        User $user,
        Request $request,
        EntityManagerInterface $em
    ): JsonResponse
    {
        $csrf = (string) $request->headers->get('X-CSRF-TOKEN', '');
        if (!$this->isCsrfTokenValid('admin_users', $csrf)) {
            return $this->json(['ok' => false, 'error' => 'CSRF invalide'], 403);
        }

        $user->setIsActive(!$user->isActive()); // adapte si ton getter s’appelle différemment
        $em->flush();

        return $this->json(['ok' => true, 'isActive' => $user->isActive()]);
    }
}
