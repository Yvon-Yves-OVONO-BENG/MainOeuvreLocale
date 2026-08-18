<?php
// src/Repository/PlanDurationRepository.php
namespace App\Repository;

use App\Entity\PlanDuration;
use App\Entity\Plan;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PlanDuration>
 *
 * @method PlanDuration|null find($id, $lockMode = null, $lockVersion = null)
 * @method PlanDuration|null findOneBy(array $criteria, array $orderBy = null)
 * @method PlanDuration[]    findAll()
 * @method PlanDuration[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PlanDurationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PlanDuration::class);
    }

    /**
     * Trouve toutes les durées pour un plan spécifique
     * 
     * @param Plan $plan
     * @return PlanDuration[]
     */
    public function findByPlan(Plan $plan): array
    {
        return $this->createQueryBuilder('pd')
            ->andWhere('pd.plan = :plan')
            ->setParameter('plan', $plan)
            ->orderBy('pd.months', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les durées groupées par plan
     * 
     * @return array
     */
    public function findAllGroupedByPlan(): array
    {
        $durations = $this->createQueryBuilder('pd')
            ->leftJoin('pd.plan', 'p')
            ->addSelect('p')
            ->orderBy('p.price', 'ASC')
            ->addOrderBy('pd.months', 'ASC')
            ->getQuery()
            ->getResult();

        $grouped = [];
        foreach ($durations as $duration) {
            $planId = $duration->getPlan()->getId();
            if (!isset($grouped[$planId])) {
                $grouped[$planId] = [
                    'plan' => $duration->getPlan(),
                    'durations' => []
                ];
            }
            $grouped[$planId]['durations'][] = $duration;
        }

        return $grouped;
    }

    /**
     * Trouve la durée la plus économique pour un plan
     * 
     * @param Plan $plan
     * @return PlanDuration|null
     */
    public function findBestValue(Plan $plan): ?PlanDuration
    {
        return $this->createQueryBuilder('pd')
            ->andWhere('pd.plan = :plan')
            ->setParameter('plan', $plan)
            ->orderBy('pd.discount', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve les durées avec une réduction
     * 
     * @return PlanDuration[]
     */
    public function findDiscountedDurations(): array
    {
        return $this->createQueryBuilder('pd')
            ->andWhere('pd.discount > 0')
            ->orderBy('pd.discount', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Calcule le prix par mois pour une durée
     * 
     * @param PlanDuration $duration
     * @return float
     */
    public function getMonthlyPrice(PlanDuration $duration): float
    {
        if ($duration->getMonths() === 0) {
            return 0;
        }
        return $duration->getPrice() / $duration->getMonths();
    }

    /**
     * Trouve les durées par nombre de mois spécifique
     * 
     * @param int $months
     * @return PlanDuration[]
     */
    public function findByMonths(int $months): array
    {
        return $this->createQueryBuilder('pd')
            ->andWhere('pd.months = :months')
            ->setParameter('months', $months)
            ->orderBy('pd.price', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les durées actives pour les plans actifs
     * 
     * @return PlanDuration[]
     */
    public function findActiveDurations(): array
    {
        return $this->createQueryBuilder('pd')
            ->leftJoin('pd.plan', 'p')
            ->andWhere('p.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('p.price', 'ASC')
            ->addOrderBy('pd.months', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Vérifie si une durée existe déjà pour un plan et un nombre de mois
     * 
     * @param Plan $plan
     * @param int $months
     * @return bool
     */
    public function existsForPlanAndMonths(Plan $plan, int $months): bool
    {
        $result = $this->createQueryBuilder('pd')
            ->select('COUNT(pd.id)')
            ->andWhere('pd.plan = :plan')
            ->andWhere('pd.months = :months')
            ->setParameter('plan', $plan)
            ->setParameter('months', $months)
            ->getQuery()
            ->getSingleScalarResult();

        return $result > 0;
    }

    /**
     * Sauvegarde une durée
     * 
     * @param PlanDuration $duration
     * @param bool $flush
     */
    public function save(PlanDuration $duration, bool $flush = true): void
    {
        $this->getEntityManager()->persist($duration);
        
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Supprime une durée
     * 
     * @param PlanDuration $duration
     * @param bool $flush
     */
    public function remove(PlanDuration $duration, bool $flush = true): void
    {
        $this->getEntityManager()->remove($duration);
        
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Récupère les statistiques des durées
     * 
     * @return array
     */
    public function getStatistics(): array
    {
        return [
            'total_durations' => $this->count([]),
            'discounted_durations' => $this->count(['discount' => '> 0']),
            'avg_months' => $this->createQueryBuilder('pd')
                ->select('AVG(pd.months)')
                ->getQuery()
                ->getSingleScalarResult(),
            'max_months' => $this->createQueryBuilder('pd')
                ->select('MAX(pd.months)')
                ->getQuery()
                ->getSingleScalarResult(),
            'price_ranges' => [
                'min_price' => $this->createQueryBuilder('pd')
                    ->select('MIN(pd.price)')
                    ->getQuery()
                    ->getSingleScalarResult(),
                'max_price' => $this->createQueryBuilder('pd')
                    ->select('MAX(pd.price)')
                    ->getQuery()
                    ->getSingleScalarResult(),
            ]
        ];
    }

    /**
     * Recherche avancée avec filtres
     * 
     * @param array $filters
     * @return PlanDuration[]
     */
    public function search(array $filters = []): array
    {
        $qb = $this->createQueryBuilder('pd')
            ->leftJoin('pd.plan', 'p')
            ->addSelect('p');

        if (!empty($filters['plan_id'])) {
            $qb->andWhere('p.id = :planId')
               ->setParameter('planId', $filters['plan_id']);
        }

        if (!empty($filters['min_months'])) {
            $qb->andWhere('pd.months >= :minMonths')
               ->setParameter('minMonths', $filters['min_months']);
        }

        if (!empty($filters['max_months'])) {
            $qb->andWhere('pd.months <= :maxMonths')
               ->setParameter('maxMonths', $filters['max_months']);
        }

        if (!empty($filters['max_price'])) {
            $qb->andWhere('pd.price <= :maxPrice')
               ->setParameter('maxPrice', $filters['max_price']);
        }

        if (isset($filters['has_discount'])) {
            if ($filters['has_discount']) {
                $qb->andWhere('pd.discount > 0');
            } else {
                $qb->andWhere('pd.discount = 0');
            }
        }

        return $qb->orderBy('pd.price', 'ASC')
                  ->addOrderBy('pd.months', 'ASC')
                  ->getQuery()
                  ->getResult();
    }
}