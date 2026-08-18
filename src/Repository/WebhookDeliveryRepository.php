<?php

namespace App\Repository;

use App\Entity\WebhookDelivery;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class WebhookDeliveryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WebhookDelivery::class);
    }

    public function getSignatureHealth(int $days = 30): array
    {
        $since = new \DateTimeImmutable(sprintf('-%d days', $days));

        $row = $this->createQueryBuilder('d')
            ->select('COUNT(d.id) AS total')
            ->addSelect('SUM(CASE WHEN d.signatureValid = true THEN 1 ELSE 0 END) AS validCount')
            ->andWhere('d.sentAt >= :since OR d.deliveredAt >= :since')
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleResult();

        $total = (int) ($row['total'] ?? 0);
        $valid = (int) ($row['validCount'] ?? 0);
        $rate = $total > 0 ? round(($valid * 100) / $total) : 0;

        return [
            'total' => $total,
            'valid' => $valid,
            'rate' => $rate,
        ];
    }

    public function findLatestFailed(int $limit = 10): array
    {
        return $this->createQueryBuilder('d')
            ->leftJoin('d.endpoint', 'e')->addSelect('e')
            ->andWhere('d.status IN (:statuses)')
            ->setParameter('statuses', ['failed', 'invalid_signature'])
            ->orderBy('d.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}