<?php

namespace App\Repository;

use App\Entity\Application;
use App\Entity\Job;
use App\Entity\PersonalProfile;
use App\Entity\ProfessionalProfile;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ApplicationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Application::class);
    }

    /**
     * Vérifie si un talent a déjà postulé à une offre
     */
    public function hasApplied(Job $job, ProfessionalProfile $profile): bool
    {
        return (bool) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('a.job = :j')
            ->andWhere('a.professionalProfile = :p')
            ->setParameter('j', $job)
            ->setParameter('p', $profile)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Candidatures reçues pour une offre
     */
    public function findByJob(Job $job): array
    {
        return $this->createQueryBuilder('a')
            ->leftJoin('a.professionalProfile', 'pp')->addSelect('pp')
            ->andWhere('a.job = :j')
            ->setParameter('j', $job)
            ->orderBy('a.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Candidatures d’un talent
     */
    public function findByTalent(User $user): array
    {
        return $this->createQueryBuilder('a')
            ->leftJoin('a.job', 'j')->addSelect('j')
            ->andWhere('a.user = :u')
            ->setParameter('u', $user)
            ->orderBy('a.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte les candidatures reçues par une Company / Particulier
     */
    public function countByCompanyJobs(array $jobs): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('a.job IN (:jobs)')
            ->setParameter('jobs', $jobs)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Stats : candidatures par offre
     */
    public function countByJobStats(array $jobs): array
    {
        return $this->createQueryBuilder('a')
            ->select('j.id as jobId, j.title as title, COUNT(a.id) as total')
            ->leftJoin('a.job', 'j')
            ->andWhere('j IN (:jobs)')
            ->setParameter('jobs', $jobs)
            ->groupBy('j.id, j.title')
            ->orderBy('total', 'DESC')
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * Compte les candidatures reçues pour les jobs d'une entreprise (profil).
     * @param PersonalProfile $company
     * @return int
     */
    public function countReceivedForCompanyProfile(PersonalProfile $company): int
    {
        $user = $company->getUser();

        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->leftJoin('a.job', 'j')
            ->andWhere('j.createdBy = :u')
            ->setParameter('u', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Compte les candidatures par job (top N) pour un profil entreprise.
     * @param PersonalProfile $company
     * @param int $limit
     * @return array
     */
    public function countByJobForCompanyProfile(PersonalProfile $company, int $limit): array
    {
        $user = $company->getUser();

        return $this->createQueryBuilder('a')
            ->select('j.id as jobId, j.title as title, COUNT(a.id) as c')
            ->leftJoin('a.job', 'j')
            ->andWhere('j.createdBy = :u')
            ->setParameter('u', $user)
            ->groupBy('j.id, j.title')
            ->orderBy('c', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * Récupère les dernières candidatures reçues (profil entreprise).
     * @param PersonalProfile $company
     * @param int $limit
     * @return array
     */
    public function findLatestForCompanyProfile(PersonalProfile $company, int $limit): array
    {
        $user = $company->getUser();

        return $this->createQueryBuilder('a')
            ->leftJoin('a.job', 'j')->addSelect('j')
            ->andWhere('j.createdBy = :u')
            ->setParameter('u', $user)
            ->orderBy('a.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }


    public function countApplicantsForPublisher(int $publisherId): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->innerJoin('a.job', 'j')
            ->andWhere('j.createdBy = :pid') // <-- adapte ici
            ->setParameter('pid', $publisherId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countUnviewedApplicantsForPublisher(int $publisherId): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->innerJoin('a.job', 'j')
            ->andWhere('j.createdBy = :pid') // <-- adapte ici
            ->andWhere('a.viewedAt IS NULL')
            ->setParameter('pid', $publisherId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function markViewedForJob(int $jobId): int
    {
        // Marque comme "vu" toutes les candidatures non vues de ce job
        return $this->createQueryBuilder('a')
            ->update()
            ->set('a.viewedAt', ':now')
            ->andWhere('a.job = :jid')
            ->andWhere('a.viewedAt IS NULL')
            ->setParameter('jid', $jobId)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->execute();
    }


    /**
     * Retourne les candidatures d'une offre avec l'utilisateur lié
     */
    public function findByJobWithUser(Job $job): array
    {
        return $this->createQueryBuilder('a')
            ->leftJoin('a.user', 'u')->addSelect('u')
            ->andWhere('a.job = :j')
            ->setParameter('j', $job)
            ->orderBy('a.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * ✅ Récupère une candidature par id + vérifie que la mission appartient au particulier connecté.
     */
    public function findOneForOwnerById(int $applicationId, User $owner): ?Application
    {
        return $this->createQueryBuilder('a')
            ->leftJoin('a.job', 'j')->addSelect('j')
            // ⚠️ ADAPTE ICI si ton champ candidat s'appelle autrement
            ->leftJoin('a.user', 'cand')->addSelect('cand')
            ->andWhere('a.id = :id')
            ->andWhere('j.createdBy = :owner')
            ->setParameter('id', $applicationId)
            ->setParameter('owner', $owner)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }


    public function countApplicationsByCompanyPeriod(
        User $company,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
        ?Job $job = null
    ): int {
        $qb = $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->innerJoin('a.job', 'j')
            ->andWhere('j.createdBy = :u')
            ->andWhere('a.createdAt BETWEEN :from AND :to')
            ->setParameter('u', $company)
            ->setParameter('from', $from)
            ->setParameter('to', $to);

        if ($job) {
            $qb->andWhere('a.job = :job')->setParameter('job', $job);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function countViewedApplicationsByCompanyPeriod(
        User $company,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
        ?Job $job = null
    ): int {
        $qb = $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->innerJoin('a.job', 'j')
            ->andWhere('j.createdBy = :u')
            ->andWhere('a.createdAt BETWEEN :from AND :to')
            ->andWhere('a.viewedAt IS NOT NULL')
            ->setParameter('u', $company)
            ->setParameter('from', $from)
            ->setParameter('to', $to);

        if ($job) {
            $qb->andWhere('a.job = :job')->setParameter('job', $job);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Moyenne en heures (createdAt -> viewedAt).
     * Retourne float (ex: 5.2) ou 0 si pas de données.
     */
    /**
     * Moyenne en heures (createdAt -> viewedAt).
     * Retourne float (ex: 5.2) ou 0 si pas de données.
     */
    public function avgHoursToFirstViewByCompanyPeriod(
        User $company,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
        ?Job $job = null
    ): float {
        $conn = $this->getEntityManager()->getConnection();
        $platform = $conn->getDatabasePlatform()->getName();

        // ⚠️ Noms de colonnes supposés avec la naming strategy Symfony/Doctrine classique (underscore)
        // Si chez toi c'est différent, adapte: application.created_at/viewed_at/job_id ; job.created_by_id
        $whereJob = '';
        $params = [
            'companyId' => $company->getId(),
            'from'      => $from->format('Y-m-d H:i:s'),
            'to'        => $to->format('Y-m-d H:i:s'),
        ];

        if ($job) {
            $whereJob = ' AND a.job_id = :jobId ';
            $params['jobId'] = $job->getId();
        }

        if ($platform === 'postgresql') {
            $sql = "
                SELECT AVG(EXTRACT(EPOCH FROM (a.viewed_at - a.created_at)) / 3600.0) AS avgH
                FROM application a
                INNER JOIN job j ON j.id = a.job_id
                WHERE j.created_by_id = :companyId
                  AND a.created_at BETWEEN :from AND :to
                  AND a.viewed_at IS NOT NULL
                  $whereJob
            ";
        } else {
            // MySQL / MariaDB
            $sql = "
                SELECT AVG(TIMESTAMPDIFF(HOUR, a.created_at, a.viewed_at)) AS avgH
                FROM application a
                INNER JOIN job j ON j.id = a.job_id
                WHERE j.created_by_id = :companyId
                  AND a.created_at BETWEEN :from AND :to
                  AND a.viewed_at IS NOT NULL
                  $whereJob
            ";
        }

        $val = $conn->fetchOne($sql, $params);
        return $val !== null ? round((float) $val, 1) : 0.0;
    }

    public function dailyApplicationsByCompanyPeriod(
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
            $whereJob = ' AND a.job_id = :jobId ';
            $params['jobId'] = $job->getId();
        }

        $sql = "
            SELECT DATE(a.created_at) AS d, COUNT(a.id) AS c
            FROM application a
            INNER JOIN job j ON j.id = a.job_id
            WHERE j.created_by_id = :companyId
            AND a.created_at BETWEEN :from AND :to
            $whereJob
            GROUP BY DATE(a.created_at)
            ORDER BY d ASC
        ";

        $rows = $conn->fetchAllAssociative($sql, $params);

        $out = [];
        foreach ($rows as $r) {
            $out[(string) $r['d']] = (int) $r['c'];
        }
        return $out;
    }

    /**
     * Breakdown statuts (NEW/REVIEWED/SHORTLIST/REJECTED/ACCEPTED) sur la période.
     */
    public function statusBreakdownByCompanyPeriod(
        User $company,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
        ?Job $job = null
    ): array {
        $qb = $this->createQueryBuilder('a')
            ->select('a.status AS s, COUNT(a.id) AS c')
            ->innerJoin('a.job', 'j')
            ->andWhere('j.createdBy = :u')
            ->andWhere('a.createdAt BETWEEN :from AND :to')
            ->setParameter('u', $company)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->groupBy('s');

        if ($job) {
            $qb->andWhere('a.job = :job')->setParameter('job', $job);
        }

        $rows = $qb->getQuery()->getArrayResult();
        $out = [
            Application::STATUS_NEW => 0,
            Application::STATUS_REVIEWED => 0,
            Application::STATUS_SHORTLIST => 0,
            Application::STATUS_REJECTED => 0,
            Application::STATUS_ACCEPTED => 0,
        ];
        foreach ($rows as $r) {
            $out[(string) $r['s']] = (int) $r['c'];
        }
        return $out;
    }


    /**
     * Retourne les candidatures groupées par status pour le pipeline.
     * - Filtre par entreprise (job.createdBy)
     * - Filtre optionnel par job / période / recherche texte
     */
    public function findPipelineByCompany(
        User $company,
        ?Job $job = null,
        ?\DateTimeImmutable $from = null,
        ?\DateTimeImmutable $to = null,
        ?string $q = null,
        int $limitPerColumn = 60
    ): array {
        $qb = $this->createQueryBuilder('a')
            ->addSelect('j','u','pp')
            ->innerJoin('a.job', 'j')
            ->innerJoin('a.user', 'u')
            ->leftJoin('u.personalProfile', 'pp')
            ->andWhere('j.createdBy = :company')
            ->setParameter('company', $company)
            ->orderBy('a.createdAt', 'DESC');

        if ($job) {
            $qb->andWhere('a.job = :job')->setParameter('job', $job);
        }

        if ($from && $to) {
            $qb->andWhere('a.createdAt BETWEEN :from AND :to')
               ->setParameter('from', $from)
               ->setParameter('to', $to);
        }

        if ($q) {
            $qLike = '%' . mb_strtolower(trim($q)) . '%';
            $qb->andWhere("
                LOWER(u.email) LIKE :q
                OR (pp.fullName IS NOT NULL AND LOWER(pp.fullName) LIKE :q)
            ")->setParameter('q', $qLike);
        }

        /** @var Application[] $apps */
        $apps = $qb->getQuery()->getResult();

        $columns = [
            Application::STATUS_NEW => [],
            Application::STATUS_REVIEWED => [],
            Application::STATUS_SHORTLIST => [],
            Application::STATUS_ACCEPTED => [],
            Application::STATUS_REJECTED => [],
        ];

        foreach ($apps as $a) {
            $s = $a->getStatus();
            if (!isset($columns[$s])) continue;
            if (\count($columns[$s]) >= $limitPerColumn) continue;
            $columns[$s][] = $a;
        }

        return $columns;
    }

    /**
     * Compte les candidatures par status pour les KPIs.
     */
    public function countByStatusForCompany(
        User $company,
        ?Job $job = null,
        ?\DateTimeImmutable $from = null,
        ?\DateTimeImmutable $to = null
    ): array {
        $qb = $this->createQueryBuilder('a')
            ->select('a.status AS s, COUNT(a.id) AS c')
            ->innerJoin('a.job', 'j')
            ->andWhere('j.createdBy = :company')
            ->setParameter('company', $company)
            ->groupBy('s');

        if ($job) {
            $qb->andWhere('a.job = :job')->setParameter('job', $job);
        }
        if ($from && $to) {
            $qb->andWhere('a.createdAt BETWEEN :from AND :to')
               ->setParameter('from', $from)
               ->setParameter('to', $to);
        }

        $rows = $qb->getQuery()->getArrayResult();

        $out = [
            Application::STATUS_NEW => 0,
            Application::STATUS_REVIEWED => 0,
            Application::STATUS_SHORTLIST => 0,
            Application::STATUS_ACCEPTED => 0,
            Application::STATUS_REJECTED => 0,
        ];

        foreach ($rows as $r) {
            $out[(string)$r['s']] = (int)$r['c'];
        }

        $out['_total'] = array_sum($out);

        return $out;
    }

    public function countAcceptedApplicationsForActiveJobs(): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->innerJoin('a.job', 'j')
            ->andWhere('a.status = :status')
            ->andWhere('j.dateExpirationAt >= :now')
            ->setParameter('status', Application::STATUS_ACCEPTED)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getSingleScalarResult();
}

}
