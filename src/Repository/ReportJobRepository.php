<?php

namespace App\Repository;

use App\Entity\ReportJob;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ReportJob>
 *
 * @method ReportJob|null find($id, $lockMode = null, $lockVersion = null)
 * @method ReportJob|null findOneBy(array $criteria, array $orderBy = null)
 * @method ReportJob[]    findAll()
 * @method ReportJob[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ReportJobRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ReportJob::class);
    }


    public function countRecentPendingForAlert(\DateTimeInterface $since): int
    {
        return (int) $this->createQueryBuilder('rj')
            ->select('COUNT(rj.id)')
            ->andWhere('rj.createdAt >= :since')
            ->andWhere('rj.status = :status')
            ->setParameter('since', $since)
            ->setParameter('status', 'pending')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findLatestRecentPendingForAlert(\DateTimeInterface $since): ?\DateTimeInterface
    {
        $result = $this->createQueryBuilder('rj')
            ->select('rj.createdAt')
            ->andWhere('rj.createdAt >= :since')
            ->andWhere('rj.status = :status')
            ->setParameter('since', $since)
            ->setParameter('status', 'pending')
            ->orderBy('rj.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (\is_array($result) && isset($result['createdAt'])) {
            return $result['createdAt'];
        }

        return null;
    }
    
    public function findForModeration(?string $status, string $q, int $page, int $limit): array
    {
        $qb = $this->createQueryBuilder('r')
            ->leftJoin('r.job', 'j')->addSelect('j')
            ->leftJoin('r.user', 'u')->addSelect('u')
            ->orderBy('r.createdAt', 'DESC');

        if ($status) {
            $qb->andWhere('r.status = :st')->setParameter('st', $status);
        }

        if ($q !== '') {
            $qb->andWhere('r.reason LIKE :q OR u.email LIKE :q')
               ->setParameter('q', '%'.$q.'%');
        }

        $total = (int) (clone $qb)
            ->select('COUNT(r.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $items = $qb->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return ['items' => $items, 'total' => $total];
    }

    public function countByStatu(?\DateTimeImmutable $from = null): array
    {
        $qb = $this->createQueryBuilder('r')
            ->select('r.status as status, COUNT(r.id) as c')
            ->groupBy('r.status');

        if ($from) {
            $qb->andWhere('r.createdAt >= :from')->setParameter('from', $from);
        }

        $rows = $qb->getQuery()->getArrayResult();
        $out = [];
        foreach ($rows as $row) $out[$row['status']] = (int) $row['c'];
        return $out;
    }

    public function dailyCounts(int $days = 14): array
    {
        $from = (new \DateTimeImmutable('today'))->modify('-'.($days-1).' days');

        $rows = $this->createQueryBuilder('r')
            ->select('r.createdAt as createdAt')
            ->andWhere('r.createdAt >= :from')->setParameter('from', $from)
            ->getQuery()->getArrayResult();

        $map = [];
        foreach ($rows as $row) {
            /** @var \DateTimeImmutable $dt */
            $dt = $row['createdAt'];
            $k = $dt->format('Y-m-d');
            $map[$k] = ($map[$k] ?? 0) + 1;
        }

        $labels = [];
        $values = [];
        for ($i = 0; $i < $days; $i++) {
            $d = $from->modify("+$i days");
            $k = $d->format('Y-m-d');
            $labels[] = $d->format('d/m');
            $values[] = $map[$k] ?? 0;
        }

        return ['labels' => $labels, 'values' => $values];
    }

    public function topJobs(int $days = 30, int $top = 10): array
    {
        $from = (new \DateTimeImmutable('today'))->modify('-'.($days-1).' days');

        $rows = $this->createQueryBuilder('r')
            ->select('j.id as id, j.title as title, COUNT(r.id) as c')
            ->leftJoin('r.job', 'j')
            ->andWhere('r.createdAt >= :from')->setParameter('from', $from)
            ->groupBy('j.id')
            ->orderBy('c', 'DESC')
            ->setMaxResults($top)
            ->getQuery()->getArrayResult();

        return array_map(fn($x) => [
            'id' => (int)$x['id'],
            'label' => (string)($x['title'] ?? ('Job #'.$x['id'])),
            'value' => (int)$x['c'],
        ], $rows);
    }

    public function getDashboardStats(int $days = 14, int $top = 10): array
    {
        $from = (new \DateTimeImmutable('today'))->modify('-'.($days-1).' days');
        $byStatus = $this->countByStatu($from);
        $daily = $this->dailyCounts($days);
        $topJobs = $this->topJobs(30, $top);

        $pending  = (int)($byStatus['pending'] ?? 0);
        $reviewed = (int)($byStatus['reviewed'] ?? 0);
        $rejected = (int)($byStatus['rejected'] ?? 0);
        $resolved = (int)($byStatus['resolved'] ?? 0);

        return [
            'kpi' => [
                'pending' => $pending,
                'reviewed' => $reviewed,
                'rejected' => $rejected,
                'resolved' => $resolved,
                'total' => $pending + $reviewed + $rejected + $resolved,
            ],
            'byStatus' => $byStatus,
            'daily' => $daily,
            'topJobs' => $topJobs,
        ];
    }

    public function analyticsReportsDaily(int $days): array
    {
        $from = (new \DateTimeImmutable())->modify("-{$days} days")->setTime(0, 0, 0);

        $rows = $this->createQueryBuilder('rj')
            ->select("SUBSTRING(rj.createdAt, 1, 10) as d, COUNT(rj.id) as c")
            ->andWhere('rj.createdAt >= :from')->setParameter('from', $from)
            ->groupBy('d')
            ->orderBy('d', 'ASC')
            ->getQuery()->getArrayResult();

        $map = [];
        foreach ($rows as $r) $map[(string) $r['d']] = (int) $r['c'];

        $labels = [];
        $values = [];
        for ($i = 0; $i <= $days; $i++) {
            $d = $from->modify("+{$i} days")->format('Y-m-d');
            $labels[] = $d;
            $values[] = $map[$d] ?? 0;
        }

        return ['labels' => $labels, 'values' => $values];
    }

    public function analyticsStatusCounts(int $days): array
    {
        $from = (new \DateTimeImmutable())->modify("-{$days} days")->setTime(0,0,0);

        $row = $this->createQueryBuilder('rj')
            ->select("
            SUM(CASE WHEN rj.status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN rj.status = 'reviewed' THEN 1 ELSE 0 END) as reviewed,
            SUM(CASE WHEN rj.status = 'rejected' THEN 1 ELSE 0 END) as rejected,
            SUM(CASE WHEN rj.status = 'resolved' THEN 1 ELSE 0 END) as resolved
            ")
            ->andWhere('rj.createdAt >= :from')->setParameter('from', $from)
            ->getQuery()->getOneOrNullResult();

        return [
            'pending'  => (int)($row['pending'] ?? 0),
            'reviewed' => (int)($row['reviewed'] ?? 0),
            'rejected' => (int)($row['rejected'] ?? 0),
            'resolved' => (int)($row['resolved'] ?? 0),
        ];
    }

    public function analyticsTopJobs(int $top, int $days): array
    {
        $from = (new \DateTimeImmutable())->modify("-{$days} days")->setTime(0,0,0);

        $rows = $this->createQueryBuilder('rj')
            ->select('j.id as id, j.title as label, COUNT(rj.id) as value')
            ->innerJoin('rj.job', 'j')
            ->andWhere('rj.createdAt >= :from')->setParameter('from', $from)
            ->groupBy('j.id')
            ->orderBy('value', 'DESC')
            ->setMaxResults($top)
            ->getQuery()->getArrayResult();

        return array_map(fn($x)=>['label'=>$x['label'] ?: ('Job #'.$x['id']), 'value'=>(int)$x['value']], $rows);
    }


    public function statsForJobOwner(int $ownerId, int $days = 30): array
    {
        $from = (new \DateTimeImmutable())->modify("-{$days} days")->setTime(0,0,0);

        $rows = $this->createQueryBuilder('rj')
            ->select('rj.status AS st, COUNT(rj.id) AS c')
            ->join('rj.job', 'j')
            ->andWhere('j.createdBy = :u')->setParameter('u', $ownerId)
            ->andWhere('rj.createdAt >= :from')->setParameter('from', $from)
            ->groupBy('rj.status')
            ->getQuery()->getArrayResult();

        $out = ['pending'=>0,'reviewed'=>0,'rejected'=>0,'resolved'=>0,'total'=>0];
        foreach ($rows as $row) {
            $st = (string) $row['st'];
            $c  = (int) $row['c'];
            if (!isset($out[$st])) $out[$st] = 0;
            $out[$st] += $c;
            $out['total'] += $c;
        }
        return $out;
    }


    public function riskStats(int $days = 14, int $top = 10): array
    {
        $from = (new \DateTimeImmutable())->modify("-{$days} days")->setTime(0,0,0);

        $rows = $this->createQueryBuilder('r')
            ->select('r.id, r.createdAt, r.status, IDENTITY(j.createdBy) AS creatorId')
            ->leftJoin('r.job', 'j')
            ->andWhere('r.createdAt >= :from')->setParameter('from', $from)
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()->getArrayResult();

        $byDay = [];
        $byStatus = ['pending'=>0,'reviewed'=>0,'rejected'=>0,'resolved'=>0];
        $pendingByCreator = [];
        $topCreators = [];

        foreach ($rows as $r) {
            $dt = $r['createdAt'];
            $day = $dt->format('Y-m-d');
            $byDay[$day] = ($byDay[$day] ?? 0) + 1;

            $st = (string)($r['status'] ?? 'pending');
            if (!isset($byStatus[$st])) $byStatus[$st] = 0;
            $byStatus[$st]++;

            $cid = $r['creatorId'] ? (int)$r['creatorId'] : 0;
            if ($cid > 0) {
                $topCreators[$cid] = ($topCreators[$cid] ?? 0) + 1;
                if ($st === 'pending') $pendingByCreator[$cid] = ($pendingByCreator[$cid] ?? 0) + 1;
            }
        }

        $labels = [];
        $values = [];
        for ($i=0; $i<=$days; $i++) {
            $d = $from->modify("+{$i} days")->format('Y-m-d');
            $labels[] = $d;
            $values[] = $byDay[$d] ?? 0;
        }

        arsort($topCreators);
        $topCreatorsArr = [];
        foreach (array_slice($topCreators, 0, $top, true) as $uid=>$v) {
            $topCreatorsArr[] = ['label'=>'#'.$uid, 'value'=>$v, 'userId'=>$uid];
        }

        return [
            'kpi' => [
                'pending' => $byStatus['pending'] ?? 0,
                'reviewed'=> $byStatus['reviewed'] ?? 0,
                'rejected'=> $byStatus['rejected'] ?? 0,
                'resolved'=> $byStatus['resolved'] ?? 0,
                'total'   => array_sum($byStatus),
            ],
            'daily' => ['labels'=>$labels,'values'=>$values],
            'byStatus' => $byStatus,
            'topCreators' => $topCreatorsArr,
            'pendingByCreator' => $pendingByCreator,
        ];
    }


    public function countAllJobReports(): int
    {
        return (int) $this->createQueryBuilder('rj')
            ->select('COUNT(rj.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countByStatus(string $status): int
    {
        return (int) $this->createQueryBuilder('rj')
            ->select('COUNT(rj.id)')
            ->andWhere('rj.status = :status')
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countJobReportsSince(\DateTimeInterface $since): int
    {
        return (int) $this->createQueryBuilder('rj')
            ->select('COUNT(rj.id)')
            ->andWhere('rj.createdAt >= :since')
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getJobReportsChartSince(\DateTimeInterface $since): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = '
            SELECT DATE(created_at) AS dte, COUNT(id) AS c
            FROM report_job
            WHERE created_at >= :since
            GROUP BY DATE(created_at)
            ORDER BY dte ASC
        ';

        return $conn->executeQuery(
            $sql,
            ['since' => $since->format('Y-m-d H:i:s')]
        )->fetchAllAssociative();
    }

    public function findRecentJobReports(int $limit = 8): array
    {
        return $this->createQueryBuilder('rj')
            ->leftJoin('rj.job', 'j')->addSelect('j')
            ->leftJoin('rj.user', 'u')->addSelect('u')
            ->orderBy('rj.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function getTopReportedJobs(int $limit = 8): array
    {
        return $this->createQueryBuilder('rj')
            ->select('j.title AS title, COUNT(rj.id) AS total')
            ->leftJoin('rj.job', 'j')
            ->groupBy('j.id, j.title')
            ->orderBy('total', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();
    }


    public function countPendingGroupedByCategory(): array
    {
        return $this->createQueryBuilder('rj')
            ->select("COALESCE(LOWER(c.label), 'autres') AS label, COUNT(rj.id) AS total")
            ->leftJoin('rj.categorie', 'c')
            ->andWhere('rj.status = :status')
            ->setParameter('status', 'pending')
            ->groupBy('c.id, c.label')
            ->getQuery()
            ->getArrayResult();
    }
    //    /**
    //     * @return ReportJob[] Returns an array of ReportJob objects
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

    //    public function findOneBySomeField($value): ?ReportJob
    //    {
    //        return $this->createQueryBuilder('c')
    //            ->andWhere('c.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
