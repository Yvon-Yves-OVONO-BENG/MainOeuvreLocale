<?php

namespace App\Repository;

use App\Entity\AdminAuditLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class AdminAuditLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AdminAuditLog::class);
    }

    public function getSensitiveCoverageRate(int $days = 30): array
    {
        $since = new \DateTimeImmutable(sprintf('-%d days', $days));

        $row = $this->createQueryBuilder('l')
            ->select('COUNT(l.id) AS total')
            ->addSelect('SUM(CASE WHEN l.isSensitive = true THEN 1 ELSE 0 END) AS sensitiveCount')
            ->andWhere('l.createdAt >= :since')
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleResult();

        $total = (int) ($row['total'] ?? 0);
        $sensitive = (int) ($row['sensitiveCount'] ?? 0);
        $rate = $total > 0 ? round(($sensitive * 100) / $total) : 0;

        return [
            'total' => $total,
            'sensitive' => $sensitive,
            'rate' => $rate,
        ];
    }

    public function findLatestSensitive(int $limit = 10): array
    {
        return $this->createQueryBuilder('l')
            ->leftJoin('l.adminUser', 'a')->addSelect('a')
            ->andWhere('l.isSensitive = true')
            ->orderBy('l.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}