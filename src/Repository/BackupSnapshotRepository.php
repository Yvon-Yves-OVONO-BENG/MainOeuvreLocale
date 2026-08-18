<?php

namespace App\Repository;

use App\Entity\BackupSnapshot;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class BackupSnapshotRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BackupSnapshot::class);
    }

    public function getEncryptionHealth(int $days = 30): array
    {
        $since = new \DateTimeImmutable(sprintf('-%d days', $days));

        $rows = $this->createQueryBuilder('b')
            ->select('COUNT(b.id) AS total')
            ->addSelect('SUM(CASE WHEN b.isEncrypted = true THEN 1 ELSE 0 END) AS encryptedCount')
            ->andWhere('b.startedAt >= :since')
            ->andWhere('b.status = :status')
            ->setParameter('since', $since)
            ->setParameter('status', 'success')
            ->getQuery()
            ->getSingleResult();

        $total = (int) ($rows['total'] ?? 0);
        $encrypted = (int) ($rows['encryptedCount'] ?? 0);
        $rate = $total > 0 ? round(($encrypted * 100) / $total) : 0;

        return [
            'total' => $total,
            'encrypted' => $encrypted,
            'rate' => $rate,
            'allEncrypted' => $total > 0 && $total === $encrypted,
        ];
    }

    public function findLatest(int $limit = 10): array
    {
        return $this->createQueryBuilder('b')
            ->orderBy('b.startedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}