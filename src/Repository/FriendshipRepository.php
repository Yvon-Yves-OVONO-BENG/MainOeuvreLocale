<?php

namespace App\Repository;

use App\Entity\Friendship;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Friendship>
 */
class FriendshipRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Friendship::class);
    }

    // =========================================================
    // 1) TROUVER LA RELATION ENTRE DEUX USERS (dans les 2 sens)
    // =========================================================
    public function findBetweenUsers(User $u1, User $u2): ?Friendship
    {
        return $this->createQueryBuilder('f')
            ->andWhere('(f.requester = :u1 AND f.addressee = :u2) OR (f.requester = :u2 AND f.addressee = :u1)')
            ->setParameter('u1', $u1)
            ->setParameter('u2', $u2)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    // =========================================================
    // DEMANDES RECUES → USERS DIRECTEMENT (ULTRA PRATIQUE UI)
    // =========================================================
    public function findIncomingPendingUsers(User $me, int $limit = 100): array
    {
        return $this->createQueryBuilder('f')
            ->select('f, r AS user')   // ✅ IMPORTANT
            ->innerJoin('f.requester', 'r')
            ->andWhere('f.status = :pending')
            ->andWhere('f.addressee = :me')
            ->setParameter('pending', Friendship::STATUS_PENDING)
            ->setParameter('me', $me)
            ->orderBy('f.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }


    public function countPendingForUser(User $me): int
    {
        return (int) $this->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->andWhere('f.status = :pending')
            ->andWhere('f.addressee = :me')
            ->setParameter('pending', Friendship::STATUS_PENDING)
            ->setParameter('me', $me)
            ->getQuery()
            ->getSingleScalarResult();
    }


    // =========================================================
    // AMIS → USERS + ID FRIENDSHIP (PARFAIT POUR ACTIONS)
    // =========================================================
    public function findFriendsWithMeta(User $me, int $limit = 100): array
    {
        return $this->createQueryBuilder('f')
            ->select('
                CASE WHEN f.requester = :me THEN a ELSE r END AS user,
                f.id AS friendshipId,
                f.createdAt
            ')
            ->innerJoin('f.requester', 'r')
            ->innerJoin('f.addressee', 'a')
            ->andWhere('f.status = :accepted')
            ->andWhere('r = :me OR a = :me')
            ->setParameter('accepted', Friendship::STATUS_ACCEPTED)
            ->setParameter('me', $me)
            ->orderBy('f.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function existsBetweenUsers(User $u1, User $u2): bool
    {
        $count = (int) $this->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->andWhere('(f.requester = :u1 AND f.addressee = :u2) OR (f.requester = :u2 AND f.addressee = :u1)')
            ->setParameter('u1', $u1)
            ->setParameter('u2', $u2)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    // =========================================================
    // 2) STATUT AMITIÉ
    // =========================================================
    public function areFriends(User $u1, User $u2): bool
    {
        $count = (int) $this->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->andWhere('f.status = :accepted')
            ->andWhere('(f.requester = :u1 AND f.addressee = :u2) OR (f.requester = :u2 AND f.addressee = :u1)')
            ->setParameter('accepted', Friendship::STATUS_ACCEPTED)
            ->setParameter('u1', $u1)
            ->setParameter('u2', $u2)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    public function hasPendingRequest(User $from, User $to): bool
    {
        $count = (int) $this->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->andWhere('f.status = :pending')
            ->andWhere('f.requester = :from')
            ->andWhere('f.addressee = :to')
            ->setParameter('pending', Friendship::STATUS_PENDING)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    public function hasAnyPendingBetween(User $u1, User $u2): bool
    {
        $count = (int) $this->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->andWhere('f.status = :pending')
            ->andWhere('(f.requester = :u1 AND f.addressee = :u2) OR (f.requester = :u2 AND f.addressee = :u1)')
            ->setParameter('pending', Friendship::STATUS_PENDING)
            ->setParameter('u1', $u1)
            ->setParameter('u2', $u2)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    public function getStatusBetween(User $u1, User $u2): ?string
    {
        $row = $this->createQueryBuilder('f')
            ->select('f.status AS status')
            ->andWhere('(f.requester = :u1 AND f.addressee = :u2) OR (f.requester = :u2 AND f.addressee = :u1)')
            ->setParameter('u1', $u1)
            ->setParameter('u2', $u2)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $row['status'] ?? null;
    }

    // =========================================================
    // 3) LISTE DES AMIS (Friendship rows)
    // =========================================================
    public function findAcceptedForUser(User $me, int $limit = 100, int $offset = 0): array
    {
        $limit = max(1, min(500, $limit));
        $offset = max(0, $offset);

        return $this->createQueryBuilder('f')
            ->andWhere('f.status = :accepted')
            ->andWhere('f.requester = :me OR f.addressee = :me')
            ->setParameter('accepted', Friendship::STATUS_ACCEPTED)
            ->setParameter('me', $me)
            ->orderBy('f.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    // =========================================================
    // 4) LISTE DES AMIS (directement les Users "autres")
    // =========================================================
    public function findFriendsUsers(User $me, int $limit = 100, int $offset = 0): array
    {
        $limit = max(1, min(500, $limit));
        $offset = max(0, $offset);

        // On retourne le "friend user" = celui qui n’est pas $me
        // DQL CASE WHEN
        return $this->createQueryBuilder('f')
            ->select('CASE WHEN f.requester = :me THEN a ELSE r END AS friend')
            ->innerJoin('f.requester', 'r')
            ->innerJoin('f.addressee', 'a')
            ->andWhere('f.status = :accepted')
            ->andWhere('r = :me OR a = :me')
            ->setParameter('accepted', Friendship::STATUS_ACCEPTED)
            ->setParameter('me', $me)
            ->orderBy('f.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
        // ⚠️ Doctrine retourne ici un tableau de tableaux: [ ['friend' => User], ... ]
        // Si tu veux un tableau de User direct, utilise la méthode normalize ci-dessous.
    }

    public function findFriendsUsersFlat(User $me, int $limit = 100): array
    {
        return $this->getEntityManager()->createQuery('
            SELECT u
            FROM App\Entity\Friendship f
            JOIN f.requester r
            JOIN f.addressee a
            JOIN App\Entity\User u
            WHERE f.status = :accepted
            AND (
                (r = :me AND u = a)
                OR
                (a = :me AND u = r)
            )
            ORDER BY f.createdAt DESC
        ')
        ->setParameter('accepted', Friendship::STATUS_ACCEPTED)
        ->setParameter('me', $me)
        ->setMaxResults($limit)
        ->getResult();
    }


    // =========================================================
    // 5) DEMANDES RECUES (pending)
    // =========================================================
    public function findIncomingPending(User $me, int $limit = 100, int $offset = 0): array
    {
        $limit = max(1, min(500, $limit));
        $offset = max(0, $offset);

        return $this->createQueryBuilder('f')
            ->andWhere('f.status = :pending')
            ->andWhere('f.addressee = :me')
            ->setParameter('pending', Friendship::STATUS_PENDING)
            ->setParameter('me', $me)
            ->orderBy('f.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    // =========================================================
    // 6) DEMANDES ENVOYÉES (pending)
    // =========================================================
    public function findOutgoingPending(User $me, int $limit = 100, int $offset = 0): array
    {
        $limit = max(1, min(500, $limit));
        $offset = max(0, $offset);

        return $this->createQueryBuilder('f')
            ->andWhere('f.status = :pending')
            ->andWhere('f.requester = :me')
            ->setParameter('pending', Friendship::STATUS_PENDING)
            ->setParameter('me', $me)
            ->orderBy('f.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    // =========================================================
    // 7) COUNTS (dashboard)
    // =========================================================
    public function countFriends(User $me): int
    {
        return (int) $this->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->andWhere('f.status = :accepted')
            ->andWhere('f.requester = :me OR f.addressee = :me')
            ->setParameter('accepted', Friendship::STATUS_ACCEPTED)
            ->setParameter('me', $me)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countIncomingPending(User $me): int
    {
        return (int) $this->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->andWhere('f.status = :pending')
            ->andWhere('f.addressee = :me')
            ->setParameter('pending', Friendship::STATUS_PENDING)
            ->setParameter('me', $me)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countOutgoingPending(User $me): int
    {
        return (int) $this->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->andWhere('f.status = :pending')
            ->andWhere('f.requester = :me')
            ->setParameter('pending', Friendship::STATUS_PENDING)
            ->setParameter('me', $me)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
