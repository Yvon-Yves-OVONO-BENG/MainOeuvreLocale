<?php

namespace App\Repository;

use App\Entity\ContactLog;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ContactLog>
 */
class ContactLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ContactLog::class);
    }

    /**
     * Compte le nombre de contacts utilisés par un utilisateur pour le mois en cours
     */
    public function countMonthlyUsage(User $user): int
    {
        $startOfMonth = new \DateTime('first day of this month 00:00:00');
        
        return $this->createQueryBuilder('cl')
            ->select('COUNT(cl.id)')
            ->where('cl.user = :user')
            ->andWhere('cl.createdAt >= :startOfMonth')
            ->setParameter('user', $user)
            ->setParameter('startOfMonth', $startOfMonth)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Compte le nombre de contacts utilisés par un utilisateur pour le mois en cours
     * avec possibilité de filtrer par type (email, phone)
     */
    public function countMonthlyUsageByType(User $user, string $type): int
    {
        $startOfMonth = new \DateTime('first day of this month 00:00:00');
        
        return $this->createQueryBuilder('cl')
            ->select('COUNT(cl.id)')
            ->where('cl.user = :user')
            ->andWhere('cl.type = :type')
            ->andWhere('cl.createdAt >= :startOfMonth')
            ->setParameter('user', $user)
            ->setParameter('type', $type)
            ->setParameter('startOfMonth', $startOfMonth)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Vérifie si un utilisateur a déjà contacté un autre utilisateur ce mois-ci
     */
    // Dans ContactLogRepository
    public function hasContactedThisMonth(User $user, User $targetUser, ?string $type = null): bool
    {
        $startOfMonth = new \DateTime('first day of this month 00:00:00');
        
        $qb = $this->createQueryBuilder('cl')
            ->select('COUNT(cl.id)')
            ->where('cl.user = :user')
            ->andWhere('cl.targetUser = :targetUser')
            ->andWhere('cl.createdAt >= :startOfMonth')
            ->setParameter('user', $user)
            ->setParameter('targetUser', $targetUser)
            ->setParameter('startOfMonth', $startOfMonth);
        
        if ($type) {
            $qb->andWhere('cl.type = :type')
               ->setParameter('type', $type);
        }
        
        $count = $qb->getQuery()->getSingleScalarResult();
        
        // Log pour débogage
        error_log('📊 hasContactedThisMonth: ' . $count . ' contacts trouvés');
        
        return $count > 0;
    }

    /** Un contact débloqué avec un ticket reste accessible sans consommer un second crédit. */
    public function hasTicketAccess(User $user, User $targetUser): bool
    {
        return (int) $this->createQueryBuilder('cl')
            ->select('COUNT(cl.id)')
            ->andWhere('cl.user = :user')
            ->andWhere('cl.targetUser = :target')
            ->andWhere('cl.ticket IS NOT NULL')
            ->setParameter('user', $user)
            ->setParameter('target', $targetUser)
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }

    /**
     * Récupère tous les contacts d'un utilisateur pour le mois en cours
     */
    public function findMonthlyContacts(User $user): array
    {
        $startOfMonth = new \DateTime('first day of this month 00:00:00');
        
        return $this->createQueryBuilder('cl')
            ->where('cl.user = :user')
            ->andWhere('cl.createdAt >= :startOfMonth')
            ->setParameter('user', $user)
            ->setParameter('startOfMonth', $startOfMonth)
            ->orderBy('cl.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les contacts d'un utilisateur pour une période donnée
     */
    public function findContactsBetweenDates(User $user, \DateTime $start, \DateTime $end): array
    {
        return $this->createQueryBuilder('cl')
            ->where('cl.user = :user')
            ->andWhere('cl.createdAt >= :start')
            ->andWhere('cl.createdAt <= :end')
            ->setParameter('user', $user)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('cl.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les contacts par type (email ou phone)
     */
    public function findByType(User $user, string $type): array
    {
        return $this->createQueryBuilder('cl')
            ->where('cl.user = :user')
            ->andWhere('cl.type = :type')
            ->setParameter('user', $user)
            ->setParameter('type', $type)
            ->orderBy('cl.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les contacts triés par date
     */
    public function findRecentContacts(User $user, int $limit = 10): array
    {
        return $this->createQueryBuilder('cl')
            ->where('cl.user = :user')
            ->setParameter('user', $user)
            ->orderBy('cl.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les contacts d'un utilisateur vers un autre utilisateur
     */
    public function findBetweenUsers(User $user, User $targetUser): array
    {
        return $this->createQueryBuilder('cl')
            ->where('cl.user = :user')
            ->andWhere('cl.targetUser = :targetUser')
            ->setParameter('user', $user)
            ->setParameter('targetUser', $targetUser)
            ->orderBy('cl.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Supprime les contacts d'un utilisateur pour une période donnée
     * (utilisé pour le nettoyage ou la maintenance)
     */
    public function deleteOldContacts(\DateTime $before): int
    {
        return $this->createQueryBuilder('cl')
            ->delete()
            ->where('cl.createdAt < :before')
            ->setParameter('before', $before)
            ->getQuery()
            ->execute();
    }

    /**
     * Récupère les statistiques de contacts par mois pour un utilisateur
     */
    public function getMonthlyStats(User $user, int $months = 6): array
    {
        $stats = [];
        $now = new \DateTime();
        
        for ($i = 0; $i < $months; $i++) {
            $month = (clone $now)->modify("-$i months");
            $start = (clone $month)->modify('first day of this month 00:00:00');
            $end = (clone $month)->modify('last day of this month 23:59:59');
            
            $count = $this->createQueryBuilder('cl')
                ->select('COUNT(cl.id)')
                ->where('cl.user = :user')
                ->andWhere('cl.createdAt >= :start')
                ->andWhere('cl.createdAt <= :end')
                ->setParameter('user', $user)
                ->setParameter('start', $start)
                ->setParameter('end', $end)
                ->getQuery()
                ->getSingleScalarResult();
            
            $stats[$month->format('Y-m')] = $count;
        }
        
        return $stats;
    }

    /**
     * Récupère les utilisateurs les plus contactés par un utilisateur
     */
    public function getTopTargets(User $user, int $limit = 5): array
    {
        return $this->createQueryBuilder('cl')
            ->select('cl.targetUser, COUNT(cl.id) as contactCount')
            ->where('cl.user = :user')
            ->setParameter('user', $user)
            ->groupBy('cl.targetUser')
            ->orderBy('contactCount', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Vérifie si l'utilisateur a atteint sa limite mensuelle
     */
    public function hasReachedMonthlyLimit(User $user, int $maxContacts): bool
    {
        $used = $this->countMonthlyUsage($user);
        return $used >= $maxContacts;
    }

    /**
     * Récupère le nombre de jours depuis le dernier contact
     */
    public function getDaysSinceLastContact(User $user): ?int
    {
        $last = $this->createQueryBuilder('cl')
            ->where('cl.user = :user')
            ->setParameter('user', $user)
            ->orderBy('cl.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
        
        if (!$last) {
            return null;
        }
        
        $now = new \DateTime();
        $diff = $now->diff($last->getCreatedAt());
        return (int) $diff->days;
    }

    /**
     * Récupère tous les contacts d'un utilisateur avec pagination
     */
    public function findPaginated(User $user, int $page = 1, int $limit = 20): array
    {
        $offset = ($page - 1) * $limit;
        
        return $this->createQueryBuilder('cl')
            ->where('cl.user = :user')
            ->setParameter('user', $user)
            ->orderBy('cl.createdAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte le nombre total de contacts d'un utilisateur
     */
    public function countTotal(User $user): int
    {
        return $this->createQueryBuilder('cl')
            ->select('COUNT(cl.id)')
            ->where('cl.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }
    
    private function countUsedContacts(User $user): int
    {
        $startOfMonth = new \DateTime('first day of this month 00:00:00');
        
        return $this->contactLogRepository->createQueryBuilder('cl')
            ->select('COUNT(cl.id)')
            ->where('cl.user = :user')
            ->andWhere('cl.createdAt >= :startOfMonth')
            ->setParameter('user', $user)
            ->setParameter('startOfMonth', $startOfMonth)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
