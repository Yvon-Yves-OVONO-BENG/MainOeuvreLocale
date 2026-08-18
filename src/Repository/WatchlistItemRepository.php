<?php

namespace App\Repository;

use App\Entity\User;
use App\Entity\WatchlistItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class WatchlistItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WatchlistItem::class);
    }

    public function countActive(): int
    {
        return (int) $this->createQueryBuilder('w')
            ->select('COUNT(w.id)')
            ->andWhere('w.isActive = true')
            ->getQuery()->getSingleScalarResult();
    }

    public function findForIndex(string $q, string $role, bool $active, int $limit, int $offset): array
    {
        $qb = $this->createQueryBuilder('w')
            ->leftJoin('w.targetUser', 'u')->addSelect('u')
            ->leftJoin('u.personalProfile', 'pp')->addSelect('pp')
            ->leftJoin('u.professionalProfile', 'pro')->addSelect('pro')
            ->andWhere('w.isActive = :a')->setParameter('a', $active)
            ->orderBy('w.createdAt', 'DESC');

        if ($q !== '') {
            $qb->andWhere('u.email LIKE :q OR u.phone LIKE :q OR pp.fullName LIKE :q OR pp.city LIKE :q OR pp.adress LIKE :q OR w.reason LIKE :q')
               ->setParameter('q', '%'.$q.'%');
        }

        if ($role === 'talent') {
            $qb->andWhere('u.roles LIKE :rt')->setParameter('rt', '%"ROLE_TALENT"%');
        } elseif ($role === 'particulier') {
            $qb->andWhere('u.roles LIKE :rp')->setParameter('rp', '%"ROLE_PARTICULIER"%');
        } elseif ($role === 'company') {
            $qb->andWhere('u.roles LIKE :rc')->setParameter('rc', '%"ROLE_COMPANY"%');
        }

        $items = $qb->setFirstResult($offset)->setMaxResults($limit)->getQuery()->getResult();
        $total = (int) (clone $qb)->select('COUNT(DISTINCT w.id)')->getQuery()->getSingleScalarResult();

        return ['items' => $items, 'total' => $total];
    }

    public function upsertAdd(int $targetUserId, ?string $reason, int $moderatorId): void
    {
        $em = $this->getEntityManager();
        $targetRef = $em->getReference(User::class, $targetUserId);
        $modRef = $em->getReference(User::class, $moderatorId);

        $existing = $this->createQueryBuilder('w')
            ->andWhere('w.targetUser = :u')->setParameter('u', $targetRef)
            ->getQuery()->getOneOrNullResult();

        if ($existing) {
            $existing->setIsActive(true);
            if ($reason !== null && trim($reason) !== '') $existing->setReason($reason);
            return;
        }

        $w = (new WatchlistItem())
            ->setTargetUser($targetRef)
            ->setReason($reason)
            ->setIsActive(true)
            ->setCreatedBy($modRef);

        $em->persist($w);
    }

    public function toggleActive(int $watchId, bool $active): void
    {
        $this->getEntityManager()->createQuery("
            UPDATE App\Entity\WatchlistItem w
            SET w.isActive = :a
            WHERE w.id = :id
        ")
        ->setParameter('a', $active)
        ->setParameter('id', $watchId)
        ->execute();
    }

    public function updateReason(int $watchId, ?string $reason): void
    {
        $this->getEntityManager()->createQuery("
            UPDATE App\Entity\WatchlistItem w
            SET w.reason = :r
            WHERE w.id = :id
        ")
        ->setParameter('r', $reason)
        ->setParameter('id', $watchId)
        ->execute();
    }


    /**
     * Récupère un WatchlistItem + targetUser + personalProfile + professionalProfile + createdBy
     * (pour l'écran "Voir" / détail watchlist).
     */
    public function findOneWithUserProfiles(int $id): ?WatchlistItem
    {
        return $this->createQueryBuilder('w')
            ->leftJoin('w.targetUser', 'u')->addSelect('u')
            ->leftJoin('u.personalProfile', 'pp')->addSelect('pp')
            ->leftJoin('u.professionalProfile', 'pro')->addSelect('pro')
            ->leftJoin('w.createdBy', 'm')->addSelect('m')
            ->andWhere('w.id = :id')->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }


}