<?php

namespace App\Repository;

use App\Entity\Invoices;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Invoices>
 */
class InvoicesRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Invoices::class);
    }

    // ✅ Dernières factures
    public function findLatestByUser(int $userId, int $limit = 5): array
    {
        return $this->createQueryBuilder('i')
            ->leftJoin('i.payment', 'p')->addSelect('p')
            ->andWhere('i.user = :uid')
            ->setParameter('uid', $userId)
            ->orderBy('i.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array{items: array, total: int}
     */
    public function findPaginatedWithPaymentByUser(User $user, int $page = 1, int $limit = 10, ?string $q = null): array
    {
        $page = max(1, $page);
        $limit = max(1, $limit);
        $q = trim((string) $q);

        $qb = $this->createQueryBuilder('inv')
            ->leftJoin('inv.payment', 'p')->addSelect('p')
            ->leftJoin('p.provider', 'pr')->addSelect('pr')
            ->leftJoin('p.subscription', 's')->addSelect('s')
            ->leftJoin('p.statusPayment', 'sp')->addSelect('sp')
            ->andWhere('inv.user = :u')
            ->setParameter('u', $user)
            ->orderBy('inv.createdAt', 'DESC');

        if ($q !== '') {
            $qb->andWhere('
                inv.invoiceNumber LIKE :q OR
                inv.slug         LIKE :q OR
                pr.name          LIKE :q OR
                p.amount         LIKE :q OR
                p.currency       LIKE :q
            ')
            ->setParameter('q', '%' . $q . '%');
        }

        $qb->setFirstResult(($page - 1) * $limit)
           ->setMaxResults($limit);

        $paginator = new Paginator($qb->getQuery(), true);

        return [
            'items' => iterator_to_array($paginator),
            'total' => count($paginator),
        ];
    }

    public function findOneBySlugForUser(string $slug, User $user): ?Invoices
    {
        return $this->createQueryBuilder('inv')
            ->andWhere('inv.slug = :s')->setParameter('s', $slug)
            ->andWhere('inv.user = :u')->setParameter('u', $user)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function countAllInvoices(): int
    {
        return (int) $this->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countInvoicesSince(\DateTimeInterface $since): int
    {
        return (int) $this->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->andWhere('i.createdAt >= :since')
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getInvoicesChartSince(\DateTimeInterface $since): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = '
            SELECT DATE(created_at) AS d, COUNT(id) AS c
            FROM invoices
            WHERE created_at >= :since
            GROUP BY DATE(created_at)
            ORDER BY d ASC
        ';

        return $conn->executeQuery(
            $sql,
            ['since' => $since->format('Y-m-d H:i:s')]
        )->fetchAllAssociative();
    }

    public function searchAdmin(string $q): array
    {
        return $this->createQueryBuilder('t')
            ->select('t.id, t.subject, t.status')
            ->where('t.subject LIKE :q')
            ->setParameter('q', "%$q%")
            ->setMaxResults(5)
            ->getQuery()
            ->getArrayResult();
    }

    public function findRecentInvoices(int $limit = 8): array
    {
        return $this->createQueryBuilder('i')
            ->leftJoin('i.user', 'u')->addSelect('u')
            ->leftJoin('i.payment', 'p')->addSelect('p')
            ->orderBy('i.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function getTopInvoiceUsersSince(\DateTimeInterface $since, int $limit = 8): array
    {
        return $this->createQueryBuilder('i')
            ->select('u.email AS email, COUNT(i.id) AS total')
            ->leftJoin('i.user', 'u')
            ->andWhere('i.createdAt >= :since')
            ->setParameter('since', $since)
            ->andWhere('u.id IS NOT NULL')
            ->groupBy('u.id, u.email')
            ->orderBy('total', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();
    }


    //    /**
    //     * @return Invoices[] Returns an array of Invoices objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('i')
    //            ->andWhere('i.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('i.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Invoices
    //    {
    //        return $this->createQueryBuilder('i')
    //            ->andWhere('i.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
