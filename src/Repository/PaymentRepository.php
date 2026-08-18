<?php

namespace App\Repository;

use App\Entity\Payment;
use App\Entity\PaymentDispute;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Payment>
 */
class PaymentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Payment::class);
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
    
    /**
     * Paiements d'un user avec Provider + Status + Invoices (pour Twig p.invoices)
     */
    public function findWithInvoicesByUser(User $user, int $limit = 20): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.provider', 'pr')->addSelect('pr')
            ->leftJoin('p.statusPayment', 'sp')->addSelect('sp')
            ->leftJoin('p.invoices', 'inv')->addSelect('inv')
            ->andWhere('p.user = :u')
            ->setParameter('u', $user)
            ->orderBy('p.paidAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Total payé (optionnel KPI)
     */
    public function sumPaidByUser(User $user): int
    {
        // Si amount est string, on somme en le traitant comme numérique.
        // (si possible, change amount en DECIMAL dans l'entité)
        return (int) $this->createQueryBuilder('p')
            ->select('COALESCE(SUM(p.amount), 0)') // OK si amount est numeric en DB
            ->leftJoin('p.statusPayment', 'sp')
            ->andWhere('p.user = :u')
            ->andWhere('sp.statusPayment IN (:ok) OR sp.statusPayment IN (:ok)')
            ->setParameter('u', $user)
            ->setParameter('ok', ['SUCCES', 'SUCCESS', 'PAYE', 'PAID'])
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Somme totale des revenus (SUM(amount)).
     * Retourne 0 si aucun paiement.
     */
    public function sumAllRevenue(): float
    {
        return (float) $this->createQueryBuilder('p')
            ->select('COALESCE(SUM(p.amount), 0)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Récupère les derniers paiements.
     *
     * @param int $limit Nombre max de résultats (ex: 10)
     * @return Payment[]
     */
    public function findLatest(int $limit = 10): array
    {
        return $this->createQueryBuilder('p')
            ->orderBy('p.id', 'DESC')
            ->setMaxResults(max(1, $limit))
            ->getQuery()
            ->getResult();
    }
    

    // ✅ Derniers paiements de l'utilisateur (avec provider + status + subscription + plan)
    public function findLatestByUser(int $userId, int $limit = 5): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.provider', 'prov')->addSelect('prov')
            ->leftJoin('p.statusPayment', 'sp')->addSelect('sp')
            ->leftJoin('p.subscription', 'sub')->addSelect('sub')
            ->leftJoin('sub.plan', 'pl')->addSelect('pl')
            ->andWhere('p.user = :uid')
            ->setParameter('uid', $userId)
            ->orderBy('p.paidAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    // ✅ Count paiements user
    public function countByUser(int $userId): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.user = :uid')
            ->setParameter('uid', $userId)
            ->getQuery()
            ->getSingleScalarResult();
    }


    public function countAllPayments(): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countPaymentsSince(\DateTimeInterface $since): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.paidAt >= :since')
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findPaymentsSince(\DateTimeInterface $since): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.paidAt >= :since')
            ->setParameter('since', $since)
            ->getQuery()
            ->getResult();
    }

    public function findRecentPayments(int $limit = 8): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.user', 'u')->addSelect('u')
            ->leftJoin('p.provider', 'pr')->addSelect('pr')
            ->leftJoin('p.subscription', 's')->addSelect('s')
            ->leftJoin('p.statusPayment', 'sp')->addSelect('sp')
            ->orderBy('p.paidAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function getPaymentsChartSince(\DateTimeInterface $since): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = '
            SELECT DATE(paid_at) AS d, COUNT(id) AS c
            FROM payment
            WHERE paid_at >= :since
            GROUP BY DATE(paid_at)
            ORDER BY d ASC
        ';

        return $conn->executeQuery(
            $sql,
            ['since' => $since->format('Y-m-d H:i:s')]
        )->fetchAllAssociative();
    }

    public function getPaymentsAmountChartSince(\DateTimeInterface $since): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = '
            SELECT DATE(paid_at) AS d, SUM(amount) AS total
            FROM payment
            WHERE paid_at >= :since
            GROUP BY DATE(paid_at)
            ORDER BY d ASC
        ';

        return $conn->executeQuery(
            $sql,
            ['since' => $since->format('Y-m-d H:i:s')]
        )->fetchAllAssociative();
    }

    public function countPaymentsToday(): int
    {
        $start = new \DateTimeImmutable('today');
        $end = $start->modify('+1 day');

        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.paidAt >= :start')
            ->andWhere('p.paidAt < :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function searchAdmin(string $q): array
    {
        return $this->createQueryBuilder('t')
            ->select('t.id, t.reference, t.amount')
            ->where('t.reference LIKE :q')
            ->setParameter('q', "%$q%")
            ->setMaxResults(5)
            ->getQuery()
            ->getArrayResult();
    }

    public function getRevenueChartSince(\DateTimeInterface $since): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = '
            SELECT DATE(paid_at) AS d, SUM(amount) AS total
            FROM payment
            WHERE paid_at >= :since
            GROUP BY DATE(paid_at)
            ORDER BY d ASC
        ';

        return $conn->executeQuery(
            $sql,
            ['since' => $since->format('Y-m-d H:i:s')]
        )->fetchAllAssociative();
    }

    public function getTotalRevenueSince(\DateTimeInterface $since): float
    {
        $rows = $this->createQueryBuilder('p')
            ->select('p.amount AS amount')
            ->andWhere('p.paidAt >= :since')
            ->setParameter('since', $since)
            ->getQuery()
            ->getArrayResult();

        $total = 0.0;

        foreach ($rows as $row) {
            $total += (float) str_replace([' ', ','], ['', '.'], (string) $row['amount']);
        }

        return $total;
    }

    public function getTopProvidersSince(\DateTimeInterface $since, int $limit = 8): array
    {
        $rows = $this->createQueryBuilder('p')
            ->select('pr.provider AS provider, COUNT(p.id) AS total')
            ->leftJoin('p.provider', 'pr')
            ->andWhere('p.paidAt >= :since')
            ->setParameter('since', $since)
            ->groupBy('pr.id, pr.provider')
            ->orderBy('total', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();

        return $rows;
    }


    public function getMrrStats(): array
    {
        $now = new \DateTimeImmutable();

        $currentMonthStart = $now->modify('first day of this month')->setTime(0, 0, 0);
        $nextMonthStart = $currentMonthStart->modify('+1 month');
        $previousMonthStart = $currentMonthStart->modify('-1 month');

        $currentMrr = $this->sumRecurringRevenueBetween($currentMonthStart, $nextMonthStart);
        $previousMrr = $this->sumRecurringRevenueBetween($previousMonthStart, $currentMonthStart);

        $delta = 0.0;
        if ($previousMrr > 0) {
            $delta = (($currentMrr - $previousMrr) / $previousMrr) * 100;
        } elseif ($currentMrr > 0) {
            $delta = 100.0;
        }

        $progress = 0;
        if ($previousMrr > 0) {
            $progress = (int) min(100, round(($currentMrr / $previousMrr) * 100));
        } elseif ($currentMrr > 0) {
            $progress = 100;
        }

        return [
            'current' => $currentMrr,
            'previous' => $previousMrr,
            'delta' => round($delta, 1),
            'progress' => $progress,
        ];
    }

    private function sumRecurringRevenueBetween(\DateTimeInterface $start, \DateTimeInterface $end): float
    {
        $rows = $this->createQueryBuilder('p')
            ->select('p.amount AS amount')
            ->andWhere('p.subscription IS NOT NULL')
            ->andWhere('p.paidAt >= :start')
            ->andWhere('p.paidAt < :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getArrayResult();

        $total = 0.0;

        foreach ($rows as $row) {
            $amount = (string) ($row['amount'] ?? '0');

            // Nettoyage : "10 000", "10000", "10000,50"
            $amount = str_replace(' ', '', $amount);
            $amount = str_replace(',', '.', $amount);

            if (is_numeric($amount)) {
                $total += (float) $amount;
            }
        }

        return $total;
    }


    public function getGmvStats(): array
    {
        $gmv = (float) $this->getEntityManager()
            ->createQuery('
                SELECT COALESCE(SUM(p.amount), 0) AS gmv
                FROM App\Entity\Payment p
            ')
            ->getSingleScalarResult();

        return [
            'gmv' => $gmv,
        ];
    }

    public function getRevenueChartSuccessPaymentsByMonth(int $months = 12): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $start = (new \DateTimeImmutable('first day of this month 00:00:00'))
            ->modify('-' . ($months - 1) . ' months');

        $sql = "
            SELECT 
                DATE_FORMAT(p.paid_at, '%Y-%m') AS month_key,
                DATE_FORMAT(p.paid_at, '%m/%Y') AS month_label,
                SUM(CAST(p.amount AS DECIMAL(15,2))) AS total
            FROM payment p
            INNER JOIN status_payment sp ON sp.id = p.status_payment_id
            WHERE UPPER(sp.status_payment) = :status
            AND p.paid_at >= :start_date
            GROUP BY month_key, month_label
            ORDER BY month_key ASC
        ";

        $rows = $conn->fetchAllAssociative($sql, [
            'status' => 'SUCCES',
            'start_date' => $start->format('Y-m-d H:i:s'),
        ]);

        return $rows;
    }
    public function getRevenueChartLast12Months(): array
    {
        $startMonth = new \DateTimeImmutable('first day of this month 00:00:00');
        $start = $startMonth->modify('-11 months');

        $sql = <<<SQL
            SELECT 
                DATE_FORMAT(p.paid_at, '%Y-%m') AS ym,
                SUM(CAST(REPLACE(p.amount, ',', '.') AS DECIMAL(15,2))) AS total
            FROM payment p
            INNER JOIN status_payment sp ON sp.id = p.status_payment_id
            WHERE UPPER(sp.status_payment) = :status
            AND p.paid_at >= :startDate
            GROUP BY DATE_FORMAT(p.paid_at, '%Y-%m')
            ORDER BY ym ASC
        SQL;

        $rows = $this->getEntityManager()
            ->getConnection()
            ->fetchAllAssociative($sql, [
                'status' => 'SUCCES',
                'startDate' => $start->format('Y-m-d H:i:s'),
            ]);

        $indexed = [];
        foreach ($rows as $row) {
            $indexed[$row['ym']] = (float) $row['total'];
        }

        $labels = [];
        $mrr = [];
        $arr = [];

        for ($i = 0; $i < 12; $i++) {
            $month = $start->modify("+{$i} months");
            $key = $month->format('Y-m');
            $value = $indexed[$key] ?? 0.0;

            $labels[] = $month->format('m/Y');
            $mrr[] = round($value, 2);
            $arr[] = round($value * 12, 2);
        }

        return [
            'labels' => $labels,
            'mrr' => $mrr,
            'arr' => $arr,
        ];
    }

    
    /**
     * ✅ CORRIGÉ : Récupère les statistiques de paiements réussis par plan
     */
    public function getPlanMixSuccessPayments(): array
    {
        try {
            $qb = $this->createQueryBuilder('p');
            
            return $qb
                ->select('pl.name AS name')
                ->addSelect('COUNT(p.id) AS count')
                ->addSelect('COALESCE(SUM(CAST(p.amount AS DECIMAL(15,2))), 0) AS mrr')
                ->innerJoin('p.subscription', 's')
                ->innerJoin('s.plan', 'pl')
                ->innerJoin('p.statusPayment', 'sp')
                ->where($qb->expr()->eq($qb->expr()->upper('sp.statusPayment'), ':status'))
                ->setParameter('status', 'SUCCES')
                ->groupBy('pl.id')
                ->addGroupBy('pl.name')
                ->orderBy('pl.name', 'ASC')
                ->getQuery()
                ->getArrayResult();
                
        } catch (\Exception $e) {
            // En cas d'erreur, retourner un tableau vide
            return [];
        }
    }

    /**
     * ✅ CORRIGÉ : Résumé des paiements par plan (version SQL natif)
     */
    public function getPlansPaymentsSummary(): array
    {
        // Vérifier d'abord si les tables et colonnes existent
        $conn = $this->getEntityManager()->getConnection();
        
        // Vérifier le nom réel de la colonne dans la table plan
        $schemaManager = $conn->createSchemaManager();
        $planColumns = $schemaManager->listTableColumns('plan');
        
        // Déterminer le nom de la colonne à utiliser pour le nom du plan
        $nameColumn = 'name'; // par défaut
        foreach ($planColumns as $column) {
            if ($column->getName() === 'name') {
                $nameColumn = 'name';
                break;
            }
        }
        
        // Construire la requête avec le bon nom de colonne
        $sql = "
            SELECT
                pl.{$nameColumn} AS name,
                COUNT(p.id) AS count,
                COALESCE(SUM(CAST(p.amount AS DECIMAL(15,2))), 0) AS total
            FROM payment p
            INNER JOIN subscription s ON s.id = p.subscription_id
            INNER JOIN plan pl ON pl.id = s.plan_id
            INNER JOIN status_payment sp ON sp.id = p.status_payment_id
            WHERE UPPER(sp.status_payment) = :status
            GROUP BY pl.id, pl.{$nameColumn}
            ORDER BY pl.{$nameColumn} ASC
        ";

        try {
            $rows = $conn->fetchAllAssociative($sql, [
                'status' => 'SUCCES',
            ]);

            return array_map(static function (array $row): array {
                return [
                    'name' => $row['name'] ?? 'Inconnu',
                    'count' => (int) ($row['count'] ?? 0),
                    'total' => (float) ($row['total'] ?? 0),
                ];
            }, $rows);
        } catch (\Exception $e) {
            // Logger l'erreur si nécessaire
            // $logger->error('Erreur getPlansPaymentsSummary: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * ✅ CORRIGÉ : Paiements par fournisseur (version corrigée)
     */
    public function getProviderMixSuccessPayments(): array
    {
        // Vérifier le nom réel de la colonne dans la table provider
        $conn = $this->getEntityManager()->getConnection();
        $schemaManager = $conn->createSchemaManager();
        $providerColumns = $schemaManager->listTableColumns('provider');
        
        $nameColumn = 'provider'; // par défaut (correspond à la propriété dans l'entité)
        foreach ($providerColumns as $column) {
            if ($column->getName() === 'provider') {
                $nameColumn = 'provider';
                break;
            } elseif ($column->getName() === 'name') {
                $nameColumn = 'name';
                break;
            }
        }
        
        $sql = "
            SELECT
                pr.{$nameColumn} AS name,
                COUNT(p.id) AS count,
                COALESCE(SUM(CAST(p.amount AS DECIMAL(15,2))), 0) AS total
            FROM payment p
            INNER JOIN provider pr ON pr.id = p.provider_id
            INNER JOIN status_payment sp ON sp.id = p.status_payment_id
            WHERE UPPER(sp.status_payment) = :status
            GROUP BY pr.id, pr.{$nameColumn}
            ORDER BY pr.{$nameColumn} ASC
        ";

        try {
            $rows = $conn->fetchAllAssociative($sql, [
                'status' => 'SUCCES',
            ]);

            return array_map(static function (array $row): array {
                return [
                    'name' => $row['name'] ?? 'Inconnu',
                    'count' => (int) ($row['count'] ?? 0),
                    'total' => (float) ($row['total'] ?? 0),
                ];
            }, $rows);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * ✅ Version alternative avec QueryBuilder (recommandée)
     */
    public function getPlanMixSuccessPaymentsQB(): array
    {
        $qb = $this->createQueryBuilder('p');
        
        return $qb
            ->select('pl.name AS name')
            ->addSelect('COUNT(p.id) AS count')
            ->addSelect('COALESCE(SUM(CAST(p.amount AS DECIMAL(15,2))), 0) AS mrr')
            ->innerJoin('p.subscription', 's')
            ->innerJoin('s.plan', 'pl')
            ->innerJoin('p.statusPayment', 'sp')
            ->where($qb->expr()->eq($qb->expr()->upper('sp.statusPayment'), ':status'))
            ->setParameter('status', 'SUCCES')
            ->groupBy('pl.id')
            ->addGroupBy('pl.name')
            ->orderBy('pl.name', 'ASC')
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * ✅ Version avec gestion complète des erreurs
     */
    public function getSafePlanMixSuccessPayments(): array
    {
        try {
            // Vérifier que les entités et relations existent
            $em = $this->getEntityManager();
            
            // Vérifier si la table plan existe
            $conn = $em->getConnection();
            $schemaManager = $conn->createSchemaManager();
            
            if (!$schemaManager->tablesExist(['plan', 'subscription', 'payment', 'status_payment'])) {
                return [];
            }
            
            // Utiliser le QueryBuilder (plus sûr)
            return $this->getPlanMixSuccessPaymentsQB();
            
        } catch (\Doctrine\DBAL\Exception\TableNotFoundException $e) {
            // Table manquante
            return [];
        } catch (\Doctrine\ORM\Query\QueryException $e) {
            // Erreur de requête
            return [];
        } catch (\Exception $e) {
            // Autre erreur
            return [];
        }
    }


    public function findLatestByStatuses(array $statuses, int $limit = 3): array
    {
        $statuses = array_map('strtoupper', $statuses);

        return $this->createQueryBuilder('p')
            ->leftJoin('p.statusPayment', 'sp')->addSelect('sp')
            ->leftJoin('p.provider', 'pr')->addSelect('pr')
            ->leftJoin('p.user', 'u')->addSelect('u')
            ->leftJoin('p.subscription', 's')->addSelect('s')
            ->leftJoin('s.plan', 'pl')->addSelect('pl')
            ->andWhere('UPPER(sp.statusPayment) IN (:statuses)')
            ->setParameter('statuses', $statuses)
            ->orderBy('p.paidAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findLatestForAdminReview(int $limit = 200): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.statusPayment', 'sp')->addSelect('sp')
            ->leftJoin('p.provider', 'pr')->addSelect('pr')
            ->leftJoin('p.user', 'u')->addSelect('u')
            ->leftJoin('p.subscription', 's')->addSelect('s')
            ->leftJoin('s.plan', 'pl')->addSelect('pl')
            ->orderBy('p.paidAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

     public function createAdminReviewQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.statusPayment', 'sp')->addSelect('sp')
            ->leftJoin('p.provider', 'pr')->addSelect('pr')
            ->leftJoin('p.user', 'u')->addSelect('u')
            ->leftJoin('p.subscription', 's')->addSelect('s')
            ->leftJoin('s.plan', 'pl')->addSelect('pl')
            ->orderBy('p.paidAt', 'DESC');
    }

    public function getAdminPaymentStatusCounts(): array
    {
        $sql = "
            SELECT
                UPPER(sp.status_payment) AS status_code,
                COUNT(p.id) AS total
            FROM payment p
            LEFT JOIN status_payment sp ON sp.id = p.status_payment_id
            GROUP BY UPPER(sp.status_payment)
        ";

        $rows = $this->getEntityManager()
            ->getConnection()
            ->fetchAllAssociative($sql);

        $counts = [];
        foreach ($rows as $row) {
            $counts[$row['status_code']] = (int) $row['total'];
        }

        return $counts;
    }


    public function findLatestPayments(int $limit = 5): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.statusPayment', 'sp')->addSelect('sp')
            ->orderBy('p.paidAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    //    /**
    //     * @return Payment[] Returns an array of Payment objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('p')
    //            ->andWhere('p.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('p.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Payment
    //    {
    //        return $this->createQueryBuilder('p')
    //            ->andWhere('p.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
