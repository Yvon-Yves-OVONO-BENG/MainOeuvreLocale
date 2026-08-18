<?php
// src/Repository/ReportRepository.php

namespace App\Repository;

use App\Entity\CategorieReport;
use App\Entity\Report;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query\Expr\Orx;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\QueryBuilder;

class ReportRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Report::class);
    }


    public function countRecentOpenForAlert(\DateTimeInterface $since): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.createdAt >= :since')
            ->andWhere('r.status = :status')
            ->andWhere('r.supprimer = false')
            ->setParameter('since', $since)
            ->setParameter('status', Report::STATUS_OPEN)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findLatestRecentOpenForAlert(\DateTimeInterface $since): ?\DateTimeInterface
    {
        $result = $this->createQueryBuilder('r')
            ->select('r.createdAt')
            ->andWhere('r.createdAt >= :since')
            ->andWhere('r.status = :status')
            ->andWhere('r.supprimer = false')
            ->setParameter('since', $since)
            ->setParameter('status', Report::STATUS_OPEN)
            ->orderBy('r.createdAt', 'DESC')
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
            ->leftJoin('r.reporter', 'rep')->addSelect('rep')
            ->leftJoin('r.targetUser', 'tu')->addSelect('tu')
            ->leftJoin('r.categorie', 'cat')->addSelect('cat')
            ->orderBy('r.createdAt', 'DESC');

        if ($status) {
            $qb->andWhere('r.status = :st')->setParameter('st', $status);
        }

        if ($q !== '') {
            $qb->andWhere('r.reason LIKE :q OR rep.email LIKE :q OR tu.email LIKE :q')
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

    public function findRecentByTarget(User $target, int $limit = 8, ?int $excludeId = null): array
    {
        $qb = $this->createQueryBuilder('r')
            ->andWhere('r.targetUser = :t')->setParameter('t', $target)
            ->orderBy('r.createdAt', 'DESC')
            ->setMaxResults($limit);

        if ($excludeId) {
            $qb->andWhere('r.id != :id')->setParameter('id', $excludeId);
        }

        return $qb->getQuery()->getResult();
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

    public function categoryBreakdown(int $days = 30, int $top = 10): array
    {
        $from = (new \DateTimeImmutable('today'))->modify('-'.($days-1).' days');

        $rows = $this->createQueryBuilder('r')
            ->select('cat.label as label, COUNT(r.id) as c')
            ->leftJoin('r.categorie', 'cat')
            ->andWhere('r.createdAt >= :from')->setParameter('from', $from)
            ->groupBy('cat.id')
            ->orderBy('c', 'DESC')
            ->setMaxResults($top)
            ->getQuery()->getArrayResult();

        return array_map(fn($x) => ['label' => (string)($x['label'] ?? 'Autre'), 'value' => (int)$x['c']], $rows);
    }

    public function topTargets(int $days = 30, int $top = 10): array
    {
        $from = (new \DateTimeImmutable('today'))->modify('-'.($days-1).' days');

        $rows = $this->createQueryBuilder('r')
            ->select('tu.id as id, tu.email as email, COUNT(r.id) as c')
            ->leftJoin('r.targetUser', 'tu')
            ->andWhere('r.createdAt >= :from')->setParameter('from', $from)
            ->groupBy('tu.id')
            ->orderBy('c', 'DESC')
            ->setMaxResults($top)
            ->getQuery()->getArrayResult();

        return array_map(fn($x) => [
            'id' => (int)$x['id'],
            'label' => (string)($x['email'] ?? ('#'.$x['id'])),
            'value' => (int)$x['c'],
        ], $rows);
    }

    public function getDashboardStats(int $days = 14, int $top = 10): array
    {
        $from = (new \DateTimeImmutable('today'))->modify('-'.($days-1).' days');

        $byStatus = $this->countByStatu($from);
        $daily    = $this->dailyCounts($days);
        $cats     = $this->categoryBreakdown(30, $top);
        $targets  = $this->topTargets(30, $top);

        $open     = (int)($byStatus[Report::STATUS_OPEN] ?? 0);
        $resolved = (int)($byStatus[Report::STATUS_RESOLVED] ?? 0);
        $canceled = (int)($byStatus[Report::STATUS_CANCELED] ?? 0);

        return [
            'kpi' => [
                'open' => $open,
                'resolved' => $resolved,
                'canceled' => $canceled,
                'total' => $open + $resolved + $canceled,
            ],
            'byStatus' => $byStatus,
            'daily' => $daily,
            'categories' => $cats,
            'topTargets' => $targets,
        ];
    }

    public function findOpenByReporterAndTarget(User $reporter, User $target): ?Report
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.reporter = :me')
            ->andWhere('r.targetUser = :tu')
            ->andWhere('r.status = :st')
            ->setParameter('me', $reporter)
            ->setParameter('tu', $target)
            ->setParameter('st', Report::STATUS_OPEN)
            ->orderBy('r.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }


    /**
     * Requête paginée des signalements d’un utilisateur (reporter) :
     * - joint targetUser + categorie pour éviter le N+1
     * - filtre supprimer = 0 (suppression logique)
     * - tri par createdAt DESC
     */
    public function paginateMyReports(User $me, int $page = 1, int $limit = 5): array
    {
        $page = max(1, $page);
        $limit = max(1, $limit);
        $offset = ($page - 1) * $limit;

        $qb = $this->createQueryBuilder('r')
            ->leftJoin('r.targetUser', 'tu')->addSelect('tu')
            ->leftJoin('r.categorie', 'c')->addSelect('c')
            ->andWhere('r.reporter = :me')
            ->andWhere('r.supprimer = 0')
            ->setParameter('me', $me)
            ->orderBy('r.createdAt', 'DESC');

        // Total (sans pagination)
        $total = (int) (clone $qb)
            ->select('COUNT(r.id)')
            ->getQuery()
            ->getSingleScalarResult();

        // Items paginés
        $items = $qb
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $pages = max(1, (int) ceil($total / $limit));

        return [
            'items' => $items,
            'pagination' => [
                'page' => $page,
                'pages' => $pages,
                'limit' => $limit,
                'total' => $total,
                'hasPrev' => $page > 1,
                'hasNext' => $page < $pages,
                'prev' => max(1, $page - 1),
                'next' => min($pages, $page + 1),
            ],
        ];
    }

    // =========================================================
    // CÔTÉ UTILISATEUR (front)
    // =========================================================

    /**
     * ✅ Retourne le signalement OPEN (actif) fait par $reporter sur l'utilisateur cible ($targetUserId).
     * Utile pour afficher le bouton "Signaler" / "Annuler le signalement".
     */
    public function findOpenUserReport(User $reporter, int $targetUserId): ?Report
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.reporter = :rep')
            ->andWhere('r.targetUser = :target')
            ->andWhere('r.status = :st')
            ->setParameter('rep', $reporter)
            ->setParameter('target', $targetUserId) // Doctrine accepte aussi un User si tu préfères
            ->setParameter('st', Report::STATUS_OPEN)
            ->orderBy('r.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * ✅ Vérifie si $reporter a déjà un signalement OPEN sur $target.
     * Version booléenne (plus rapide à utiliser).
     */
    public function isUserReportedBy(User $reporter, User $target): bool
    {
        $count = (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.reporter = :rep')
            ->andWhere('r.targetUser = :target')
            ->andWhere('r.status = :st')
            ->setParameter('rep', $reporter)
            ->setParameter('target', $target)
            ->setParameter('st', Report::STATUS_OPEN)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    /**
     * ✅ Retourne le dernier signalement (tous statuts) d’un reporter sur une cible.
     * Utile pour empêcher spam, logs, historique.
     */
    public function findLastReportBetweenUsers(User $reporter, User $target): ?Report
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.reporter = :rep')
            ->andWhere('r.targetUser = :target')
            ->setParameter('rep', $reporter)
            ->setParameter('target', $target)
            ->orderBy('r.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    // =========================================================
    // CÔTÉ ADMIN (back-office)
    // =========================================================

    /**
     * ✅ Liste paginée des signalements (admin)
     *
     * Filtres possibles:
     * - status: open|canceled|resolved (ou null = tous)
     * - q: recherche sur email/nom reporter ou cible
     * - sort: new|old
     *
     * Retour:
     * [
     *   'items' => Report[],
     *   'total' => int,
     *   'page'  => int,
     *   'limit' => int,
     *   'pages' => int
     * ]
     */
    public function paginateAdminReports(
        ?string $status = null,
        string $q = '',
        string $sort = 'new',
        int $page = 1,
        int $limit = 20
    ): array {
        $page  = max(1, $page);
        $limit = max(1, min(100, $limit));
        $offset = ($page - 1) * $limit;

        $qb = $this->createQueryBuilder('r')
            ->leftJoin('r.reporter', 'rep')->addSelect('rep')
            ->leftJoin('r.targetUser', 'tgt')->addSelect('tgt')
            ->leftJoin('r.handledBy', 'hb')->addSelect('hb');

        // Filtre statut
        if ($status) {
            $qb->andWhere('r.status = :st')->setParameter('st', $status);
        }

        // Recherche (reporter/cible)
        $q = trim($q);
        if ($q !== '') {
            $qb->andWhere('(rep.email LIKE :q OR tgt.email LIKE :q OR rep.fullName LIKE :q OR tgt.fullName LIKE :q)')
               ->setParameter('q', '%' . $q . '%');
        }

        // Tri
        if ($sort === 'old') {
            $qb->orderBy('r.createdAt', 'ASC');
        } else {
            $qb->orderBy('r.createdAt', 'DESC');
        }

        // Pagination
        $qb->setFirstResult($offset)->setMaxResults($limit);

        $p = new Paginator($qb, true);
        $total = count($p);

        return [
            'items' => iterator_to_array($p),
            'total' => $total,
            'page'  => $page,
            'limit' => $limit,
            'pages' => (int) max(1, ceil($total / $limit)),
        ];
    }

    /**
     * ✅ Retourne le nombre de signalements OPEN (actifs) reçus par un utilisateur.
     * Utile pour afficher un badge "X signalements" dans l’admin.
     */
    public function countOpenReportsForTarget(User $target): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.targetUser = :t')
            ->andWhere('r.status = :st')
            ->setParameter('t', $target)
            ->setParameter('st', Report::STATUS_OPEN)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * ✅ Retourne le nombre total de signalements (tous statuts) reçus par un utilisateur.
     * Utile pour analyser l’historique.
     */
    public function countAllReportsForTarget(User $target): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.targetUser = :t')
            ->setParameter('t', $target)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * ✅ Retourne la liste des signalements OPEN d’un utilisateur (détails)
     * Utile pour afficher "dossiers" avant décision admin.
     */
    public function findOpenReportsForTarget(User $target, int $limit = 50): array
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.reporter', 'rep')->addSelect('rep')
            ->andWhere('r.targetUser = :t')
            ->andWhere('r.status = :st')
            ->setParameter('t', $target)
            ->setParameter('st', Report::STATUS_OPEN)
            ->orderBy('r.createdAt', 'DESC')
            ->setMaxResults(max(1, min(200, $limit)))
            ->getQuery()
            ->getResult();
    }

    /**
     * ✅ Top utilisateurs les plus signalés (OPEN uniquement).
     * Utile pour dashboard admin.
     *
     * Retour:
     * [
     *   ['userId' => 12, 'count' => 5],
     *   ...
     * ]
     */
    public function topReportedUsers(int $limit = 10): array
    {
        return $this->createQueryBuilder('r')
            ->select('IDENTITY(r.targetUser) AS userId, COUNT(r.id) AS count')
            ->andWhere('r.status = :st')
            ->setParameter('st', Report::STATUS_OPEN)
            ->groupBy('r.targetUser')
            ->orderBy('count', 'DESC')
            ->setMaxResults(max(1, min(50, $limit)))
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * ✅ Signalements créés dans une période (admin).
     * Exemple: pour afficher les signalements du mois.
     */
    public function findReportsBetween(\DateTimeImmutable $from, \DateTimeImmutable $to, ?string $status = null): array
    {
        $qb = $this->createQueryBuilder('r')
            ->leftJoin('r.reporter', 'rep')->addSelect('rep')
            ->leftJoin('r.targetUser', 'tgt')->addSelect('tgt')
            ->andWhere('r.createdAt BETWEEN :from AND :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('r.createdAt', 'DESC');

        if ($status) {
            $qb->andWhere('r.status = :st')->setParameter('st', $status);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * ✅ Liste des signalements "à traiter" (OPEN) par ordre de priorité.
     * Ici la priorité = plus ancien d'abord (FIFO).
     */
    public function findOpenReportsToHandle(int $limit = 100): array
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.reporter', 'rep')->addSelect('rep')
            ->leftJoin('r.targetUser', 'tgt')->addSelect('tgt')
            ->andWhere('r.status = :st')
            ->setParameter('st', Report::STATUS_OPEN)
            ->orderBy('r.createdAt', 'ASC')
            ->setMaxResults(max(1, min(500, $limit)))
            ->getQuery()
            ->getResult();
    }

    /**
     * ✅ Recherche rapide des signalements d’un utilisateur cible par son ID.
     * Utile quand l’admin est sur la page d’un user et veut son historique.
     */
    public function findReportsForTargetUserId(int $targetUserId, int $limit = 100): array
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.reporter', 'rep')->addSelect('rep')
            ->leftJoin('r.handledBy', 'hb')->addSelect('hb')
            ->andWhere('r.targetUser = :t')
            ->setParameter('t', $targetUserId)
            ->orderBy('r.createdAt', 'DESC')
            ->setMaxResults(max(1, min(500, $limit)))
            ->getQuery()
            ->getResult();
    }

    /**
     * ✅ Compte combien de signalements un admin a traités (audit).
     * Utile pour suivi modération.
     */
    public function countResolvedByAdmin(User $admin, ?\DateTimeImmutable $from = null, ?\DateTimeImmutable $to = null): int
    {
        $qb = $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.handledBy = :a')
            ->andWhere('r.status = :st')
            ->setParameter('a', $admin)
            ->setParameter('st', Report::STATUS_RESOLVED);

        if ($from) {
            $qb->andWhere('r.handledAt >= :from')->setParameter('from', $from);
        }
        if ($to) {
            $qb->andWhere('r.handledAt <= :to')->setParameter('to', $to);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }


    /**
     * Compte le nombre de signalements actuellement ouverts.
     * 
     * Définition métier :
     * - Un report OPEN = report non encore traité par un modérateur
     * - Utilisé pour les KPI du tableau de bord modérateur
     */
    public function countOpenReports(): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.status = :st')
            ->setParameter('st', 'OPEN')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Compte les signalements OPEN liés à un utilisateur.
     * 
     * Modèle actuel :
     * - Report ne cible que targetUser (pas targetJob)
     */
    public function countOpenReportsWithTargetJob(): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.status = :st')
            ->andWhere('r.targetUser IS NOT NULL')
            ->setParameter('st', Report::STATUS_OPEN)
            ->getQuery()
            ->getSingleScalarResult();
    }



    /**
     * Compte les signalements OPEN (sur comptes).
     * Ici, un "suspect" = un report ouvert sur un targetUser.
     */
    public function countOpenReportsOnUsers(): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.status = :st')
            ->andWhere('r.targetUser IS NOT NULL')
            ->setParameter('st', Report::STATUS_OPEN)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Récupère les derniers signalements (tri décroissant).
     *
     * Usage :
     * - Tableau de bord modérateur
     * - Vue rapide des nouveaux problèmes
     *
     * @return Report[]
     */
    public function findLatest(int $limit = 12): array
    {
        return $this->createQueryBuilder('r')
            ->orderBy('r.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les dernières décisions de modération.
     *
     * États concernés :
     * - REVIEWED  → examiné
     * - REJECTED  → rejeté
     * - RESOLVED  → résolu
     *
     * Logique UX :
     * - Affichage chronologique basé sur handledAt
     *
     * @return Report[]
     */
    public function findLastDecisions(int $limit = 10): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.status IN (:st)')
            ->setParameter('st', ['REVIEWED', 'REJECTED', 'RESOLVED'])
            ->orderBy('r.handledAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function analyticsReportsDaily(int $days): array
    {
        $from = (new \DateTimeImmutable())->modify("-{$days} days")->setTime(0, 0, 0);

        $rows = $this->createQueryBuilder('r')
            ->select("SUBSTRING(r.createdAt, 1, 10) as d, COUNT(r.id) as c")
            ->andWhere('r.createdAt >= :from')->setParameter('from', $from)
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

        $row = $this->createQueryBuilder('r')
            ->select("
            SUM(CASE WHEN r.status = 'open' THEN 1 ELSE 0 END) as open,
            SUM(CASE WHEN r.status = 'resolved' THEN 1 ELSE 0 END) as resolved,
            SUM(CASE WHEN r.status = 'canceled' THEN 1 ELSE 0 END) as canceled
            ")
            ->andWhere('r.createdAt >= :from')->setParameter('from', $from)
            ->getQuery()->getOneOrNullResult();

        return [
            'open' => (int)($row['open'] ?? 0),
            'resolved' => (int)($row['resolved'] ?? 0),
            'canceled' => (int)($row['canceled'] ?? 0),
        ];
    }

    public function analyticsTopTargets(int $top, int $days): array
    {
        $from = (new \DateTimeImmutable())->modify("-{$days} days")->setTime(0,0,0);

        $rows = $this->createQueryBuilder('r')
            ->select('tu.id as id, tu.email as label, COUNT(r.id) as value')
            ->innerJoin('r.targetUser', 'tu')
            ->andWhere('r.createdAt >= :from')->setParameter('from', $from)
            ->groupBy('tu.id')
            ->orderBy('value', 'DESC')
            ->setMaxResults($top)
            ->getQuery()->getArrayResult();

        return array_map(fn($x)=>['label'=>$x['label'] ?: ('#'.$x['id']), 'value'=>(int)$x['value']], $rows);
    }

    public function analyticsTopCategories(int $top, int $days): array
    {
        $from = (new \DateTimeImmutable())->modify("-{$days} days")->setTime(0,0,0);

        $rows = $this->createQueryBuilder('r')
            ->select('c.label as label, COUNT(r.id) as value')
            ->innerJoin('r.categorie', 'c')
            ->andWhere('r.createdAt >= :from')->setParameter('from', $from)
            ->groupBy('c.id')
            ->orderBy('value', 'DESC')
            ->setMaxResults($top)
            ->getQuery()->getArrayResult();

        return array_map(fn($x)=>['label'=>$x['label'] ?: '—', 'value'=>(int)$x['value']], $rows);
    }


    /**
     * Stats (par statut) des signalements reçus par un compte (targetUser).
     */
    public function statsForTargetUser(int $userId, int $days = 30): array
    {
        $from = (new \DateTimeImmutable())->modify("-{$days} days")->setTime(0,0,0);

        $rows = $this->createQueryBuilder('r')
            ->select('r.status AS st, COUNT(r.id) AS c')
            ->andWhere('r.targetUser = :u')->setParameter('u', $userId)
            ->andWhere('r.createdAt >= :from')->setParameter('from', $from)
            ->groupBy('r.status')
            ->getQuery()->getArrayResult();

        $out = ['open'=>0,'resolved'=>0,'canceled'=>0,'total'=>0];
        foreach ($rows as $row) {
            $st = (string) $row['st'];
            $c  = (int) $row['c'];
            if (!isset($out[$st])) $out[$st] = 0;
            $out[$st] += $c;
            $out['total'] += $c;
        }
        return $out;
    }

    public function slaStats(int $days = 14): array
    {
        $from = (new \DateTimeImmutable())->modify("-{$days} days")->setTime(0,0,0);

        $rows = $this->createQueryBuilder('r')
            ->select('r.createdAt, r.handledAt')
            ->andWhere('r.handledAt IS NOT NULL')
            ->andWhere('r.createdAt >= :from')->setParameter('from', $from)
            ->getQuery()->getArrayResult();

        $sumH = 0.0;
        $count = 0;

        $byDay = []; // Y-m-d => [sumH,count]
        foreach ($rows as $row) {
            $c = $row['createdAt'];
            $h = $row['handledAt'];
            if (!$c || !$h) continue;

            $diffSeconds = $h->getTimestamp() - $c->getTimestamp();
            $hours = max(0, $diffSeconds / 3600);

            $sumH += $hours;
            $count++;

            $d = $h->format('Y-m-d');
            if (!isset($byDay[$d])) $byDay[$d] = ['sum'=>0.0,'count'=>0];
            $byDay[$d]['sum'] += $hours;
            $byDay[$d]['count']++;
        }

        $avg = $count ? round($sumH / $count, 2) : 0.0;

        // série daily
        $labels = [];
        $values = [];
        $counts = [];

        for ($i=0; $i <= $days; $i++) {
            $d = $from->modify("+{$i} days")->format('Y-m-d');
            $labels[] = $d;
            $c = $byDay[$d]['count'] ?? 0;
            $counts[] = $c;
            $values[] = $c ? round(($byDay[$d]['sum'] / $c), 2) : 0;
        }

        return [
            'avgHours' => $avg,
            'countHandled' => $count,
            'daily' => [
                'labels' => $labels,
                'avgHours' => $values,
                'handledCount' => $counts,
            ],
        ];
    }


    public function countHandledReports(int $days = 30): int
    {
        $from = (new \DateTimeImmutable())->modify("-{$days} days")->setTime(0,0,0);

        return (int)$this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.handledAt IS NOT NULL')
            ->andWhere('r.handledAt >= :from')->setParameter('from', $from)
            ->getQuery()->getSingleScalarResult();
    }


    public function riskStats(int $days = 14, int $top = 10): array
    {
        $from = (new \DateTimeImmutable())->modify("-{$days} days")->setTime(0,0,0);

        $rows = $this->createQueryBuilder('r')
            ->select('r.id, r.createdAt, r.status, IDENTITY(r.targetUser) AS tid')
            ->andWhere('r.createdAt >= :from')->setParameter('from', $from)
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()->getArrayResult();

        $byDay = [];
        $byStatus = ['open'=>0,'resolved'=>0,'canceled'=>0];
        $topTargets = [];
        $openByTarget = [];

        foreach ($rows as $r) {
            $dt = $r['createdAt'];
            $day = $dt->format('Y-m-d');
            $byDay[$day] = ($byDay[$day] ?? 0) + 1;

            $st = (string)($r['status'] ?? 'open');
            if (!isset($byStatus[$st])) $byStatus[$st] = 0;
            $byStatus[$st]++;

            $tid = $r['tid'] ? (int)$r['tid'] : 0;
            if ($tid > 0) {
                $topTargets[$tid] = ($topTargets[$tid] ?? 0) + 1;
                if ($st === 'open') $openByTarget[$tid] = ($openByTarget[$tid] ?? 0) + 1;
            }
        }

        $labels = [];
        $values = [];
        for ($i=0; $i<=$days; $i++) {
            $d = $from->modify("+{$i} days")->format('Y-m-d');
            $labels[] = $d;
            $values[] = $byDay[$d] ?? 0;
        }

        arsort($topTargets);
        $topTargetsArr = [];
        foreach (array_slice($topTargets, 0, $top, true) as $uid=>$v) {
            $topTargetsArr[] = ['label'=>'#'.$uid, 'value'=>$v, 'userId'=>$uid];
        }

        return [
            'kpi' => [
                'open' => $byStatus['open'] ?? 0,
                'resolved' => $byStatus['resolved'] ?? 0,
                'canceled' => $byStatus['canceled'] ?? 0,
                'total' => array_sum($byStatus),
            ],
            'daily' => ['labels'=>$labels,'values'=>$values],
            'byStatus' => $byStatus,
            'topTargets' => $topTargetsArr,
            'openByTarget' => $openByTarget,
        ];
    }


    public function searchForModeration(string $q, int $limit = 30): array
    {
        $q = trim($q);
        if ($q === '') return [];

        $em = $this->getEntityManager();

        $qb = $this->createQueryBuilder('r')
            ->leftJoin('r.reporter', 'rep')->addSelect('rep')
            ->leftJoin('r.targetUser', 'tgt')->addSelect('tgt')
            ->leftJoin('r.handledBy', 'hb')->addSelect('hb')
            ->leftJoin('r.categorie', 'cat')->addSelect('cat');

        $meta = [
            'r'   => $em->getClassMetadata(Report::class),
            'rep' => $em->getClassMetadata(User::class),
            'tgt' => $em->getClassMetadata(User::class),
            'hb'  => $em->getClassMetadata(User::class),
            'cat' => $em->getClassMetadata(CategorieReport::class),
        ];

        $or = new Orx();

        $addLike = function (string $alias, string $field) use ($qb, $meta, $or): void {
            if ($meta[$alias]->hasField($field)) {
                $or->add($qb->expr()->like($alias.'.'.$field, ':q'));
            }
        };

        // Champs Report
        $addLike('r', 'reason');
        $addLike('r', 'status');
        $addLike('r', 'slug');

        // Champs User (ajoute seulement si le champ existe dans ton User)
        $addLike('rep', 'email');
        $addLike('rep', 'phone');
        $addLike('rep', 'fullName');

        $addLike('tgt', 'email');
        $addLike('tgt', 'phone');
        $addLike('tgt', 'fullName');

        $addLike('hb', 'email');
        $addLike('hb', 'fullName');

        // Champs CategorieReport (selon ton entité : name / label / title…)
        $addLike('cat', 'name');
        $addLike('cat', 'label');
        $addLike('cat', 'title');

        // Si q est numérique => recherche aussi par IDs
        if (ctype_digit($q)) {
            $qb->setParameter('id', (int) $q);
            $or->add($qb->expr()->eq('r.id', ':id'));
            $or->add($qb->expr()->eq('rep.id', ':id'));
            $or->add($qb->expr()->eq('tgt.id', ':id'));
            $or->add($qb->expr()->eq('hb.id', ':id'));
            $or->add($qb->expr()->eq('cat.id', ':id'));
        }

        // Sécurité: si aucun champ n’a été ajouté (cas rare), on renvoie vide
        if (count($or->getParts()) === 0) {
            return [];
        }

        $qb->andWhere($or)
            ->setParameter('q', '%'.$q.'%')
            ->setMaxResults($limit)
            ->orderBy('r.createdAt', 'DESC');

        return $qb->getQuery()->getResult();
    }

    public function countByStatuses(array $statuses): int
    {
        $dql = <<<DQL
            SELECT COUNT(r.id)
            FROM App\Entity\Report r
            WHERE r.status IN (:st)
        DQL;

        return (int) $this->getEntityManager()
            ->createQuery($dql)
            ->setParameter('st', $statuses)
            ->getSingleScalarResult();
    }

    public function qbSearch(array $filters = []): QueryBuilder
    {
        $qb = $this->createQueryBuilder('r')
            ->leftJoin('r.reporter', 'rep')->addSelect('rep')
            ->leftJoin('r.targetUser', 'tgt')->addSelect('tgt')
            ->leftJoin('r.handledBy', 'hb')->addSelect('hb')
            ->leftJoin('r.categorie', 'cat')->addSelect('cat');

        // Statut
        if (!empty($filters['status'])) {
            $qb->andWhere('r.status = :status')
               ->setParameter('status', $filters['status']);
        }

        // "type" => ici on le fait correspondre à la catégorie
        if (!empty($filters['type'])) {
            $qb->andWhere('cat.slug = :type OR cat.name = :type OR cat.label = :type OR cat.title = :type')
               ->setParameter('type', $filters['type']);
        }

        // priorité : ton entité n'a pas ce champ, donc on ignore
        // si un jour tu ajoutes un champ priority, on le remettra

        // Recherche texte
        if (!empty($filters['q'])) {
            $q = trim((string) $filters['q']);
            $or = new Orx();

            $or->add($qb->expr()->like('r.reason', ':q'));
            $or->add($qb->expr()->like('r.status', ':q'));
            $or->add($qb->expr()->like('r.slug', ':q'));

            $or->add($qb->expr()->like('rep.email', ':q'));
            $or->add($qb->expr()->like('rep.phone', ':q'));

            $or->add($qb->expr()->like('tgt.email', ':q'));
            $or->add($qb->expr()->like('tgt.phone', ':q'));

            $or->add($qb->expr()->like('hb.email', ':q'));

            // Selon les champs réellement présents dans CategorieReport
            $or->add($qb->expr()->like('cat.name', ':q'));

            if (ctype_digit($q)) {
                $qb->setParameter('id', (int) $q);
                $or->add($qb->expr()->eq('r.id', ':id'));
                $or->add($qb->expr()->eq('rep.id', ':id'));
                $or->add($qb->expr()->eq('tgt.id', ':id'));
                $or->add($qb->expr()->eq('hb.id', ':id'));
                $or->add($qb->expr()->eq('cat.id', ':id'));
            }

            $qb->andWhere($or)
               ->setParameter('q', '%' . $q . '%');
        }

        // Tri
        $sort = $filters['sort'] ?? 'createdAt';
        $dir  = strtoupper($filters['dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';

        switch ($sort) {
            case 'status':
                $qb->orderBy('r.status', $dir);
                break;

            case 'category':
                $qb->orderBy('cat.name', $dir);
                break;

            case 'createdAt':
            default:
                $qb->orderBy('r.createdAt', $dir);
                break;
        }

        return $qb;
    }

    public function getDistinctTypes(int $limit = 40): array
    {
        // Ici "types" = catégories
        $rows = $this->createQueryBuilder('r')
            ->select('DISTINCT cat.label AS value')
            ->leftJoin('r.categorie', 'cat')
            ->where('cat.label IS NOT NULL')
            ->orderBy('cat.label', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();

        return array_values(array_filter(array_map(
            static fn(array $row) => $row['value'] ?? null,
            $rows
        )));
    }

    public function countCreatedSince(\DateTimeInterface $since): int
    {
        $dql = <<<DQL
            SELECT COUNT(r.id)
            FROM App\Entity\Report r
            WHERE r.createdAt >= :since
        DQL;

        return (int) $this->getEntityManager()
            ->createQuery($dql)
            ->setParameter('since', $since)
            ->getSingleScalarResult();
    }

    public function countResolvedSince(\DateTimeInterface $since, string $resolvedStatus = 'resolved'): int
    {
        $dql = <<<DQL
            SELECT COUNT(r.id)
            FROM App\Entity\Report r
            WHERE r.createdAt >= :since
              AND r.status = :resolved
        DQL;

        return (int) $this->getEntityManager()
            ->createQuery($dql)
            ->setParameter('since', $since)
            ->setParameter('resolved', $resolvedStatus)
            ->getSingleScalarResult();
    }

    /**
     * Retourne: ['total'=>int,'resolved'=>int,'rate'=>string]
     */
    public function getResolutionRateSince(\DateTimeInterface $since): array
    {
        $total = $this->countCreatedSince($since);
        $resolved = $this->countResolvedSince($since, 'resolved');

        $rate = $total > 0 ? ((int) round(($resolved / $total) * 100)) . '%' : '—';

        return ['total' => $total, 'resolved' => $resolved, 'rate' => $rate];
    }

    public function exportRows(int $limit = 2000): iterable
    {
        $rows = $this->createQueryBuilder('r')
            ->leftJoin('r.reporter', 'rep')->addSelect('rep')
            ->leftJoin('r.targetUser', 'tgt')->addSelect('tgt')
            ->leftJoin('r.handledBy', 'hb')->addSelect('hb')
            ->leftJoin('r.categorie', 'cat')->addSelect('cat')
            ->orderBy('r.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $safeCall = static function ($obj, string $method, string $default = ''): string {
            if (!$obj) return $default;
            if (!method_exists($obj, $method)) return $default;
            $v = $obj->$method();
            return $v === null ? $default : (string) $v;
        };

        foreach ($rows as $r) {
            $rep = $r->getReporter();    // peut être null si DB incohérente
            $tgt = $r->getTargetUser();  // peut être null si DB incohérente
            $hb  = $r->getHandledBy();   // nullable normal
            $cat = $r->getCategorie();   // peut être null selon tes données

            yield [
                (string)($r->getId() ?? ''),
                $r->getStatus(),
                (string)($r->getSlug() ?? ''),
                $r->getCreatedAt()->format('Y-m-d H:i:s'),

                (string)($rep?->getId() ?? ''),
                $safeCall($rep, 'getEmail'),
                $safeCall($rep, 'getPhone'),
                $safeCall($rep, 'getFullName'),

                (string)($tgt?->getId() ?? ''),
                $safeCall($tgt, 'getEmail'),
                $safeCall($tgt, 'getPhone'),
                $safeCall($tgt, 'getFullName'),

                (string)($hb?->getId() ?? ''),
                $safeCall($hb, 'getEmail'),
                $safeCall($hb, 'getFullName'),

                (string)($cat?->getId() ?? ''),
                $safeCall($cat, 'getName', $safeCall($cat, 'getLabel', $safeCall($cat, 'getTitle'))),

                $r->getReason(),
            ];
        }
    }


    public function countAllReports(): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countByStatus(string $status): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.status = :status')
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countReportsSince(\DateTimeInterface $since): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.createdAt >= :since')
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getReportsChartSince(\DateTimeInterface $since): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = '
            SELECT DATE(created_at) AS dte, COUNT(id) AS c
            FROM report
            WHERE created_at >= :since
            GROUP BY DATE(created_at)
            ORDER BY dte ASC
        ';

        return $conn->executeQuery(
            $sql,
            ['since' => $since->format('Y-m-d H:i:s')]
        )->fetchAllAssociative();
    }

    public function findRecentReports(int $limit = 8): array
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.reporter', 'rep')->addSelect('rep')
            ->leftJoin('r.targetUser', 'tgt')->addSelect('tgt')
            ->leftJoin('r.handledBy', 'hb')->addSelect('hb')
            ->leftJoin('r.categorie', 'cat')->addSelect('cat')
            ->orderBy('r.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function getTopReportedUsers(int $limit = 8): array
    {
        return $this->createQueryBuilder('r')
            ->select('tgt.email AS email, COUNT(r.id) AS total')
            ->leftJoin('r.targetUser', 'tgt')
            ->groupBy('tgt.id, tgt.email')
            ->orderBy('total', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();
    }

    public function getTopCategoriesSince(\DateTimeInterface $since, int $limit = 8): array
    {
        return $this->createQueryBuilder('r')
            ->select('cat.id AS id, COUNT(r.id) AS total')
            ->leftJoin('r.categorie', 'cat')
            ->andWhere('r.createdAt >= :since')
            ->setParameter('since', $since)
            ->groupBy('cat.id')
            ->orderBy('total', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();
    }

    public function countOpenGroupedByCategory(): array
    {
        return $this->createQueryBuilder('r')
            ->select('LOWER(c.label) AS label, COUNT(r.id) AS total')
            ->innerJoin('r.categorie', 'c')
            ->andWhere('r.status = :status')
            ->setParameter('status', Report::STATUS_OPEN)
            ->groupBy('c.id, c.label')
            ->getQuery()
            ->getArrayResult();
    }
}
