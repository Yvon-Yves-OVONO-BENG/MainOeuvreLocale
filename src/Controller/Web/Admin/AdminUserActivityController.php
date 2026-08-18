<?php

namespace App\Controller\Web\Admin;

use App\Entity\User;
use App\Repository\UserRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/users', name: 'admin_users_')]
#[IsGranted('ROLE_ADMIN')]
final class AdminUserActivityController extends AbstractController
{
    private const ONLINE_WINDOW_MINUTES = 15;

    #[Route('/online', name: 'online', methods: ['GET'])]
    public function online(
        Request $request,
        UserRepository $users,
        EntityManagerInterface $entityManager,
    ): Response {
        $metadata = $entityManager->getClassMetadata(User::class);
        $activityField = $metadata->hasField('lastActivityAt')
            ? 'lastActivityAt'
            : ($metadata->hasField('lastLoginAt') ? 'lastLoginAt' : null);

        $queryBuilder = $users->createQueryBuilder('u');
        $since = new DateTimeImmutable(sprintf('-%d minutes', self::ONLINE_WINDOW_MINUTES));

        if ($activityField !== null) {
            $queryBuilder
                ->andWhere(sprintf('u.%s >= :onlineSince', $activityField))
                ->setParameter('onlineSince', $since)
                ->orderBy(sprintf('u.%s', $activityField), 'DESC');
        } else {
            // Le projet ne possède encore aucun champ permettant de mesurer l'activité.
            $queryBuilder->andWhere('1 = 0');
        }

        $this->applySearch($queryBuilder, $request);
        $pagination = $this->paginate($queryBuilder, $request);

        return $this->render('admin/users_online.html.twig', [
            ...$pagination,
            'q' => trim((string) $request->query->get('q', '')),
            'activityField' => $activityField,
            'onlineSince' => $since,
            'onlineWindowMinutes' => self::ONLINE_WINDOW_MINUTES,
        ]);
    }

    #[Route('/signups-24h', name: 'signups_24h', methods: ['GET'])]
    public function signups24h(Request $request, UserRepository $users): Response
    {
        $since = new DateTimeImmutable('-24 hours');

        $queryBuilder = $users->createQueryBuilder('u')
            ->andWhere('u.createdAt >= :registeredSince')
            ->setParameter('registeredSince', $since)
            ->orderBy('u.createdAt', 'DESC');

        $this->applySearch($queryBuilder, $request);
        $pagination = $this->paginate($queryBuilder, $request);

        return $this->render('admin/users_signups_24h.html.twig', [
            ...$pagination,
            'q' => trim((string) $request->query->get('q', '')),
            'registeredSince' => $since,
        ]);
    }

    private function applySearch(QueryBuilder $queryBuilder, Request $request): void
    {
        $search = trim((string) $request->query->get('q', ''));

        if ($search === '') {
            return;
        }

        $queryBuilder
            ->andWhere('LOWER(u.email) LIKE :activitySearch')
            ->setParameter('activitySearch', '%' . mb_strtolower($search) . '%');
    }

    /**
     * @return array{items: array<int, User>, total: int, page: int, pages: int, perPage: int}
     */
    private function paginate(QueryBuilder $queryBuilder, Request $request): array
    {
        $page = max(1, $request->query->getInt('page', 1));
        $perPage = min(100, max(10, $request->query->getInt('perPage', 20)));

        $countQueryBuilder = clone $queryBuilder;
        $total = (int) $countQueryBuilder
            ->resetDQLPart('orderBy')
            ->select('COUNT(u.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);

        $items = $queryBuilder
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        return compact('items', 'total', 'page', 'pages', 'perPage');
    }
}
