<?php

namespace App\Repository;

use App\Entity\Payment;
use App\Entity\PaymentDispute;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

class PaymentDisputeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PaymentDispute::class);
    }

    /**
     * @return PaymentDispute[]
     */
    public function findRecentForUser(User $user, int $limit = 5): array
    {
        return $this->createQueryBuilder('pd')
            ->andWhere('pd.user = :user')
            ->setParameter('user', $user)
            ->orderBy('pd.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    

    /**
     * @return PaymentDispute[]
     */
    public function findAllForUser(User $user): array
    {
        return $this->createQueryBuilder('pd')
            ->leftJoin('pd.payment', 'p')->addSelect('p')
            ->leftJoin('pd.invoice', 'i')->addSelect('i')
            ->leftJoin('pd.handledBy', 'h')->addSelect('h')
            ->andWhere('pd.user = :user')
            ->setParameter('user', $user)
            ->orderBy('pd.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function countAllForUser(User $user): int
    {
        return (int) $this->createQueryBuilder('pd')
            ->select('COUNT(pd.id)')
            ->andWhere('pd.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countByStatusForUser(User $user, string $status): int
    {
        return (int) $this->createQueryBuilder('pd')
            ->select('COUNT(pd.id)')
            ->andWhere('pd.user = :user')
            ->andWhere('pd.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();
    }


    public function findOpenForUserAndPayment(User $user, Payment $payment): ?PaymentDispute
    {
        return $this->createQueryBuilder('pd')
            ->andWhere('pd.user = :user')
            ->andWhere('pd.payment = :payment')
            ->andWhere('pd.status IN (:statuses)')
            ->setParameter('user', $user)
            ->setParameter('payment', $payment)
            ->setParameter('statuses', [
                PaymentDispute::STATUS_OPEN,
                PaymentDispute::STATUS_REVIEW,
            ])
            ->orderBy('pd.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
    public function countRecentOpenOrReview(\DateTimeInterface $since): int
    {
        return (int) $this->createQueryBuilder('pd')
            ->select('COUNT(pd.id)')
            ->andWhere('pd.createdAt >= :since')
            ->andWhere('pd.status IN (:statuses)')
            ->setParameter('since', $since)
            ->setParameter('statuses', [
                PaymentDispute::STATUS_OPEN,
                PaymentDispute::STATUS_REVIEW,
            ])
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findLatestRecentOpenOrReview(\DateTimeInterface $since): ?\DateTimeInterface
    {
        $result = $this->createQueryBuilder('pd')
            ->select('pd.createdAt')
            ->andWhere('pd.createdAt >= :since')
            ->andWhere('pd.status IN (:statuses)')
            ->setParameter('since', $since)
            ->setParameter('statuses', [
                PaymentDispute::STATUS_OPEN,
                PaymentDispute::STATUS_REVIEW,
            ])
            ->orderBy('pd.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (\is_array($result) && isset($result['createdAt'])) {
            return $result['createdAt'];
        }

        return null;
    }
    
    public function qbAdminList(array $filters = []): QueryBuilder
    {
        $qb = $this->createQueryBuilder('d')
            ->leftJoin('d.user', 'u')->addSelect('u')
            ->leftJoin('d.payment', 'p')->addSelect('p')
            ->leftJoin('d.invoice', 'i')->addSelect('i')
            ->leftJoin('d.handledBy', 'h')->addSelect('h');

        if (!empty($filters['q'])) {
            $qb->andWhere('
                u.email LIKE :q
                OR d.reason LIKE :q
                OR d.status LIKE :q
                OR d.priority LIKE :q
                OR d.source LIKE :q
                OR i.invoiceNumber LIKE :q
            ')
            ->setParameter('q', '%' . trim($filters['q']) . '%');
        }

        if (!empty($filters['status'])) {
            $qb->andWhere('d.status = :status')
               ->setParameter('status', $filters['status']);
        }

        if (!empty($filters['priority'])) {
            $qb->andWhere('d.priority = :priority')
               ->setParameter('priority', $filters['priority']);
        }

        if (!empty($filters['source'])) {
            $qb->andWhere('d.source = :source')
               ->setParameter('source', $filters['source']);
        }

        return $qb->orderBy('d.createdAt', 'DESC');
    }

    public function findForUser(User $user): array
    {
        return $this->createQueryBuilder('d')
            ->leftJoin('d.payment', 'p')->addSelect('p')
            ->leftJoin('d.invoice', 'i')->addSelect('i')
            ->andWhere('d.user = :user')
            ->setParameter('user', $user)
            ->orderBy('d.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function countOpen(): int
    {
        return (int) $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->andWhere('d.status = :status')
            ->setParameter('status', PaymentDispute::STATUS_OPEN)
            ->getQuery()
            ->getSingleScalarResult();
    }


    public function countAllDisputes(): int
    {
        return (int) $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countByStatus(string $status): int
    {
        return (int) $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->andWhere('d.status = :status')
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countDisputesSince(\DateTimeInterface $since): int
    {
        return (int) $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->andWhere('d.createdAt >= :since')
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getDisputesChartSince(\DateTimeInterface $since): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = '
            SELECT DATE(created_at) AS dte, COUNT(id) AS c
            FROM Payment_dispute
            WHERE created_at >= :since
            GROUP BY DATE(created_at)
            ORDER BY dte ASC
        ';

        return $conn->executeQuery(
            $sql,
            ['since' => $since->format('Y-m-d H:i:s')]
        )->fetchAllAssociative();
    }

    public function findRecentDisputes(int $limit = 8): array
    {
        return $this->createQueryBuilder('d')
            ->leftJoin('d.user', 'u')->addSelect('u')
            ->leftJoin('d.payment', 'p')->addSelect('p')
            ->leftJoin('d.invoice', 'i')->addSelect('i')
            ->leftJoin('d.handledBy', 'h')->addSelect('h')
            ->orderBy('d.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function getSourceStatsSince(\DateTimeInterface $since): array
    {
        return $this->createQueryBuilder('d')
            ->select('d.source AS source, COUNT(d.id) AS total')
            ->andWhere('d.createdAt >= :since')
            ->setParameter('since', $since)
            ->groupBy('d.source')
            ->orderBy('total', 'DESC')
            ->getQuery()
            ->getArrayResult();
    }

    public function getPriorityStatsSince(\DateTimeInterface $since): array
    {
        return $this->createQueryBuilder('d')
            ->select('d.priority AS priority, COUNT(d.id) AS total')
            ->andWhere('d.createdAt >= :since')
            ->setParameter('since', $since)
            ->groupBy('d.priority')
            ->orderBy('total', 'DESC')
            ->getQuery()
            ->getArrayResult();
    }

    public function findLatestOpenAndReviewDisputes(int $limit = 5): array
    {
        return $this->createQueryBuilder('pd')
            ->leftJoin('pd.user', 'u')->addSelect('u')
            ->leftJoin('pd.payment', 'p')->addSelect('p')
            ->leftJoin('pd.invoice', 'i')->addSelect('i')
            ->andWhere('pd.status IN (:statuses)')
            ->setParameter('statuses', [
                PaymentDispute::STATUS_OPEN,
                PaymentDispute::STATUS_REVIEW,
            ])
            ->orderBy('pd.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}