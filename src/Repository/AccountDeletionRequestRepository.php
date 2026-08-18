<?php

namespace App\Repository;

use App\Entity\AccountDeletionRequest;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class AccountDeletionRequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AccountDeletionRequest::class);
    }

    public function existsFor(User $user): bool
    {
        return (bool) $this->findOneBy(['user' => $user]);
    }

    public function findDueForProcessing(int $limit = 50): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.status = :s')->setParameter('s', AccountDeletionRequest::STATUS_APPROVED)
            ->andWhere('r.scheduledAt IS NOT NULL')
            ->andWhere('r.scheduledAt <= :now')->setParameter('now', new \DateTimeImmutable())
            ->andWhere('r.processedAt IS NULL')
            ->orderBy('r.scheduledAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function searchAdmin(?string $status, ?string $q, int $limit = 100): array
    {
        $qb = $this->createQueryBuilder('r')
            ->addSelect('u')
            ->innerJoin('r.user', 'u')
            ->orderBy('r.requestedAt', 'DESC')
            ->setMaxResults($limit);

        if ($status && in_array($status, [
            AccountDeletionRequest::STATUS_PENDING,
            AccountDeletionRequest::STATUS_APPROVED,
            AccountDeletionRequest::STATUS_REJECTED,
            AccountDeletionRequest::STATUS_DONE,
        ], true)) {
            $qb->andWhere('r.status = :s')->setParameter('s', $status);
        }

        if ($q) {
            $qLike = '%' . mb_strtolower(trim($q)) . '%';
            $qb->andWhere('LOWER(u.email) LIKE :q')->setParameter('q', $qLike);
        }

        return $qb->getQuery()->getResult();
    }
}