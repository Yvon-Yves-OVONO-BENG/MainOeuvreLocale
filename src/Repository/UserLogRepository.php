<?php

namespace App\Repository;

use App\Entity\ConstantsClass;
use App\Entity\UserLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\QueryBuilder;

/**
 * @extends ServiceEntityRepository<UserLog>
 */
class UserLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserLog::class);
    }

    public function countLogsSince(\DateTimeInterface $since): int
    {
        return (int) $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->andWhere('l.logedAt >= :since')
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countOnlineUsersSince(\DateTimeInterface $since): int
    {
        $dql = <<<DQL
            SELECT COUNT(DISTINCT u.id)
            FROM App\Entity\UserLog l
            JOIN l.user u
            WHERE l.disconnectedAt IS NULL
            AND l.lastSeenAt >= :since
        DQL;

        return (int) $this->getEntityManager()
            ->createQuery($dql)
            ->setParameter('since', $since)
            ->getSingleScalarResult();
    }

    public function countOnlineUsers(): int
    {
        $dql = <<<DQL
            SELECT COUNT(DISTINCT u.id)
            FROM App\Entity\UserLog l
            JOIN l.user u
            WHERE l.disconnectedAt IS NULL
        DQL;

        return (int) $this->getEntityManager()
            ->createQuery($dql)
            ->getSingleScalarResult();
    }


    public function findForModeration(string $q, string $action, int $days, int $limit, int $offset): array
    {
        $from = (new \DateTimeImmutable())->modify("-{$days} days")->setTime(0,0,0);

        $qb = $this->createQueryBuilder('l')
            ->select('l')
            ->leftJoin('l.user', 'u')->addSelect('u')
            ->leftJoin('u.personalProfile', 'pp')->addSelect('pp')
            ->leftJoin('l.deviceType', 'dt')->addSelect('dt')
            ->leftJoin('l.operatingSystem', 'os')->addSelect('os')
            ->leftJoin('l.browser', 'br')->addSelect('br')
            ->leftJoin('l.country', 'co')->addSelect('co')
            ->andWhere('l.logedAt >= :from')->setParameter('from', $from)
            ->orderBy('l.logedAt', 'DESC');

        if ($action !== '') {
            $qb->andWhere('l.action = :a')->setParameter('a', $action);
        }

        if ($q !== '') {
            $qb->andWhere('
                u.email LIKE :q
                OR u.phone LIKE :q
                OR pp.fullName LIKE :q
                OR l.ip LIKE :q
                OR l.ville LIKE :q
                OR l.userAgent LIKE :q
                OR l.action LIKE :q
            ')->setParameter('q', '%'.$q.'%');
        }

        // ✅ TOTAL (clône SANS pagination)
        $qbCount = clone $qb;
        $total = (int) $qbCount
            ->resetDQLPart('orderBy')
            ->select('COUNT(DISTINCT l.id)')
            ->setFirstResult(null)   // ✅ retire offset
            ->setMaxResults(null)    // ✅ retire limit
            ->getQuery()
            ->getSingleScalarResult();

        // ✅ ITEMS (pagination)
        $items = $qb
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return ['items' => $items, 'total' => $total];
    }

    public function stats7dActions(): array
    {
        $from = (new \DateTimeImmutable())->modify("-7 days")->setTime(0,0,0);

        // Daily count (SUBSTRING pour éviter FUNCTION/DATE)
        $daily = $this->createQueryBuilder('l')
            ->select("SUBSTRING(l.logedAt, 1, 10) as d, COUNT(l.id) as c")
            ->andWhere('l.logedAt >= :from')->setParameter('from', $from)
            ->andWhere('l.action IS NOT NULL')
            ->groupBy('d')
            ->orderBy('d','ASC')
            ->getQuery()->getArrayResult();

        $map = [];
        foreach ($daily as $r) $map[(string)$r['d']] = (int)$r['c'];

        $labels = [];
        $values = [];
        for ($i=0; $i<=7; $i++){
            $d = $from->modify("+{$i} days")->format('Y-m-d');
            $labels[] = $d;
            $values[] = $map[$d] ?? 0;
        }

        // Top actions
        $topActions = $this->createQueryBuilder('l')
            ->select('l.action as label, COUNT(l.id) as value')
            ->andWhere('l.logedAt >= :from')->setParameter('from', $from)
            ->andWhere('l.action IS NOT NULL')
            ->groupBy('l.action')
            ->orderBy('value','DESC')
            ->setMaxResults(8)
            ->getQuery()->getArrayResult();

        return [
            'daily' => ['labels'=>$labels,'values'=>$values],
            'topActions' => $topActions,
            'total' => array_sum($values),
        ];
    }

    /**
     * Récupère les dernières actions de modération / sanctions système.
     *
     * Actions concernées :
     * - USER_SUSPENDED  → utilisateur suspendu
     * - JOB_HIDDEN      → offre masquée
     * - WARNING_SENT    → avertissement envoyé
     * - REPORT_RESOLVED → signalement clôturé
     *
     * Usage :
     * - Historique modérateur
     * - Traçabilité des décisions de modération
     * - Audit / supervision administrative
     *
     * @return UserLog[]
     */
    public function findLastSanctions(int $limit = 10): array
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.action IN (:a)')
            ->setParameter('a', [
                ConstantsClass::ACTION_USER_SUSPENDED,
                ConstantsClass::ACTION_JOB_HIDDEN,
                ConstantsClass::ACTION_WARNING_SENT,
                ConstantsClass::ACTION_REPORT_RESOLVED,
            ])

            ->orderBy('l.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function riskStats(int $days = 14, int $top = 10): array
    {
        $from = (new \DateTimeImmutable())->modify("-{$days} days")->setTime(0,0,0);
        $from24h = (new \DateTimeImmutable('-24 hours'));

        $rows = $this->createQueryBuilder('l')
            ->select('l.id, l.logedAt, l.ip, l.action, IDENTITY(l.user) AS uid')
            ->andWhere('l.logedAt >= :from')->setParameter('from', $from)
            ->orderBy('l.logedAt', 'DESC')
            ->getQuery()->getArrayResult();

        $byDay = [];
        $topIps = [];
        $topActions = [];
        $lastSeenByUser = [];

        $total24h = 0;
        $ips24h = [];

        foreach ($rows as $r) {
            $dt = $r['logedAt']; // DateTimeInterface
            $day = $dt->format('Y-m-d');

            $byDay[$day] = ($byDay[$day] ?? 0) + 1;

            $ip = (string)($r['ip'] ?? '');
            if ($ip !== '') $topIps[$ip] = ($topIps[$ip] ?? 0) + 1;

            $act = (string)($r['action'] ?? '—');
            $topActions[$act] = ($topActions[$act] ?? 0) + 1;

            $uid = $r['uid'] ? (int)$r['uid'] : 0;
            if ($uid > 0) {
                $cur = $lastSeenByUser[$uid] ?? null;
                $curDt = $cur ? new \DateTimeImmutable($cur) : null;
                if (!$curDt || $dt > $curDt) $lastSeenByUser[$uid] = $dt->format('Y-m-d H:i:s');
            }

            if ($dt >= $from24h) {
                $total24h++;
                if ($ip !== '') $ips24h[$ip] = true;
            }
        }

        // séries complètes
        $labels = [];
        $values = [];
        for ($i=0; $i<=$days; $i++) {
            $d = $from->modify("+{$i} days")->format('Y-m-d');
            $labels[] = $d;
            $values[] = $byDay[$d] ?? 0;
        }

        arsort($topIps);
        $topIpsArr = array_slice(array_map(fn($k,$v)=>['label'=>$k,'value'=>$v], array_keys($topIps), $topIps), 0, $top);

        arsort($topActions);
        $topActArr = array_slice(array_map(fn($k,$v)=>['label'=>$k,'value'=>$v], array_keys($topActions), $topActions), 0, $top);

        return [
            'kpi' => [
                'total24h' => $total24h,
                'uniqueIps24h' => count($ips24h),
            ],
            'daily' => ['labels'=>$labels, 'values'=>$values],
            'topIps' => $topIpsArr,
            'topActions' => $topActArr,
            'lastSeenByUser' => $lastSeenByUser,
        ];
    }


    public function qbSearch(array $f): QueryBuilder
    {
        $qb = $this->createQueryBuilder('l')
            ->leftJoin('l.user', 'u')->addSelect('u')
            ->leftJoin('l.deviceType', 'dt')->addSelect('dt')
            ->leftJoin('l.operatingSystem', 'os')->addSelect('os')
            ->leftJoin('l.browser', 'b')->addSelect('b')
            ->leftJoin('l.country', 'c')->addSelect('c');

        if (!empty($f['q'])) {
            $qb->andWhere('u.email LIKE :q OR l.ip LIKE :q OR l.userAgent LIKE :q OR l.ville LIKE :q')
               ->setParameter('q', '%'.$f['q'].'%');
        }

        if (!empty($f['action'])) {
            $qb->andWhere('l.action = :action')->setParameter('action', $f['action']);
        }

        if (!empty($f['ip'])) {
            $qb->andWhere('l.ip LIKE :ip')->setParameter('ip', '%'.$f['ip'].'%');
        }

        if (!empty($f['status'])) {
            if ($f['status'] === 'connected') {
                $qb->andWhere('l.disconnectedAt IS NULL');
            } elseif ($f['status'] === 'disconnected') {
                $qb->andWhere('l.disconnectedAt IS NOT NULL');
            }
        }

        if (!empty($f['from'])) {
            $qb->andWhere('l.logedAt >= :from')
               ->setParameter('from', new \DateTimeImmutable($f['from'].' 00:00:00'));
        }

        if (!empty($f['to'])) {
            $qb->andWhere('l.logedAt <= :to')
               ->setParameter('to', new \DateTimeImmutable($f['to'].' 23:59:59'));
        }

        $sort = in_array($f['sort'] ?? 'logedAt', ['logedAt','ip','action','ville'], true) ? $f['sort'] : 'logedAt';
        $dir  = (($f['dir'] ?? 'DESC') === 'ASC') ? 'ASC' : 'DESC';

        $qb->orderBy('l.' . $sort, $dir); // ✅ plus clean
        return $qb;                       // ✅
    }

    public function getDistinctActions(int $limit = 30): array
    {
        return array_column(
            $this->createQueryBuilder('l')
                ->select('DISTINCT l.action AS action')
                ->andWhere('l.action IS NOT NULL')
                ->orderBy('l.action', 'ASC')
                ->setMaxResults($limit)
                ->getQuery()->getArrayResult(),
            'action'
        );
    }

    public function countActiveSessions(): int
    {
        return (int) $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->andWhere('l.disconnectedAt IS NULL')
            ->getQuery()->getSingleScalarResult();
    }

    public function countTodayConnections(): int
    {
        $from = new \DateTimeImmutable('today 00:00:00');
        return (int) $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->andWhere('l.logedAt >= :from')
            ->setParameter('from', $from)
            ->getQuery()->getSingleScalarResult();
    }

    public function findActiveSessions(int $limit = 100): array
    {
        return $this->createQueryBuilder('l')
            ->leftJoin('l.user', 'u')->addSelect('u')
            ->leftJoin('l.deviceType', 'dt')->addSelect('dt')
            ->leftJoin('l.operatingSystem', 'os')->addSelect('os')
            ->leftJoin('l.browser', 'b')->addSelect('b')
            ->leftJoin('l.country', 'c')->addSelect('c')
            ->andWhere('l.disconnectedAt IS NULL')
            ->orderBy('l.lastSeenAt', 'DESC')
            ->addOrderBy('l.logedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countNewSessionsSince(\DateTimeInterface $since): int
    {
        return (int) $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->andWhere('l.logedAt >= :since')
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findClosedSessionsSince(\DateTimeInterface $since): array
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.disconnectedAt IS NOT NULL')
            ->andWhere('l.logedAt >= :since')
            ->setParameter('since', $since)
            ->orderBy('l.logedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }


    public function getTopConnectedCities(int $limit = 10): array
    {
        $since = new \DateTimeImmutable('-15 minutes');

        return $this->createQueryBuilder('l')
            ->innerJoin('l.user', 'u')
            ->select('l.ville AS name, COUNT(DISTINCT u.id) AS users')
            ->andWhere('l.ville IS NOT NULL')
            ->andWhere('l.ville <> :empty')
            ->andWhere('(l.disconnectedAt IS NULL OR l.lastSeenAt >= :since OR l.logedAt >= :since)')
            ->setParameter('empty', '')
            ->setParameter('since', $since)
            ->groupBy('l.ville')
            ->orderBy('users', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();
    }

    public function findLatestConnections(int $limit = 8): array
    {
        return $this->createQueryBuilder('ul')
            ->leftJoin('ul.user', 'u')->addSelect('u')
            ->leftJoin('ul.browser', 'b')->addSelect('b')
            ->leftJoin('ul.operatingSystem', 'os')->addSelect('os')
            ->leftJoin('ul.deviceType', 'dt')->addSelect('dt')
            ->leftJoin('ul.country', 'c')->addSelect('c')
            ->andWhere('ul.logedAt IS NOT NULL')
            ->andWhere('ul.action = :action')
            ->setParameter('action', 'login')
            ->orderBy('ul.logedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
    //    /**
    //     * @return UserLogs[] Returns an array of UserLogs objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('u')
    //            ->andWhere('u.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('u.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?UserLogs
    //    {
    //        return $this->createQueryBuilder('u')
    //            ->andWhere('u.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
