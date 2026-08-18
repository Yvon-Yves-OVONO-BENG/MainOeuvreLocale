<?php

namespace App\Repository;

use App\Entity\Rating;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class RatingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Rating::class);
    }

    public function findUserVoteForTalent(int $ratingById, int $talentUserId): ?Rating
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.ratingBy = :by')->setParameter('by', $ratingById)
            ->andWhere('r.user = :u')->setParameter('u', $talentUserId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function getStatsForTalent(int $talentUserId): array
    {
        return $this->createQueryBuilder('r')
            ->select('COUNT(DISTINCT r.ratingBy) as voters, COALESCE(AVG(r.stars), 0) as avg')
            ->andWhere('r.user = :u')
            ->setParameter('u', $talentUserId)
            ->getQuery()
            ->getSingleResult();
}

    /**
     * Stats groupées pour une liste de talents (1 seule requête)
     * retourne: [profileId => ['avg'=>..., 'voters'=>...]]
     */
    public function getStatsForTalents(array $userIds): array
    {
        if (!$userIds) {
            return [];
        }

        $rows = $this->createQueryBuilder('r')
            ->select('IDENTITY(r.user) AS uid, COUNT(r.id) AS voters, COALESCE(AVG(r.stars), 0) AS avg')
            ->andWhere('r.user IN (:ids)')
            ->setParameter('ids', $userIds)
            ->groupBy('uid')
            ->getQuery()
            ->getArrayResult();

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row['uid']] = [
                'avg' => (float) $row['avg'],
                'voters' => (int) $row['voters'],
            ];
        }

        return $out;
    }

    /**
     * Retourne:
     *  - average: ?float (arrondi à 1 décimale)
     *  - count: int (nombre de votes)
     */
    public function getStatsForProfile(User $targetUser): array
    {
        $row = $this->createQueryBuilder('rt')
            ->select('AVG(rt.stars) AS avgStars', 'COUNT(rt.id) AS cnt')
            ->andWhere('rt.user = :u')
            ->setParameter('u', $targetUser)
            ->getQuery()
            ->getOneOrNullResult();

        $average = isset($row['avgStars']) && $row['avgStars'] !== null
            ? round((float) $row['avgStars'], 1)
            : null;

        $count = (int) ($row['cnt'] ?? 0);

        return [
            'average' => $average,
            'count' => $count,
        ];
    }

    /**
     * Liste des votes reçus par l'utilisateur (mon profil), avec le voter (ratingBy) préchargé
     * @param User $me
     * @return array
     */
    public function findRatingsForUser(User $me): array
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.ratingBy', 'rb')->addSelect('rb')
            ->andWhere('r.user = :me')
            ->setParameter('me', $me)
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte le nombre de votes reçus par l'utilisateur (mon profil)
     *
     * @param User $me
     * @return int
     */
    public function countRatingsForUser(User $me): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.user = :me')
            ->setParameter('me', $me)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Calcule la moyenne des étoiles reçues par l'utilisateur (mon profil)
     *
     * @param User $me
     * @return float
     */
    public function averageStarsForUser(User $me): float
    {
        return (float) $this->createQueryBuilder('r')
            ->select('COALESCE(AVG(r.stars), 0)')
            ->andWhere('r.user = :me')
            ->setParameter('me', $me)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Vérifie si un utilisateur a déjà voté pour un autre utilisateur
     *
     * @param int $ratingById ID de l'utilisateur qui a voté
     * @param int $ratedUserId ID de l'utilisateur qui a été voté
     * @return Rating|null
     */
    public function findUserVoteForUser(int $ratingById, int $ratedUserId): ?Rating
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.ratingBy = :rb')
            ->andWhere('r.user = :u')
            ->setParameter('rb', $ratingById)
            ->setParameter('u', $ratedUserId)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Stats pour un utilisateur (moyenne + nombre de votants)
     *
     * @param int $ratedUserId
     * @return array ['avg' => float, 'voters' => int]
     */
    public function getStatsForUser(int $ratedUserId): array
    {
        $row = $this->createQueryBuilder('r')
            ->select('COALESCE(AVG(r.stars), 0) AS avg', 'COUNT(r.id) AS voters')
            ->andWhere('r.user = :u')
            ->setParameter('u', $ratedUserId)
            ->getQuery()
            ->getOneOrNullResult();

        return [
            'avgRating' => (float) ($row['avg'] ?? 0),
            'voters' => (int) ($row['voters'] ?? 0),
        ];
    }


    public function getStatsForUsers(array $userIds): array
    {
        if (!$userIds) return [];

        $rows = $this->createQueryBuilder('r')
            ->select('IDENTITY(r.user) AS userId')
            ->addSelect('AVG(r.stars) AS avgStars')
            ->addSelect('COUNT(r.id) AS voters')
            ->andWhere('r.user IN (:ids)')
            ->setParameter('ids', $userIds)
            ->groupBy('r.user')
            ->getQuery()
            ->getArrayResult();

        $map = [];
        foreach ($rows as $row) {
            $uid = (int) $row['userId'];
            $map[$uid] = [
                'avg'    => (float) $row['avgStars'],
                'voters' => (int) $row['voters'],
            ];
        }

        return $map;
    }

    public function avgStarsFor(User $target): float
    {
        $avg = $this->createQueryBuilder('r')
            ->select('COALESCE(AVG(r.stars), 0)')
            ->andWhere('r.user = :u')
            ->setParameter('u', $target)
            ->getQuery()
            ->getSingleScalarResult();

        return (float) $avg;
    }

    public function countFor(User $target): int
    {
        $count = $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.user = :u')
            ->setParameter('u', $target)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count;
    }

    public function findMine(User $target, User $me): ?Rating
    {
        return $this->findOneBy(['user' => $target, 'ratingBy' => $me]);
    }


}
