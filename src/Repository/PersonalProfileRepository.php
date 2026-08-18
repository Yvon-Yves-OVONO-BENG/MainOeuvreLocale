<?php

namespace App\Repository;

use App\Entity\PersonalProfile;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PersonalProfile>
 */
class PersonalProfileRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, protected EntityManagerInterface $em)
    {
        parent::__construct($registry, PersonalProfile::class);
    }

    /**
     * ✅ Stats CNI : pending / verified / rejected
     * (affichage “chips” en haut)
     */
    public function getCniStats(): array
    {
        $rows = $this->createQueryBuilder('pp')
            ->select('pp.cniStatus as st, COUNT(pp.id) as c')
            ->groupBy('pp.cniStatus')
            ->getQuery()->getArrayResult();

        $out = ['pending' => 0, 'verified' => 0, 'rejected' => 0];

        foreach ($rows as $r) {
            $st = (string) ($r['st'] ?? 'pending');
            $out[$st] = (int) $r['c'];
        }

        return $out;
    }


    /**
     * ✅ Met à jour le statut CNI en DQL (pending|verified|rejected)
     * + set cniVerifiedAt + cniVerifiedBy
     */
    public function markCniStatus(int $personalProfileId, string $status, int $moderatorId): void
    {
        $status = in_array($status, ['pending','verified','rejected'], true) ? $status : 'pending';

        $this->em->createQuery('
            UPDATE App\Entity\PersonalProfile pp
            SET pp.cniStatus = :st,
                pp.cniVerifiedAt = :dt,
                pp.cniVerifiedBy = :mod
            WHERE pp.id = :id
        ')
        ->setParameter('st', $status)
        ->setParameter('dt', new \DateTime())
        ->setParameter('mod', $moderatorId) // Doctrine accepte l’id pour ManyToOne en DQL
        ->setParameter('id', $personalProfileId)
        ->execute();
    }


    public function analyticsGenderCountsNewUsers(int $days): array
    {
        $from = (new \DateTimeImmutable())->modify("-{$days} days")->setTime(0,0,0);

        // F/M via Sexe.sexe, et "PM" = sexe null
        $rows = $this->createQueryBuilder('pp')
            ->select("
            SUM(CASE WHEN s.sexe = 'F' THEN 1 ELSE 0 END) as f,
            SUM(CASE WHEN s.sexe = 'M' THEN 1 ELSE 0 END) as m,
            SUM(CASE WHEN pp.sexe IS NULL THEN 1 ELSE 0 END) as pm
            ")
            ->leftJoin('pp.sexe', 's')
            ->innerJoin('pp.user', 'u')
            ->andWhere('u.createdAt >= :from')->setParameter('from', $from)
            ->getQuery()->getOneOrNullResult();

        return [
            'F'  => (int)($rows['f'] ?? 0),
            'M'  => (int)($rows['m'] ?? 0),
            'PM' => (int)($rows['pm'] ?? 0),
        ];
    }

    public function countPendingCni(): int
    {
        return (int) $this->createQueryBuilder('pp')
            ->select('COUNT(pp.id)')
            ->andWhere('pp.cniStatus = :status')
            ->setParameter('status', 'pending')
            ->getQuery()
            ->getSingleScalarResult();
    }

    //    /**
    //     * @return PersonalProfile[] Returns an array of PersonalProfile objects
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

    //    public function findOneBySomeField($value): ?PersonalProfile
    //    {
    //        return $this->createQueryBuilder('p')
    //            ->andWhere('p.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }

    /** @return list<string> */
    public function findDistinctCitiesForSuggestions(int $limit = 300): array
    {
        $rows = $this->createQueryBuilder('profileSuggestion')
            ->select('DISTINCT profileSuggestion.city AS value')
            ->andWhere('profileSuggestion.city IS NOT NULL')
            ->andWhere("TRIM(profileSuggestion.city) <> ''")
            ->orderBy('profileSuggestion.city', 'ASC')
            ->setMaxResults(max(1, min(1000, $limit)))
            ->getQuery()
            ->getArrayResult();

        return array_values(array_filter(array_map(
            static fn (array $row): string => trim((string) ($row['value'] ?? '')),
            $rows
        )));
    }

}
