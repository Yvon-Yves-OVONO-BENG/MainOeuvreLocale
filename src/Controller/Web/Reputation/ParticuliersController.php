<?php

namespace App\Controller\Web\Reputation;

use App\Entity\User;
use App\Repository\RatingRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ParticuliersController extends AbstractController
{
    /**
     * Affiche uniquement les particuliers actifs possédant un nom public et une photo réels.
     */
    #[Route('/reputation/particuliers', name: 'reputation_particuliers')]
    public function index(
        Request $request,
        UserRepository $userRepository,
        RatingRepository $ratingRepository
    ): Response {

        $q = trim((string) $request->query->get('q', ''));
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = 10;

        $qb = $userRepository->createQueryBuilder('u')

            // Un particulier public doit posséder un PersonalProfile.
            ->innerJoin('u.personalProfile', 'pp')
            ->addSelect('pp')

            ->leftJoin('u.country', 'c')
            ->addSelect('c')

            ->andWhere('u.isActive = 1')
            // Ne jamais exposer un profil public incomplet.
            ->andWhere('pp.fullName IS NOT NULL')
            ->andWhere("TRIM(pp.fullName) NOT IN ('', '-', '—')")
            ->andWhere("LOWER(TRIM(pp.fullName)) <> 'profil sans nom'")
            ->andWhere("pp.fullName NOT LIKE '%@%'")
            ->andWhere('LOWER(pp.fullName) <> LOWER(u.email)')
            ->andWhere('pp.photo IS NOT NULL')
            ->andWhere("TRIM(pp.photo) <> ''")
            ->andWhere("LOWER(pp.photo) NOT IN ('avatar.png', 'default-avatar.png', 'default.png')")
            ->andWhere("LOWER(pp.photo) NOT LIKE '%/avatar.png'")
            ->andWhere("LOWER(pp.photo) NOT LIKE '%/default-avatar.png'")
            ->andWhere("LOWER(pp.photo) NOT LIKE '%/default.png'")
            ->andWhere('u.roles LIKE :role')
            ->setParameter('role', '%ROLE_PARTICULIER%')

            // ✅ Moyenne des avis
            ->addSelect('(
                SELECT COALESCE(AVG(r2.globalScore), 0)
                FROM App\Entity\Review r2
                WHERE r2.target = u
                  AND r2.published = true
            ) AS HIDDEN avgRating');

        if ($q !== '') {
            $qb->andWhere(
                'LOWER(pp.fullName) LIKE :q'
            )
            ->setParameter('q', '%' . mb_strtolower($q) . '%');
        }

        // ✅ Les mieux notés en premier
        $qb->addOrderBy('avgRating', 'DESC');
        $qb->addOrderBy('u.id', 'DESC');

        $total = count($qb->getQuery()->getResult());

        $users = $qb
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $userIds = array_map(
            fn(User $u) => $u->getId(),
            $users
        );

        $ratingsStats = $userIds
            ? $ratingRepository->getStatsForUsers($userIds)
            : [];

        return $this->render('liste_talents/liste_talents.html.twig', [
            'talents' => $users,
            'pagination' => [
                'page' => $page,
                'pages' => (int) ceil($total / $limit),
                'total' => $total,
            ],
            'ratingsStats' => $ratingsStats,
            'q' => $q,
            'sort' => 'new',
            'pageTitle' => 'Particuliers',
            'listingType' => 'particulier',
        ]);
    }
}
