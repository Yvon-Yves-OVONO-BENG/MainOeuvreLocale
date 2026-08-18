<?php

namespace App\Repository;

use App\Entity\Job;
use App\Entity\JobView;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<JobView>
 *
 * @method JobView|null find($id, $lockMode = null, $lockVersion = null)
 * @method JobView|null findOneBy(array $criteria, array $orderBy = null)
 * @method JobView[]    findAll()
 * @method JobView[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class JobViewRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, protected EntityManagerInterface $em)
    {
        parent::__construct($registry, JobView::class);

    }

    public function addUniqueView(User $user, Job $job): bool
    {
        $exists = $this->createQueryBuilder('v')
            ->select('COUNT(v.id)')
            ->andWhere('v.user = :u')->setParameter('u', $user)
            ->andWhere('v.job = :j')->setParameter('j', $job)
            ->getQuery()
            ->getSingleScalarResult();

        if ((int)$exists > 0) {
            return false; // déjà vu
        }

        $view = new JobView();
        $view->setUser($user);
        $view->setJob($job);
        $view->setViewedAt(new \DateTimeImmutable());

        $this->em->persist($view);
        $this->em->flush();

        return true; // nouvelle vue
    }

    public function countViews(Job $job): int
    {
        return (int) $this->createQueryBuilder('v')
            ->select('COUNT(v.id)')
            ->andWhere('v.job = :j')->setParameter('j', $job)
            ->getQuery()
            ->getSingleScalarResult();
    }


    public function countViewsByCompanyPeriod(
        User $company,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
        ?Job $job = null
    ): int {
        $qb = $this->createQueryBuilder('v')
            ->select('COUNT(DISTINCT v.id)')
            ->innerJoin('v.job', 'j')
            ->andWhere('j.createdBy = :u')
            ->andWhere('v.viewedAt BETWEEN :from AND :to')
            ->setParameter('u', $company)
            ->setParameter('from', $from)
            ->setParameter('to', $to);

        if ($job) {
            $qb->andWhere('v.job = :job')->setParameter('job', $job);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Retourne un tableau associatif: ['Y-m-d' => count]
     */
    public function dailyViewsByCompanyPeriod(
        User $company,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
        ?Job $job = null
    ): array {
        $conn = $this->getEntityManager()->getConnection();

        $whereJob = '';
        $params = [
            'companyId' => $company->getId(),
            'from' => $from->format('Y-m-d H:i:s'),
            'to'   => $to->format('Y-m-d H:i:s'),
        ];
        if ($job) {
            $whereJob = ' AND v.job_id = :jobId ';
            $params['jobId'] = $job->getId();
        }

        $sql = "
            SELECT DATE(v.viewed_at) AS d, COUNT(v.id) AS c
            FROM job_view v
            INNER JOIN job j ON j.id = v.job_id
            WHERE j.created_by_id = :companyId
            AND v.viewed_at BETWEEN :from AND :to
            $whereJob
            GROUP BY DATE(v.viewed_at)
            ORDER BY d ASC
        ";

        $rows = $conn->fetchAllAssociative($sql, $params);

        $out = [];
        foreach ($rows as $r) {
            $out[(string) $r['d']] = (int) $r['c'];
        }
        return $out;
    }
    //    /**
    //     * @return JobView[] Returns an array of JobView objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('c')
    //            ->andWhere('c.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('c.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?JobView
    //    {
    //        return $this->createQueryBuilder('c')
    //            ->andWhere('c.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
