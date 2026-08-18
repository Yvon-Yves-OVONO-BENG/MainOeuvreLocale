<?php

namespace App\Controller\Web\Admin;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/permissions', name: 'admin_permissions_')]
#[IsGranted('ROLE_ADMIN')]
class AdminUserRoleController extends AbstractController
{
    private const EDITABLE_ROLES = [
        'ROLE_SUPER_ADMIN',
        'ROLE_ADMIN',
        'ROLE_MODERATEUR',
        'ROLE_COMPANY',
        'ROLE_PARTICULIER',
        'ROLE_TALENT',
    ];

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, UserRepository $userRepository): Response
    {
        $q = trim((string) $request->query->get('q', ''));
        $role = trim((string) $request->query->get('role', ''));
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 10;

        $qb = $userRepository->createQueryBuilder('u')
            ->orderBy('u.id', 'DESC');

        if ($q !== '') {
            $qb->andWhere('u.email LIKE :q')
                ->setParameter('q', '%' . $q . '%');
        }

        if ($role !== '' && in_array($role, self::EDITABLE_ROLES, true)) {
            $qb->andWhere('u.roles LIKE :role')
                ->setParameter('role', '%"' . $role . '"%');
        }

        $query = $qb
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery();

        $paginator = new Paginator($query, false);

        $totalItems = count($paginator);
        $totalPages = max(1, (int) ceil($totalItems / $limit));

        if ($page > $totalPages) {
            $page = $totalPages;
        }

        $stats = [];
        foreach (self::EDITABLE_ROLES as $editableRole) {
            $stats[$editableRole] = $userRepository->countUsersHavingRole($editableRole);
        }

        return $this->render('admin/permissions/permissions.html.twig', [
            'users' => iterator_to_array($paginator),
            'editableRoles' => self::EDITABLE_ROLES,
            'stats' => $stats,
            'filters' => [
                'q' => $q,
                'role' => $role,
            ],
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'totalItems' => $totalItems,
                'totalPages' => $totalPages,
                'hasPrevious' => $page > 1,
                'hasNext' => $page < $totalPages,
                'previousPage' => $page > 1 ? $page - 1 : 1,
                'nextPage' => $page < $totalPages ? $page + 1 : $totalPages,
            ],
        ]);
    }

    #[Route('/{id}/roles', name: 'update_roles', methods: ['POST'])]
    public function updateRoles(
        User $user,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        if (!$this->isCsrfTokenValid(
            'admin_update_roles_' . $user->getId(),
            (string) $request->request->get('_token')
        )) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        $submittedRoles = (array) $request->request->all('roles');
        $submittedRoles = array_values(array_unique(array_intersect($submittedRoles, self::EDITABLE_ROLES)));

        $finalRoles = array_values(array_unique(array_merge(['ROLE_USER'], $submittedRoles)));

        $currentUser = $this->getUser();
        if ($currentUser instanceof User && $currentUser->getId() === $user->getId()) {
            if (!in_array('ROLE_ADMIN', $finalRoles, true) && !in_array('ROLE_SUPER_ADMIN', $finalRoles, true)) {
                $this->addFlash('warning', 'Vous ne pouvez pas vous retirer vous-même tous les droits d’administration.');
                return $this->redirectToRoute('admin_permissions_index');
            }
        }

        $user->setRoles($finalRoles);
        $em->flush();

        $this->addFlash('success', sprintf(
            'Rôles mis à jour pour %s.',
            $user->getEmail() ?? ('#' . $user->getId())
        ));

        return $this->redirectToRoute('admin_permissions_index');
    }
}