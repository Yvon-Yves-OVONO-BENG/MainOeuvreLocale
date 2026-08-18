<?php

namespace App\Repository;

use App\Entity\Rating;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry, protected EntityManagerInterface $em)
    {
        parent::__construct($registry, User::class);
    }

    public function findOneByEmailAddress(string $email): ?User
    {
        $normalizedEmail = mb_strtolower(trim($email));

        if ($normalizedEmail === '') {
            return null;
        }

        return $this->createQueryBuilder('u')
            ->andWhere('LOWER(u.email) = :email')
            ->setParameter('email', $normalizedEmail)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findOneByResetPasswordToken(string $token): ?User
    {
        return $this->findOneBy([
            'resetPasswordToken' => $token,
        ]);
    }
    
    public function qbAdminSearch(array $f): QueryBuilder
    {
        $qb = $this->createQueryBuilder('u');

        // si tu as PersonalProfile relation :
        // $qb->leftJoin('u.personalProfile', 'pp')->addSelect('pp');

        if (!empty($f['q'])) {
            $qb->andWhere('u.email LIKE :q')
               ->setParameter('q', '%'.$f['q'].'%');
        }

        if (!empty($f['role'])) {
            // roles est souvent JSON -> LIKE marche en MySQL, OK pour une base simple
            $qb->andWhere('u.roles LIKE :role')->setParameter('role', '%"'.$f['role'].'"%');
        }

        if (!empty($f['status'])) {
            if ($f['status'] === 'active') $qb->andWhere('u.isActive = 1');
            if ($f['status'] === 'blocked') $qb->andWhere('u.isActive = 0');
        }

        $sort = in_array($f['sort'] ?? 'createdAt', ['createdAt','email'], true) ? $f['sort'] : 'createdAt';
        $dir  = (($f['dir'] ?? 'DESC') === 'ASC') ? 'ASC' : 'DESC';

        return $qb->orderBy('u.'.$sort, $dir);
    }

    public function searchForPicker(string $q, int $limit = 10): array
    {
        $q = trim($q);

        if ($q === '') {
            return [];
        }

        return $this->createQueryBuilder('u')
            ->where('u.email LIKE :q')
            ->setParameter('q', '%'.$q.'%')
            ->orderBy('u.email', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
    
    public function countSignupsSince(\DateTimeInterface $since): int
    {
        $dql = <<<DQL
            SELECT COUNT(u.id)
            FROM App\Entity\User u
            WHERE u.createdAt >= :since
        DQL;

        return (int) $this->getEntityManager()
            ->createQuery($dql)
            ->setParameter('since', $since)
            ->getSingleScalarResult();
    }

    public function searchForModeration(string $q, int $limit = 30): array
    {
        $qb = $this->createQueryBuilder('u');

        $or = $qb->expr()->orX(
            $qb->expr()->like('u.email', ':q'),
            $qb->expr()->like('u.phone', ':q')
        );

        if (ctype_digit($q)) {
            $or->add($qb->expr()->eq('u.id', ':id'));
            $qb->setParameter('id', (int) $q);
        }

        $qb->andWhere($or)
            ->setParameter('q', '%'.$q.'%')
            ->setMaxResults($limit)
            ->orderBy('u.id', 'DESC');

        return $qb->getQuery()->getResult();
    }

    public function exportRows(int $limit = 5000): iterable
    {
        $rows = $this->createQueryBuilder('u')
            ->select('u.id, u.email, u.phone, u.roles, u.createdAt')
            ->orderBy('u.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();

        foreach ($rows as $r) {
            yield [
                $r['id'] ?? '',
                $r['email'] ?? '',
                $r['phone'] ?? '',
                is_array($r['roles'] ?? null) ? implode(',', $r['roles']) : (string)($r['roles'] ?? ''),
                isset($r['createdAt']) && $r['createdAt'] ? $r['createdAt']->format('Y-m-d H:i:s') : '',
            ];
        }
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }


    /**
     * Compte les comptes actifs / bloqués selon le booléen User.isActive.
     * Utilisé pour les KPI Admin.
     */
    public function countByActive(bool $active): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u.isActive = :a')
            ->setParameter('a', $active)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Retourne les derniers utilisateurs créés (ordre DESC sur createdAt).
     * Utilisé pour la table "Utilisateurs récents".
     *
     * @return User[]
     */
    public function findLatestUsers(int $limit = 5): array
    {
        $qb = $this->createQueryBuilder('u');

        return $qb
            ->andWhere($qb->expr()->notLike('u.roles', ':roleAdmin'))
            ->andWhere($qb->expr()->notLike('u.roles', ':roleSuperAdmin'))
            ->setParameter('roleAdmin', '%ROLE_ADMIN%')
            ->setParameter('roleSuperAdmin', '%ROLE_SUPER_ADMIN%')
            ->orderBy('u.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte les inscriptions par jour sur les N derniers jours (SQL natif).
     *
     * Pourquoi SQL natif ?
     * - Certaines installations Doctrine ne reconnaissent pas YEAR(), DATE(), etc.
     * - Cette version fonctionne toujours (MySQL / MariaDB)
     * - Parfait pour les dashboards / statistiques
     *
     * @return array<int, array{d: string, c: int}>
     */
    public function countSignupsByDayLastDays(int $days = 30): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $dt = (new \DateTimeImmutable("-{$days} days"))
            ->format('Y-m-d H:i:s');

        $sql = "
            SELECT DATE(u.created_at) AS d, COUNT(u.id) AS c
            FROM user u
            WHERE u.created_at >= :dt
            GROUP BY DATE(u.created_at)
            ORDER BY d ASC
        ";

        $rows = $conn->executeQuery($sql, ['dt' => $dt])
            ->fetchAllAssociative();

        return array_map(static fn(array $r) => [
            'd' => (string) $r['d'],
            'c' => (int) $r['c'],
        ], $rows);
    }


    /**
     * Compte les "talents" en se basant sur l’existence d’un professionalProfile.
     * (Plus fiable que roles si ton business dit "talent = a un profil pro")
     */
    public function countTalentsWithProfessionalProfile(): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->leftJoin('u.professionalProfile', 'pp')
            ->andWhere('pp.id IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Récupère les derniers utilisateurs inscrits.
     *
     * @param int $limit Nombre max de résultats (ex: 10)
     * @return User[]
     */
    public function findLatest(int $limit = 10): array
    {
        return $this->createQueryBuilder('u')
            ->orderBy('u.createdAt', 'DESC')
            ->setMaxResults(max(1, $limit))
            ->getQuery()
            ->getResult();
    }

    /**
     * Inscriptions par mois.
     * Retourne un tableau prêt pour chart:
     * [
     *   ['ym' => '2026-01', 'c' => 123],
     *   ['ym' => '2026-02', 'c' => 98],
     * ]
     *
     * NOTE: DATE_FORMAT est spécifique MySQL.
     * Si tu veux être DB-agnostic, on peut faire une version avec GROUP BY YEAR/MONTH
     * et formatter ensuite en PHP.
     */
    /**
     * ✅ Inscriptions par mois (N derniers mois) — sans fonctions DQL
     * On utilise DBAL (SQL natif) pour éviter DATE_FORMAT/YEAR/MONTH en DQL.
     *
     * @return array<int, array{ym:string, c:int}>
     */
    public function countSignupsByMonth(int $months = 12): array
    {
        $months = max(1, $months);

        $conn = $this->getEntityManager()->getConnection();
        $platform = $conn->getDatabasePlatform()->getName();

        // Table users (Doctrine donne le nom réel)
        $table = $this->getClassMetadata()->getTableName();

        // Date de début
        $start = (new \DateTimeImmutable('first day of this month 00:00:00'))
            ->modify(sprintf('-%d months', $months - 1));

        /**
         * On adapte la fonction de groupement selon le SGBD :
         * - MySQL/MariaDB : DATE_FORMAT(created_at, '%Y-%m')
         * - PostgreSQL    : TO_CHAR(created_at, 'YYYY-MM')
         * - SQLite        : strftime('%Y-%m', created_at)
         */
        if (in_array($platform, ['mysql', 'mariadb'], true)) {
            $ymExpr = "DATE_FORMAT(u.created_at, '%Y-%m')";
        } elseif ($platform === 'postgresql') {
            $ymExpr = "TO_CHAR(u.created_at, 'YYYY-MM')";
        } else { // sqlite (ou fallback)
            $ymExpr = "strftime('%Y-%m', u.created_at)";
        }

        $sql = "
            SELECT {$ymExpr} AS ym, COUNT(u.id) AS c
            FROM {$table} u
            WHERE u.created_at IS NOT NULL
              AND u.created_at >= :start
            GROUP BY ym
            ORDER BY ym ASC
        ";

        $rows = $conn->executeQuery($sql, [
            'start' => $start->format('Y-m-d H:i:s'),
        ])->fetchAllAssociative();

        // Normalise types
        return array_map(static fn(array $r) => [
            'ym' => (string) $r['ym'],
            'c'  => (int) $r['c'],
        ], $rows);
    }


    /**
     * Retourne (id, roles) pour calculer une répartition côté PHP.
     * Utile quand roles est un JSON/array et qu’on ne veut pas faire de SQL JSON.
     *
     * @return array<int, array{id:int, roles:array}>
     */
    public function findAllUserRoles(): array
    {
        return $this->createQueryBuilder('u')
            ->select('u.id, u.roles')
            ->getQuery()
            ->getArrayResult();
    }


    /**
     * ✅ Compte TOUS les utilisateurs de la plateforme
     */
    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }


    /**
     * Retourne : [ [0 => User, 'avgStars' => '4.2', 'reviewsCount' => '12'], ... ]
     */
    public function findByRoleWithRatingStats(string $role, int $limit = 100): array
    {
        $qb = $this->createQueryBuilder('u')
            ->addSelect('COALESCE(AVG(rt.stars), 0) AS avgStars')
            ->addSelect('COUNT(rt.id) AS ratingsCount')
            ->innerJoin('u.personalProfile', 'pp')
            ->leftJoin(Rating::class, 'rt', 'WITH', 'rt.user = u') // ✅ Rating ici
            ->andWhere('u.roles LIKE :role')
            ->setParameter('role', '%"' . $role . '"%')
            ->andWhere('u.isActive = true')
            ->groupBy('u.id')
            ->orderBy('avgStars', 'DESC')
            ->setMaxResults($limit);

        $this->applyPublicProfileVisibility($qb, $role);

        return $qb
            ->getQuery()
            ->getResult();
    }

    public function findParticuliersWithRatingStats(int $limit = 50): array
    {
        return $this->findByRoleWithRatingStats('ROLE_PARTICULIER', $limit);
    }

    public function findCompaniesWithRatingStats(int $limit = 50): array
    {
        return $this->findByRoleWithRatingStats('ROLE_COMPANY', $limit);
    }


    private function qbRoleWithStats(string $role, string $q = ''): QueryBuilder
    {
        $qb = $this->createQueryBuilder('u')
            ->innerJoin('u.personalProfile', 'pp')
            ->addSelect('COALESCE(AVG(rt.stars), 0) AS avgStars')
            ->addSelect('COUNT(rt.id) AS ratingsCount')
            ->leftJoin(Rating::class, 'rt', 'WITH', 'rt.user = u')
            ->andWhere('u.roles LIKE :role')
            ->setParameter('role', '%"' . $role . '"%')
            ->andWhere('u.isActive = true');

        $this->applyPublicProfileVisibility($qb, $role);

        $q = trim($q);
        if ($q !== '') {
            // Recherche publique : jamais sur l'adresse e-mail.
            $searchExpression = $role === 'ROLE_COMPANY'
                ? '(LOWER(pp.companyTradeName) LIKE :q OR LOWER(pp.companyLegalName) LIKE :q OR LOWER(pp.fullName) LIKE :q)'
                : 'LOWER(pp.fullName) LIKE :q';

            $qb->andWhere($searchExpression)
                ->setParameter('q', '%' . mb_strtolower($q) . '%');
        }

        return $qb
            ->groupBy('u.id')
            ->orderBy('avgStars', 'DESC')
            ->addOrderBy('ratingsCount', 'DESC')
            ->addOrderBy('u.id', 'DESC');
    }

    /** rows paginés */
    public function searchByRoleWithRatingStats(string $role, string $q, int $limit, int $offset): array
    {
        return $this->qbRoleWithStats($role, $q)
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /** total pour pagination */
    public function countByRoleAndQuery(string $role, ?User $excludeMe, string $q = ''): int
    {
        $qb = $this->createQueryBuilder('u')
            ->select('COUNT(DISTINCT u.id)')
            // ✅ doit avoir un personalProfile
            ->innerJoin('u.personalProfile', 'pp')
            // ✅ filtre rôle
            ->andWhere('u.roles LIKE :role')
            ->setParameter('role', '%"' . $role . '"%')
            ->andWhere('u.isActive = true');

        $this->applyPublicProfileVisibility($qb, $role);

        // ✅ ne pas me compter
        if ($excludeMe) {
            $qb->andWhere('u != :me')
            ->setParameter('me', $excludeMe);
        }

        // ✅ recherche
        $q = trim($q);
        if ($q !== '') {
            $searchExpression = $role === 'ROLE_COMPANY'
                ? '(LOWER(pp.companyTradeName) LIKE :q OR LOWER(pp.companyLegalName) LIKE :q OR LOWER(pp.fullName) LIKE :q)'
                : 'LOWER(pp.fullName) LIKE :q';

            $qb->andWhere($searchExpression)
                ->setParameter('q', '%' . mb_strtolower($q) . '%');
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }


    /**
     * ✅ Liste paginée des utilisateurs à vérifier (CNI + CV)
     * - charge User + PersonalProfile + ProfessionalProfile
     * - filtre par role (talent|particulier|company)
     * - filtre par state (pending|verified|rejected|all)
     * - recherche q sur email/phone/fullName/city/adress
     */
    public function findForVerificationList(string $q, string $role, string $state, int $limit, int $offset): array
    {
        $qb = $this->createQueryBuilder('u')
            ->addSelect('pp, pro')
            ->leftJoin('u.personalProfile', 'pp')
            ->leftJoin('u.professionalProfile', 'pro')
            ->orderBy('u.createdAt', 'DESC');

        // ✅ rôles ciblés
        $qb->andWhere('(u.roles LIKE :talent OR u.roles LIKE :part OR u.roles LIKE :comp)')
            ->setParameter('talent', '%"ROLE_TALENT"%')
            ->setParameter('part',   '%"ROLE_PARTICULIER"%')
            ->setParameter('comp',   '%"ROLE_COMPANY"%');

        // ✅ filtre role
        if ($role === 'talent') {
            $qb->andWhere('u.roles LIKE :rt')->setParameter('rt', '%"ROLE_TALENT"%');
        } elseif ($role === 'particulier') {
            $qb->andWhere('u.roles LIKE :rp')->setParameter('rp', '%"ROLE_PARTICULIER"%');
        } elseif ($role === 'company') {
            $qb->andWhere('u.roles LIKE :rc')->setParameter('rc', '%"ROLE_COMPANY"%');
        }

        // ✅ recherche (email, phone, nom, ville, adresse)
        if ($q !== '') {
            $qb->andWhere('u.email LIKE :q OR u.phone LIKE :q OR pp.fullName LIKE :q OR pp.city LIKE :q OR pp.adress LIKE :q')
               ->setParameter('q', '%'.$q.'%');
        }

        // ✅ state (pending/verified/rejected)
        // - CNI: pending|verified|rejected via pp.cniStatus
        // - CV (talent): pending = verifiedAt NULL & isVerified=false ; verified = isVerified=true ; rejected = verifiedAt NOT NULL & isVerified=false
        if ($state && $state !== 'all') {
            if ($state === 'pending') {
                $qb->andWhere('(pp.cniStatus = :cniPending OR (u.roles LIKE :tal AND (pro.id IS NULL OR (pro.isVerified = false AND pro.verifiedAt IS NULL))))')
                   ->setParameter('cniPending', 'pending')
                   ->setParameter('tal', '%"ROLE_TALENT"%');
            } elseif ($state === 'verified') {
                $qb->andWhere('(pp.cniStatus = :cniVerified OR (u.roles LIKE :tal2 AND pro.isVerified = true))')
                   ->setParameter('cniVerified', 'verified')
                   ->setParameter('tal2', '%"ROLE_TALENT"%');
            } elseif ($state === 'rejected') {
                $qb->andWhere('(pp.cniStatus = :cniRejected OR (u.roles LIKE :tal3 AND pro.isVerified = false AND pro.verifiedAt IS NOT NULL))')
                   ->setParameter('cniRejected', 'rejected')
                   ->setParameter('tal3', '%"ROLE_TALENT"%');
            }
        }

        return $qb->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * ✅ Total des utilisateurs à vérifier (pour pagination)
     * Même logique que findForVerificationList(), mais retourne COUNT(DISTINCT u.id)
     */
    public function countForVerificationList(string $q, string $role, string $state): int
    {
        $qb = $this->createQueryBuilder('u')
            ->select('COUNT(DISTINCT u.id)')
            ->leftJoin('u.personalProfile', 'pp')
            ->leftJoin('u.professionalProfile', 'pro');

        // ✅ rôles ciblés
        $qb->andWhere('(u.roles LIKE :talent OR u.roles LIKE :part OR u.roles LIKE :comp)')
            ->setParameter('talent', '%"ROLE_TALENT"%')
            ->setParameter('part',   '%"ROLE_PARTICULIER"%')
            ->setParameter('comp',   '%"ROLE_COMPANY"%');

        // ✅ filtre role
        if ($role === 'talent') {
            $qb->andWhere('u.roles LIKE :rt')->setParameter('rt', '%"ROLE_TALENT"%');
        } elseif ($role === 'particulier') {
            $qb->andWhere('u.roles LIKE :rp')->setParameter('rp', '%"ROLE_PARTICULIER"%');
        } elseif ($role === 'company') {
            $qb->andWhere('u.roles LIKE :rc')->setParameter('rc', '%"ROLE_COMPANY"%');
        }

        // ✅ recherche
        if ($q !== '') {
            $qb->andWhere('u.email LIKE :q OR u.phone LIKE :q OR pp.fullName LIKE :q OR pp.city LIKE :q OR pp.adress LIKE :q')
               ->setParameter('q', '%'.$q.'%');
        }

        // ✅ state
        if ($state && $state !== 'all') {
            if ($state === 'pending') {
                $qb->andWhere('(pp.cniStatus = :cniPending OR (u.roles LIKE :tal AND (pro.id IS NULL OR (pro.isVerified = false AND pro.verifiedAt IS NULL))))')
                   ->setParameter('cniPending', 'pending')
                   ->setParameter('tal', '%"ROLE_TALENT"%');
            } elseif ($state === 'verified') {
                $qb->andWhere('(pp.cniStatus = :cniVerified OR (u.roles LIKE :tal2 AND pro.isVerified = true))')
                   ->setParameter('cniVerified', 'verified')
                   ->setParameter('tal2', '%"ROLE_TALENT"%');
            } elseif ($state === 'rejected') {
                $qb->andWhere('(pp.cniStatus = :cniRejected OR (u.roles LIKE :tal3 AND pro.isVerified = false AND pro.verifiedAt IS NOT NULL))')
                   ->setParameter('cniRejected', 'rejected')
                   ->setParameter('tal3', '%"ROLE_TALENT"%');
            }
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }


    public function analyticsNewUsersDaily(int $days): array
    {
        $from = (new \DateTimeImmutable())->modify("-{$days} days")->setTime(0, 0, 0);

        $rows = $this->createQueryBuilder('u')
            ->select("SUBSTRING(u.createdAt, 1, 10) as d, COUNT(u.id) as c")
            ->andWhere('u.createdAt >= :from')->setParameter('from', $from)
            ->groupBy('d')
            ->orderBy('d', 'ASC')
            ->getQuery()->getArrayResult();

        return $this->fillDailySeries($rows, $from, $days);
    }

    public function analyticsRoleCounts(int $days): array
    {
        $from = (new \DateTimeImmutable())->modify("-{$days} days")->setTime(0,0,0);

        $row = $this->createQueryBuilder('u')
            ->select("
            SUM(CASE WHEN u.roles LIKE :tal THEN 1 ELSE 0 END) as talent,
            SUM(CASE WHEN u.roles LIKE :part THEN 1 ELSE 0 END) as particulier,
            SUM(CASE WHEN u.roles LIKE :comp THEN 1 ELSE 0 END) as company
            ")
            ->andWhere('u.createdAt >= :from')->setParameter('from', $from)
            ->setParameter('tal', '%\"ROLE_TALENT\"%')
            ->setParameter('part', '%\"ROLE_PARTICULIER\"%')
            ->setParameter('comp', '%\"ROLE_COMPANY\"%')
            ->getQuery()->getOneOrNullResult();

        return [
            'ROLE_TALENT' => (int)($row['talent'] ?? 0),
            'ROLE_PARTICULIER' => (int)($row['particulier'] ?? 0),
            'ROLE_COMPANY' => (int)($row['company'] ?? 0),
        ];
    }

    /**
     * ✅ Utilitaire: remplir les jours manquants pour un line/area chart
     * $rows = [ ['d' => '2026-03-01', 'c' => 12], ... ]
     */
    private function fillDailySeries(array $rows, \DateTimeImmutable $from, int $days): array
    {
        $map = [];
        foreach ($rows as $r) {
            $map[(string)$r['d']] = (int)$r['c'];
        }

        $labels = [];
        $values = [];

        for ($i = 0; $i <= $days; $i++) {
            $d = $from->modify("+{$i} days")->format('Y-m-d');
            $labels[] = $d;
            $values[] = $map[$d] ?? 0;
        }

        return ['labels' => $labels, 'values' => $values];
    }



    public function findAutoWatchlistCandidates(
        string $q,
        string $role,
        int $days,
        int $tr,
        int $tj,
        int $limit,
        int $offset
    ): array {
        $from = (new \DateTimeImmutable())->modify("-{$days} days")->setTime(0, 0, 0);

        // ==========================
        // (1) ITEMS query (avec stats)
        // ==========================
        $qb = $this->createQueryBuilder('u')
            ->leftJoin('u.personalProfile', 'pp')->addSelect('pp')
            ->leftJoin('u.professionalProfile', 'pro')->addSelect('pro')

            ->leftJoin(\App\Entity\Report::class, 'r', 'WITH',
                'r.targetUser = u AND r.status = :open AND r.createdAt >= :from'
            )
            ->leftJoin('u.jobs', 'j')
            ->leftJoin(\App\Entity\ReportJob::class, 'rj', 'WITH',
                'rj.job = j AND rj.createdAt >= :from AND rj.status IN (:jobStatuses)'
            )

            ->setParameter('open', 'open')
            ->setParameter('jobStatuses', ['pending', 'reviewed'])
            ->setParameter('from', $from)

            ->select('u, pp, pro,
                COUNT(DISTINCT r.id) AS openReports,
                COUNT(DISTINCT rj.id) AS jobReports
            ')
            ->groupBy('u.id, pp.id, pro.id')
            ->having('COUNT(DISTINCT r.id) >= :tr OR COUNT(DISTINCT rj.id) >= :tj')
            ->setParameter('tr', $tr)
            ->setParameter('tj', $tj)
            ->orderBy('COUNT(DISTINCT r.id) + COUNT(DISTINCT rj.id)', 'DESC');

        // ✅ rôles ciblés (base)
        $qb->andWhere('(u.roles LIKE :talent OR u.roles LIKE :part OR u.roles LIKE :comp)')
            ->setParameter('talent', '%"ROLE_TALENT"%')
            ->setParameter('part',   '%"ROLE_PARTICULIER"%')
            ->setParameter('comp',   '%"ROLE_COMPANY"%');

        // filtre role
        if ($role === 'talent') {
            $qb->andWhere('u.roles LIKE :rt')->setParameter('rt', '%"ROLE_TALENT"%');
        } elseif ($role === 'particulier') {
            $qb->andWhere('u.roles LIKE :rp')->setParameter('rp', '%"ROLE_PARTICULIER"%');
        } elseif ($role === 'company') {
            $qb->andWhere('u.roles LIKE :rc')->setParameter('rc', '%"ROLE_COMPANY"%');
        }

        // recherche
        if ($q !== '') {
            $qb->andWhere('u.email LIKE :q OR u.phone LIKE :q OR pp.fullName LIKE :q OR pp.city LIKE :q OR pp.adress LIKE :q')
            ->setParameter('q', '%'.$q.'%');
        }

        $items = $qb->setFirstResult($offset)->setMaxResults($limit)->getQuery()->getResult();

        // ==========================
        // (2) TOTAL query (sans GROUP BY/HAVING)
        // ✅ via sous-requêtes COUNT
        // ==========================
        $qbCount = $this->createQueryBuilder('u')
            ->select('COUNT(DISTINCT u.id)')
            ->leftJoin('u.personalProfile', 'pp')

            ->andWhere('(u.roles LIKE :talent OR u.roles LIKE :part OR u.roles LIKE :comp)')
            ->setParameter('talent', '%"ROLE_TALENT"%')
            ->setParameter('part',   '%"ROLE_PARTICULIER"%')
            ->setParameter('comp',   '%"ROLE_COMPANY"%')

            // ✅ seuils appliqués en WHERE (pas de HAVING)
            ->andWhere('
                (SELECT COUNT(r2.id)
                FROM App\Entity\Report r2
                WHERE r2.targetUser = u
                AND r2.status = :open
                AND r2.createdAt >= :from
                ) >= :tr
                OR
                (SELECT COUNT(rj2.id)
                FROM App\Entity\ReportJob rj2
                JOIN rj2.job j2
                WHERE j2.createdBy = u
                AND rj2.createdAt >= :from
                AND rj2.status IN (:jobStatuses)
                ) >= :tj
            ')
            ->setParameter('open', 'open')
            ->setParameter('jobStatuses', ['pending', 'reviewed'])
            ->setParameter('from', $from)
            ->setParameter('tr', $tr)
            ->setParameter('tj', $tj);

        // filtre role (mêmes conditions que items)
        if ($role === 'talent') {
            $qbCount->andWhere('u.roles LIKE :rt')->setParameter('rt', '%"ROLE_TALENT"%');
        } elseif ($role === 'particulier') {
            $qbCount->andWhere('u.roles LIKE :rp')->setParameter('rp', '%"ROLE_PARTICULIER"%');
        } elseif ($role === 'company') {
            $qbCount->andWhere('u.roles LIKE :rc')->setParameter('rc', '%"ROLE_COMPANY"%');
        }

        // recherche
        if ($q !== '') {
            $qbCount->andWhere('u.email LIKE :q OR u.phone LIKE :q OR pp.fullName LIKE :q OR pp.city LIKE :q OR pp.adress LIKE :q')
                    ->setParameter('q', '%'.$q.'%');
        }

        $total = (int) $qbCount->getQuery()->getSingleScalarResult();

        return ['items' => $items, 'total' => $total];
    }


    public function findOneWithProfiles(int $id): ?User
    {
        return $this->createQueryBuilder('u')
            ->leftJoin('u.personalProfile', 'pp')->addSelect('pp')
            ->leftJoin('u.professionalProfile', 'pro')->addSelect('pro')
            ->andWhere('u.id = :id')->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }


    public function findUsersForModerator(string $filter, string $q, string $role, int $limit, int $offset): array
    {
        $qb = $this->createQueryBuilder('u')
            ->leftJoin('u.personalProfile', 'pp')->addSelect('pp')
            ->leftJoin('u.professionalProfile', 'pro')->addSelect('pro')
            ->orderBy('u.createdAt', 'DESC');

        // rôles visibles par le modérateur
        $qb->andWhere('(u.roles LIKE :talent OR u.roles LIKE :part OR u.roles LIKE :comp)')
            ->setParameter('talent', '%"ROLE_TALENT"%')
            ->setParameter('part',   '%"ROLE_PARTICULIER"%')
            ->setParameter('comp',   '%"ROLE_COMPANY"%');

        if ($filter === 'new') {
            $from = (new \DateTimeImmutable('-24 hours'));
            $qb->andWhere('u.createdAt >= :from')->setParameter('from', $from);
        }

        if ($role === 'talent') {
            $qb->andWhere('u.roles LIKE :rt')->setParameter('rt', '%"ROLE_TALENT"%');
        } elseif ($role === 'particulier') {
            $qb->andWhere('u.roles LIKE :rp')->setParameter('rp', '%"ROLE_PARTICULIER"%');
        } elseif ($role === 'company') {
            $qb->andWhere('u.roles LIKE :rc')->setParameter('rc', '%"ROLE_COMPANY"%');
        }

        if ($q !== '') {
            $qb->andWhere('u.email LIKE :q OR u.phone LIKE :q OR pp.fullName LIKE :q OR pp.city LIKE :q OR pp.adress LIKE :q')
            ->setParameter('q', '%'.$q.'%');
        }

        $items = $qb->setFirstResult($offset)->setMaxResults($limit)->getQuery()->getResult();

        $total = (int) (clone $qb)
            ->resetDQLPart('orderBy')
            ->select('COUNT(DISTINCT u.id)')
            ->getQuery()->getSingleScalarResult();

        return ['items' => $items, 'total' => $total];
    }

    public function countNewUsers24h(): int
    {
        $from = new \DateTimeImmutable('-24 hours');

        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u.createdAt >= :from')->setParameter('from', $from)
            ->getQuery()->getSingleScalarResult();
    }



    /**
     * Liste "annuaire modérateur" (anti N+1)
     * $scope: all|company|particulier|talent
     */
    public function findForModeratorDirectory(string $scope, string $q, int $limit, int $offset): array
    {
        $qb = $this->createQueryBuilder('u')
            ->leftJoin('u.personalProfile', 'pp')->addSelect('pp')
            ->leftJoin('u.professionalProfile', 'pro')->addSelect('pro')
            ->orderBy('u.createdAt', 'DESC');

        // 🔒 scopes autorisés par modérateur
        // (si tu veux inclure d'autres rôles, adapte ici)
        $qb->andWhere('(u.roles LIKE :talent OR u.roles LIKE :part OR u.roles LIKE :comp)')
            ->setParameter('talent', '%"ROLE_TALENT"%')
            ->setParameter('part',   '%"ROLE_PARTICULIER"%')
            ->setParameter('comp',   '%"ROLE_COMPANY"%');

        // Filtre scope
        if ($scope === 'talent') {
            $qb->andWhere('u.roles LIKE :rt')->setParameter('rt', '%"ROLE_TALENT"%');
        } elseif ($scope === 'particulier') {
            $qb->andWhere('u.roles LIKE :rp')->setParameter('rp', '%"ROLE_PARTICULIER"%');
        } elseif ($scope === 'company') {
            $qb->andWhere('u.roles LIKE :rc')->setParameter('rc', '%"ROLE_COMPANY"%');
        }

        // Recherche
        if ($q !== '') {
            $qb->andWhere('
                u.email LIKE :q
                OR u.phone LIKE :q
                OR pp.fullName LIKE :q
                OR pp.city LIKE :q
                OR pp.adress LIKE :q
            ')
            ->setParameter('q', '%'.$q.'%');
        }

        $items = $qb->setFirstResult($offset)->setMaxResults($limit)->getQuery()->getResult();

        $total = (int) (clone $qb)
            ->resetDQLPart('orderBy')
            ->select('COUNT(DISTINCT u.id)')
            ->getQuery()->getSingleScalarResult();

        return ['items' => $items, 'total' => $total];
    }

    public function countForModeratorDirectory(string $scope): int
    {
        $qb = $this->createQueryBuilder('u')
            ->select('COUNT(u.id)');

        $qb->andWhere('(u.roles LIKE :talent OR u.roles LIKE :part OR u.roles LIKE :comp)')
            ->setParameter('talent', '%"ROLE_TALENT"%')
            ->setParameter('part',   '%"ROLE_PARTICULIER"%')
            ->setParameter('comp',   '%"ROLE_COMPANY"%');

        if ($scope === 'talent') {
            $qb->andWhere('u.roles LIKE :rt')->setParameter('rt', '%"ROLE_TALENT"%');
        } elseif ($scope === 'particulier') {
            $qb->andWhere('u.roles LIKE :rp')->setParameter('rp', '%"ROLE_PARTICULIER"%');
        } elseif ($scope === 'company') {
            $qb->andWhere('u.roles LIKE :rc')->setParameter('rc', '%"ROLE_COMPANY"%');
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }


    public function statsNewUsers7d(): array
    {
        $from = (new \DateTimeImmutable('-7 days'))->setTime(0, 0, 0);

        // Total inscrits (7j)
        $total7d = (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u.createdAt >= :from')->setParameter('from', $from)
            ->getQuery()->getSingleScalarResult();

        // Par rôle (7j) : on fait 3 counts simples (safe, stable)
        $talent7d = (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u.createdAt >= :from')->setParameter('from', $from)
            ->andWhere('u.roles LIKE :rt')->setParameter('rt', '%"ROLE_TALENT"%')
            ->getQuery()->getSingleScalarResult();

        $part7d = (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u.createdAt >= :from')->setParameter('from', $from)
            ->andWhere('u.roles LIKE :rp')->setParameter('rp', '%"ROLE_PARTICULIER"%')
            ->getQuery()->getSingleScalarResult();

        $comp7d = (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u.createdAt >= :from')->setParameter('from', $from)
            ->andWhere('u.roles LIKE :rc')->setParameter('rc', '%"ROLE_COMPANY"%')
            ->getQuery()->getSingleScalarResult();

        return [
            'analytics' => [
                'totalNew7d' => $total7d,
                'total7d' => $total7d,
            ],
            'roles7d' => [
                'ROLE_TALENT' => $talent7d,
                'ROLE_PARTICULIER' => $part7d,
                'ROLE_COMPANY' => $comp7d,
            ],
            'from' => $from,
        ];
    }

    /**
     * Liste paginée des users sur une période (range=7d par défaut si présent)
     * + filtre rôle optionnel (ROLE_TALENT / ROLE_PARTICULIER / ROLE_COMPANY)
     */
    public function findUsersRange(string $range, string $role, string $q, int $limit, int $offset): array
    {
        $from = null;
        if ($range === '7d') $from = (new \DateTimeImmutable('-7 days'))->setTime(0,0,0);
        elseif ($range === '30d') $from = (new \DateTimeImmutable('-30 days'))->setTime(0,0,0);

        $qb = $this->createQueryBuilder('u')
            ->select('DISTINCT u')
            ->leftJoin('u.personalProfile', 'pp')->addSelect('pp')
            ->leftJoin('u.professionalProfile', 'pro')->addSelect('pro')
            ->orderBy('u.createdAt', 'DESC');

        if ($from) {
            $qb->andWhere('u.createdAt >= :from')->setParameter('from', $from);
        }

        if ($role !== '') {
            $qb->andWhere('u.roles LIKE :r')->setParameter('r', '%"'.$role.'"%');
        }

        if ($q !== '') {
            $qb->andWhere('u.email LIKE :q OR u.phone LIKE :q OR pp.fullName LIKE :q OR pp.city LIKE :q OR pp.adress LIKE :q')
            ->setParameter('q', '%'.$q.'%');
        }

        // total
        $qbCount = clone $qb;
        $total = (int) $qbCount
            ->resetDQLPart('orderBy')
            ->select('COUNT(DISTINCT u.id)')
            ->setFirstResult(null)
            ->setMaxResults(null)
            ->getQuery()->getSingleScalarResult();

        // items
        $items = $qb->setFirstResult($offset)->setMaxResults($limit)->getQuery()->getResult();

        return ['items'=>$items,'total'=>$total];
    }


    public function countNewUsersSince(\DateTimeImmutable $from): int
    {
        return (int)$this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u.createdAt >= :from')->setParameter('from', $from)
            ->getQuery()->getSingleScalarResult();
    }

    /** @return array<int, \App\Entity\User> map [id=>User] avec pp/pro join */
    public function findManyWithProfilesMap(array $ids): array
    {
        if (!$ids) return [];

        $rows = $this->createQueryBuilder('u')
            ->select('DISTINCT u')
            ->leftJoin('u.personalProfile', 'pp')->addSelect('pp')
            ->leftJoin('u.professionalProfile', 'pro')->addSelect('pro')
            ->andWhere('u.id IN (:ids)')->setParameter('ids', $ids)
            ->getQuery()->getResult();

        $map = [];
        foreach ($rows as $u) $map[$u->getId()] = $u;
        return $map;
    }

    public function setActiveById(int $id, bool $active): void
    {
        $this->em->createQuery('
            UPDATE App\Entity\User u
            SET u.isActive = :a, u.updatedAt = :dt
            WHERE u.id = :id
        ')
        ->setParameter('a', $active)
        ->setParameter('dt', new \DateTime())
        ->setParameter('id', $id)
        ->execute();
    }



    public function countAllUsers(): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countActiveUsers(): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u.isActive = :active')
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countVerifiedUsers(): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u.isEmailVerified = :verified')
            ->setParameter('verified', true)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findRecentUsers(int $limit = 8): array
    {
        return $this->createQueryBuilder('u')
            ->orderBy('u.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findLastLogins(int $limit = 8): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.lastLoginAt IS NOT NULL')
            ->orderBy('u.lastLoginAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function getSignupChartSince(\DateTimeInterface $since): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = '
            SELECT DATE(created_at) AS d, COUNT(id) AS c
            FROM user
            WHERE created_at >= :since
            GROUP BY DATE(created_at)
            ORDER BY d ASC
        ';

        return $conn->executeQuery($sql, [
            'since' => $since->format('Y-m-d H:i:s'),
        ])->fetchAllAssociative();
    }
    public function getRoleStats(): array
    {
        $stats = [
            'admins' => 0,
            'moderators' => 0,
            'companies' => 0,
            'talents' => 0,
            'particuliers' => 0,
        ];

        $users = $this->createQueryBuilder('u')
            ->select('u.roles')
            ->getQuery()
            ->getArrayResult();

        foreach ($users as $row) {
            $roles = $row['roles'] ?? [];

            if (in_array('ROLE_ADMIN', $roles, true)) {
                $stats['admins']++;
            }
            if (in_array('ROLE_MODERATEUR', $roles, true) || in_array('ROLE_MODERATOR', $roles, true)) {
                $stats['moderators']++;
            }
            if (in_array('ROLE_COMPANY', $roles, true)) {
                $stats['companies']++;
            }
            if (in_array('ROLE_TALENT', $roles, true)) {
                $stats['talents']++;
            }
            if (in_array('ROLE_PARTICULIER', $roles, true)) {
                $stats['particuliers']++;
            }
        }

        return $stats;
    }

    public function countUsersHavingRole(string $role): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u.roles LIKE :role')
            ->setParameter('role', '%"' . $role . '"%')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findDistinctRoles(): array
    {
        $rows = $this->createQueryBuilder('u')
            ->select('u.roles')
            ->getQuery()
            ->getArrayResult();

        $roles = [];

        foreach ($rows as $row) {
            foreach (($row['roles'] ?? []) as $role) {
                $roles[$role] = $role;
            }
        }

        ksort($roles);

        return array_values($roles);
    }

    public function findUsersHavingRole(string $role, int $limit = 100): array
    {
        return $this->createQueryBuilder('u')
            ->leftJoin('u.personalProfile', 'pp')->addSelect('pp')
            ->leftJoin('u.country', 'c')->addSelect('c')
            ->leftJoin('u.typeCompte', 'tc')->addSelect('tc')
            ->andWhere('u.roles LIKE :role')
            ->setParameter('role', '%"' . $role . '"%')
            ->orderBy('u.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countUsersHavingRoleFiltered(string $role, ?string $q = null): int
    {
        $qb = $this->createQueryBuilder('u')
            ->select('COUNT(DISTINCT u.id)')
            ->leftJoin('u.personalProfile', 'pp')
            ->andWhere('u.roles LIKE :role')
            ->setParameter('role', '%"' . $role . '"%');

        if ($q !== null && trim($q) !== '') {
            $qb
                ->andWhere('
                    LOWER(u.email) LIKE :q
                    OR LOWER(u.phone) LIKE :q
                    OR LOWER(pp.fullName) LIKE :q
                ')
                ->setParameter('q', '%' . mb_strtolower(trim($q)) . '%');
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function findUsersHavingRolePaginated(
        string $role,
        int $page = 1,
        int $perPage = 10,
        ?string $q = null
    ): array {
        $offset = max(0, ($page - 1) * $perPage);

        $qb = $this->createQueryBuilder('u')
            ->leftJoin('u.personalProfile', 'pp')->addSelect('pp')
            ->leftJoin('u.country', 'c')->addSelect('c')
            ->leftJoin('u.typeCompte', 'tc')->addSelect('tc')
            ->andWhere('u.roles LIKE :role')
            ->setParameter('role', '%"' . $role . '"%')
            ->orderBy('u.createdAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($perPage);

        if ($q !== null && trim($q) !== '') {
            $qb
                ->andWhere('
                    LOWER(u.email) LIKE :q
                    OR LOWER(u.phone) LIKE :q
                    OR LOWER(pp.fullName) LIKE :q
                ')
                ->setParameter('q', '%' . mb_strtolower(trim($q)) . '%');
        }

        return $qb->getQuery()->getResult();
    }

    public function findNonSuperAdmins()
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.roles NOT LIKE :role')
            ->setParameter('role','%ROLE_ADMIN%')
            ->orderBy('u.id','DESC')
            ->getQuery()
            ->getResult();
    }

    public function findCompanies()
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.roles LIKE :role')
            ->setParameter('role','%ROLE_COMPANY%')
            ->getQuery()
            ->getResult();
    }

    public function findTalents()
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.roles LIKE :role')
            ->setParameter('role','%ROLE_TALENT%')
            ->getQuery()
            ->getResult();
    }

    public function searchAdmin(string $q)
    {
        return $this->createQueryBuilder('u')
            ->select('u.id, u.email, u.nom')
            ->where('u.email LIKE :q OR u.nom LIKE :q')
            ->setParameter('q', "%$q%")
            ->setMaxResults(5)
            ->getQuery()
            ->getArrayResult();
    }

    public function countUsersHavingAnyRole(array $roles): int
    {
        $qb = $this->createQueryBuilder('u')
            ->select('COUNT(u.id)');

        $orX = $qb->expr()->orX();

        foreach ($roles as $i => $role) {
            $param = 'role_' . $i;
            $orX->add($qb->expr()->like('u.roles', ':' . $param));
            $qb->setParameter($param, '%"' . $role . '"%');
        }

        $qb->andWhere($orX);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function countUsersCreatedBetweenWithRoles(
        \DateTimeInterface $start,
        \DateTimeInterface $end,
        array $roles
    ): int {
        $qb = $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u.createdAt >= :start')
            ->andWhere('u.createdAt < :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end);

        if (!empty($roles)) {
            $orX = $qb->expr()->orX();

            foreach ($roles as $i => $role) {
                $param = 'role_' . $i;
                $orX->add($qb->expr()->like('u.roles', ':' . $param));
                $qb->setParameter($param, '%"' . $role . '"%');
            }

            $qb->andWhere($orX);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function getCurrentMonthRegistrationStatsForRoles(array $roles): array
    {
        $now = new \DateTimeImmutable();

        $startCurrentMonth = $now->modify('first day of this month')->setTime(0, 0, 0);
        $startNextMonth = $startCurrentMonth->modify('+1 month');
        $startPreviousMonth = $startCurrentMonth->modify('-1 month');

        $currentMonthCount = $this->countUsersCreatedBetweenWithRoles(
            $startCurrentMonth,
            $startNextMonth,
            $roles
        );

        $previousMonthCount = $this->countUsersCreatedBetweenWithRoles(
            $startPreviousMonth,
            $startCurrentMonth,
            $roles
        );

        if ($previousMonthCount > 0) {
            $percentage = round((($currentMonthCount - $previousMonthCount) / $previousMonthCount) * 100, 1);
        } else {
            $percentage = $currentMonthCount > 0 ? 100.0 : 0.0;
        }

        return [
            'currentMonthCount' => $currentMonthCount,
            'previousMonthCount' => $previousMonthCount,
            'percentage' => $percentage,
        ];
    }


    private function allowedRolesWhereSql(string $alias = 'u'): string
    {
        return sprintf(
            "(
                JSON_SEARCH(%s.roles, 'one', 'ROLE_TALENT') IS NOT NULL
                OR JSON_SEARCH(%s.roles, 'one', 'ROLE_PARTICULIER') IS NOT NULL
                OR JSON_SEARCH(%s.roles, 'one', 'ROLE_COMPANY') IS NOT NULL
            )",
            $alias,
            $alias,
            $alias
        );
    }

    public function countAllowedRoleUsersBetween(\DateTimeInterface $start, \DateTimeInterface $end): int
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = sprintf(
            "SELECT COUNT(u.id)
             FROM `user` u
             WHERE %s
               AND u.created_at >= :start
               AND u.created_at < :end",
            $this->allowedRolesWhereSql('u')
        );

        return (int) $conn->fetchOne($sql, [
            'start' => $start->format('Y-m-d H:i:s'),
            'end'   => $end->format('Y-m-d H:i:s'),
        ]);
    }

    public function getGrowthStatsAllowedRoles(): array
    {
        $now = new \DateTimeImmutable();

        $current30Start = $now->modify('-30 days');
        $previous30Start = $now->modify('-60 days');

        $current = $this->countAllowedRoleUsersBetween($current30Start, $now);
        $previous = $this->countAllowedRoleUsersBetween($previous30Start, $current30Start);

        $delta = 0.0;
        if ($previous > 0) {
            $delta = (($current - $previous) / $previous) * 100;
        }

        return [
            'signups' => $current,
            'delta' => round($delta, 1),
        ];
    }

    public function getSignupChart30DaysAllowedRoles(): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $start = (new \DateTimeImmutable('today'))->modify('-29 days');
        $end = new \DateTimeImmutable('tomorrow');

        $sql = sprintf(
            "SELECT DATE(u.created_at) AS dte, COUNT(u.id) AS c
             FROM `user` u
             WHERE %s
               AND u.created_at >= :start
               AND u.created_at < :end
             GROUP BY DATE(u.created_at)
             ORDER BY DATE(u.created_at) ASC",
            $this->allowedRolesWhereSql('u')
        );

        $rows = $conn->fetchAllAssociative($sql, [
            'start' => $start->format('Y-m-d 00:00:00'),
            'end'   => $end->format('Y-m-d 00:00:00'),
        ]);

        $map = [];
        foreach ($rows as $row) {
            $map[$row['dte']] = (int) $row['c'];
        }

        $labels = [];
        $data = [];

        for ($i = 0; $i < 30; $i++) {
            $date = $start->modify("+{$i} days");
            $key = $date->format('Y-m-d');

            $labels[] = $date->format('d/m');
            $data[] = $map[$key] ?? 0;
        }

        return [
            'labels' => $labels,
            'signups' => $data,
        ];
    }

    public function getSignupChart90DaysAllowedRoles(): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $start = (new \DateTimeImmutable('today'))->modify('-89 days');
        $end = new \DateTimeImmutable('tomorrow');

        $sql = sprintf(
            "SELECT DATE(u.created_at) AS dte, COUNT(u.id) AS c
             FROM `user` u
             WHERE %s
               AND u.created_at >= :start
               AND u.created_at < :end
             GROUP BY DATE(u.created_at)
             ORDER BY DATE(u.created_at) ASC",
            $this->allowedRolesWhereSql('u')
        );

        $rows = $conn->fetchAllAssociative($sql, [
            'start' => $start->format('Y-m-d 00:00:00'),
            'end'   => $end->format('Y-m-d 00:00:00'),
        ]);

        $map = [];
        foreach ($rows as $row) {
            $map[$row['dte']] = (int) $row['c'];
        }

        $labels = [];
        $data = [];

        for ($i = 0; $i < 90; $i++) {
            $date = $start->modify("+{$i} days");
            $key = $date->format('Y-m-d');

            $labels[] = $date->format('d/m');
            $data[] = $map[$key] ?? 0;
        }

        return [
            'labels' => $labels,
            'signups' => $data,
        ];
    }

    public function getSignupChartCurrentYearToCurrentMonthAllowedRoles(): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $yearStart = new \DateTimeImmutable(date('Y-01-01 00:00:00'));
        $nextMonth = (new \DateTimeImmutable('first day of next month'))->setTime(0, 0, 0);

        $sql = sprintf(
            "SELECT MONTH(u.created_at) AS month_num, COUNT(u.id) AS c
             FROM `user` u
             WHERE %s
               AND u.created_at >= :start
               AND u.created_at < :end
             GROUP BY MONTH(u.created_at)
             ORDER BY MONTH(u.created_at) ASC",
            $this->allowedRolesWhereSql('u')
        );

        $rows = $conn->fetchAllAssociative($sql, [
            'start' => $yearStart->format('Y-m-d H:i:s'),
            'end'   => $nextMonth->format('Y-m-d H:i:s'),
        ]);

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['month_num']] = (int) $row['c'];
        }

        $currentMonth = (int) date('n');

        $labels = [];
        $data = [];

        for ($month = 1; $month <= $currentMonth; $month++) {
            $labels[] = $this->monthShortFr($month);
            $data[] = $map[$month] ?? 0;
        }

        return [
            'labels' => $labels,
            'signups' => $data,
        ];
    }

    private function monthShortFr(int $month): string
    {
        return match ($month) {
            1 => 'Jan',
            2 => 'Fév',
            3 => 'Mar',
            4 => 'Avr',
            5 => 'Mai',
            6 => 'Juin',
            7 => 'Juil',
            8 => 'Aoû',
            9 => 'Sep',
            10 => 'Oct',
            11 => 'Nov',
            12 => 'Déc',
            default => '',
        };
    }


    public function getUsersSplitStats(): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = <<<SQL
            SELECT
                SUM(CASE WHEN JSON_SEARCH(u.roles, 'one', 'ROLE_COMPANY') IS NOT NULL THEN 1 ELSE 0 END) AS company,
                SUM(CASE WHEN JSON_SEARCH(u.roles, 'one', 'ROLE_TALENT') IS NOT NULL THEN 1 ELSE 0 END) AS talent,
                SUM(CASE WHEN JSON_SEARCH(u.roles, 'one', 'ROLE_PARTICULIER') IS NOT NULL THEN 1 ELSE 0 END) AS particulier,
                SUM(CASE
                    WHEN JSON_SEARCH(u.roles, 'one', 'ROLE_ADMIN') IS NOT NULL
                    OR JSON_SEARCH(u.roles, 'one', 'ROLE_MODERATOR') IS NOT NULL
                    OR JSON_SEARCH(u.roles, 'one', 'ROLE_MODERATEUR') IS NOT NULL
                    THEN 1 ELSE 0
                END) AS staff
            FROM `user` u
        SQL;

        $row = $conn->fetchAssociative($sql) ?: [];

        return [
            'company' => (int) ($row['company'] ?? 0),
            'talent' => (int) ($row['talent'] ?? 0),
            'particulier' => (int) ($row['particulier'] ?? 0),
            'staff' => (int) ($row['staff'] ?? 0),
        ];
    }


    public function getActivationRateMainRoles(): int
    {
        $total = $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.roles LIKE :company OR u.roles LIKE :talent OR u.roles LIKE :particulier')
            ->setParameter('company', '%ROLE_COMPANY%')
            ->setParameter('talent', '%ROLE_TALENT%')
            ->setParameter('particulier', '%ROLE_PARTICULIER%')
            ->getQuery()
            ->getSingleScalarResult();

        $active = $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.isActive = :active')
            ->andWhere('(u.roles LIKE :company OR u.roles LIKE :talent OR u.roles LIKE :particulier)')
            ->setParameter('active', true)
            ->setParameter('company', '%ROLE_COMPANY%')
            ->setParameter('talent', '%ROLE_TALENT%')
            ->setParameter('particulier', '%ROLE_PARTICULIER%')
            ->getQuery()
            ->getSingleScalarResult();

        $total = (int) $total;
        $active = (int) $active;

        if ($total === 0) {
            return 0;
        }

        return (int) round(($active / $total) * 100);
    }

    public function getRetentionRateMainRoles(): int
    {
        // $since = (new \DateTimeImmutable())->modify(sprintf('-%d days', $days));

        $totalOldUsers = $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            // ->where('u.createdAt <= :since')
            ->andWhere('(u.roles LIKE :company OR u.roles LIKE :talent OR u.roles LIKE :particulier)')
            // ->setParameter('since', $since)
            ->setParameter('company', '%ROLE_COMPANY%')
            ->setParameter('talent', '%ROLE_TALENT%')
            ->setParameter('particulier', '%ROLE_PARTICULIER%')
            ->getQuery()
            ->getSingleScalarResult();

        $stillActiveOldUsers = $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            // ->where('u.createdAt <= :since')
            ->andWhere('u.isActive = :active')
            ->andWhere('(u.roles LIKE :company OR u.roles LIKE :talent OR u.roles LIKE :particulier)')
            // ->setParameter('since', $since)
            ->setParameter('active', false)
            ->setParameter('company', '%ROLE_COMPANY%')
            ->setParameter('talent', '%ROLE_TALENT%')
            ->setParameter('particulier', '%ROLE_PARTICULIER%')
            ->getQuery()
            ->getSingleScalarResult();

        $totalOldUsers = (int) $totalOldUsers;
        $stillActiveOldUsers = (int) $stillActiveOldUsers;

        if ($totalOldUsers === 0) {
            return 0;
        }

        return (int) round(($stillActiveOldUsers / $totalOldUsers) * 100);
    }

    public function getDailyRegistrationsByMainRolesLastDays(int $days = 30): array
    {
        $start = (new \DateTimeImmutable('today 00:00:00'))
            ->modify('-' . ($days - 1) . ' days');

        $sql = "
            SELECT 
                DATE(u.created_at) AS day,
                SUM(CASE WHEN u.roles LIKE :talentRole THEN 1 ELSE 0 END) AS talent,
                SUM(CASE WHEN u.roles LIKE :particulierRole THEN 1 ELSE 0 END) AS particulier,
                SUM(CASE WHEN u.roles LIKE :companyRole THEN 1 ELSE 0 END) AS company
            FROM `user` u
            WHERE u.created_at >= :startDate
            GROUP BY DATE(u.created_at)
            ORDER BY day ASC
        ";

        $rows = $this->getEntityManager()
            ->getConnection()
            ->fetchAllAssociative($sql, [
                'startDate' => $start->format('Y-m-d H:i:s'),
                'talentRole' => '%"ROLE_TALENT"%',
                'particulierRole' => '%"ROLE_PARTICULIER"%',
                'companyRole' => '%"ROLE_COMPANY"%'
            ]);

        $indexed = [];
        foreach ($rows as $row) {
            $indexed[$row['day']] = [
                'talent' => (int) $row['talent'],
                'particulier' => (int) $row['particulier'],
                'company' => (int) $row['company'],
            ];
        }

        $labels = [];
        $talent = [];
        $particulier = [];
        $company = [];

        for ($i = 0; $i < $days; $i++) {
            $date = $start->modify("+{$i} days");
            $key = $date->format('Y-m-d');

            $labels[] = $date->format('d/m');
            $talent[] = $indexed[$key]['talent'] ?? 0;
            $particulier[] = $indexed[$key]['particulier'] ?? 0;
            $company[] = $indexed[$key]['company'] ?? 0;
        }

        return [
            'labels' => $labels,
            'talent' => $talent,
            'particulier' => $particulier,
            'company' => $company,
        ];
    }

    /**
     * Utilisateurs finalisés créés entre deux dates.
     * Sert pour signups7d, gender7d et roles7d.
     */
    public function findCompletedUsersCreatedBetween(
        \DateTimeInterface $start,
        \DateTimeInterface $end
    ): array {
        return $this->createQueryBuilder('u')
            ->innerJoin('u.personalProfile', 'pp')
            ->addSelect('pp')
            ->leftJoin('pp.sexe', 's')
            ->addSelect('s')
            ->andWhere('u.createdAt >= :start')
            ->andWhere('u.createdAt < :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('u.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Derniers utilisateurs ayant finalisé leur PersonalProfile.
     * Sert pour lastUsers.
     */
    public function findLastCompletedUsers(int $limit = 4): array
    {
        return $this->createQueryBuilder('u')
            ->innerJoin('u.personalProfile', 'pp')
            ->addSelect('pp')
            ->leftJoin('pp.sexe', 's')
            ->addSelect('s')
            ->orderBy('u.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Nombre d'utilisateurs finalisés créés entre deux dates.
     * Optionnel, utile si tu veux une stat simple.
     */
    public function countCompletedUsersCreatedBetween(
        \DateTimeInterface $start,
        \DateTimeInterface $end
    ): int {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->innerJoin('u.personalProfile', 'pp')
            ->andWhere('u.createdAt >= :start')
            ->andWhere('u.createdAt < :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Parcourt les comptes membres assez anciens pour recevoir une relance de profil.
     *
     * @return iterable<User>
     */
    public function iterateProfileReminderCandidates(
        \DateTimeInterface $registeredBefore
    ): iterable {
        return $this->createQueryBuilder('u')
            ->leftJoin('u.personalProfile', 'pp')->addSelect('pp')
            ->leftJoin('u.professionalProfile', 'pro')->addSelect('pro')
            ->andWhere('u.isActive = true')
            ->andWhere('u.createdAt <= :registeredBefore')
            ->andWhere('(u.roles LIKE :talent OR u.roles LIKE :company OR u.roles LIKE :particulier)')
            ->setParameter('registeredBefore', $registeredBefore)
            ->setParameter('talent', '%ROLE_TALENT%')
            ->setParameter('company', '%ROLE_COMPANY%')
            ->setParameter('particulier', '%ROLE_PARTICULIER%')
            ->orderBy('u.createdAt', 'ASC')
            ->getQuery()
            ->toIterable();
    }

    /**
     * Sélectionne le compte administrateur utilisé comme expéditeur du chat système.
     */
    public function findPlatformNotificationSender(): ?User
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.isActive = true')
            ->andWhere('(u.roles LIKE :superAdmin OR u.roles LIKE :admin)')
            ->setParameter('superAdmin', '%ROLE_SUPER_ADMIN%')
            ->setParameter('admin', '%ROLE_ADMIN%')
            ->orderBy('u.id', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Ajoute les critères communs empêchant l'affichage d'un profil public incomplet.
     */
    private function applyPublicProfileVisibility(QueryBuilder $qb, string $role): void
    {
        $validFullName = "(
            pp.fullName IS NOT NULL
            AND TRIM(pp.fullName) NOT IN ('', '-', '—')
            AND LOWER(TRIM(pp.fullName)) <> 'profil sans nom'
            AND pp.fullName NOT LIKE '%@%'
            AND LOWER(pp.fullName) <> LOWER(u.email)
        )";

        if ($role === 'ROLE_COMPANY') {
            $validCompanyName = "(
                pp.companyTradeName IS NOT NULL
                AND TRIM(pp.companyTradeName) NOT IN ('', '-', '—')
                AND LOWER(TRIM(pp.companyTradeName)) <> 'profil sans nom'
                AND pp.companyTradeName NOT LIKE '%@%'
            ) OR (
                pp.companyLegalName IS NOT NULL
                AND TRIM(pp.companyLegalName) NOT IN ('', '-', '—')
                AND LOWER(TRIM(pp.companyLegalName)) <> 'profil sans nom'
                AND pp.companyLegalName NOT LIKE '%@%'
            ) OR {$validFullName}";

            $qb->andWhere('(' . $validCompanyName . ')');
        } else {
            $qb->andWhere($validFullName);
        }

        $qb
            ->andWhere('pp.photo IS NOT NULL')
            ->andWhere("TRIM(pp.photo) <> ''")
            ->andWhere("LOWER(pp.photo) NOT IN ('avatar.png', 'default-avatar.png', 'default.png')")
            ->andWhere("LOWER(pp.photo) NOT LIKE '%/avatar.png'")
            ->andWhere("LOWER(pp.photo) NOT LIKE '%/default-avatar.png'")
            ->andWhere("LOWER(pp.photo) NOT LIKE '%/default.png'");
    }


    //    /**
    //     * @return User[] Returns an array of User objects
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

    //    public function findOneBySomeField($value): ?User
    //    {
    //        return $this->createQueryBuilder('u')
    //            ->andWhere('u.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
