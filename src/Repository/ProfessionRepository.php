<?php

namespace App\Repository;

use App\Entity\Profession;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Profession>
 */
class ProfessionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Profession::class);
    }

    public function findByCategorie(int $categorieId): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.categorie = :cid')
            ->setParameter('cid', $categorieId)
            ->orderBy('p.profession', 'ASC')
            ->getQuery()
            ->getResult();
    }


    public function findAllWithJobCounts(): array
    {
        $rows = $this->createQueryBuilder('p')
            ->select('p AS profession, COUNT(j.id) AS total')
            ->leftJoin('p.jobs', 'j')
            ->leftJoin('j.status', 's')
            ->andWhere('j.dateExpirationAt >= :now')
            ->andWhere('j.moderationStatus = :approvedStatus')
            ->andWhere('s.id = :publishedStatus')
            ->setParameter('now', new \DateTime())
            ->setParameter('approvedStatus', 'approved')
            ->setParameter('publishedStatus', 2)
            ->groupBy('p.id')
            ->having('COUNT(j.id) > 0')
            ->orderBy('p.profession', 'ASC')
            ->getQuery()
            ->getResult();
    
        return array_map(static fn ($r) => [
            'profession' => $r['profession'],
            'total'      => (int) $r['total'],
        ], $rows);
    }
    
    
    public function findBySearch(string $search = ''): array
    {
        $qb = $this->createQueryBuilder('p')
                   ->leftJoin('p.categorie', 'c')
                   ->addSelect('c');
        
        if ($search) {
            $qb->andWhere('p.profession LIKE :search OR p.description LIKE :search OR c.nom LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }
        
        return $qb->orderBy('p.id', 'DESC')
                  ->getQuery()
                  ->getResult();
    }

    public function deleteMultiple(array $slugs): void
    {
        $this->createQueryBuilder('p')
             ->delete()
             ->where('p.slug IN (:slugs)')
             ->setParameter('slugs', $slugs)
             ->getQuery()
             ->execute();
    }

    //    /**
    //     * @return Profession[] Returns an array of Profession objects
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

    //    public function findOneBySomeField($value): ?Profession
    //    {
    //        return $this->createQueryBuilder('p')
    //            ->andWhere('p.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }

    /**
     * Choix propres pour les formulaires : enlève les libellés vides et les
     * doublons de casse/espaces sans modifier les relations existantes en BD.
     *
     * @return Profession[]
     */
    public function findUniqueNonEmptyOrdered(?int $categorieId = null, ?int $includeId = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.categorie', 'c')->addSelect('c')
            ->andWhere("TRIM(p.profession) <> ''")
            ->orderBy('p.profession', 'ASC')
            ->addOrderBy('p.id', 'ASC');

        if ($categorieId !== null && $categorieId > 0) {
            $qb->andWhere('c.id = :categorieId')->setParameter('categorieId', $categorieId);
        }

        $seen = [];
        $clean = [];
        foreach ($qb->getQuery()->getResult() as $profession) {
            $key = self::choiceKey((string) $profession->getProfession());
            if ($key === '') {
                continue;
            }

            if (!isset($seen[$key])) {
                $seen[$key] = count($clean);
                $clean[] = $profession;
                continue;
            }

            if ($includeId !== null && (int) $profession->getId() === $includeId) {
                $clean[$seen[$key]] = $profession;
            }
        }

        return $clean;
    }

    public function findDuplicateByName(string $name, ?int $categorieId = null, ?int $excludeId = null): ?Profession
    {
        $qb = $this->createQueryBuilder('p')
            ->andWhere('LOWER(TRIM(p.profession)) = :name')
            ->setParameter('name', mb_strtolower(trim($name)));

        if ($categorieId !== null && $categorieId > 0) {
            $qb->andWhere('IDENTITY(p.categorie) = :categorieId')->setParameter('categorieId', $categorieId);
        }
        if ($excludeId !== null) {
            $qb->andWhere('p.id <> :excludeId')->setParameter('excludeId', $excludeId);
        }

        return $qb->setMaxResults(1)->getQuery()->getOneOrNullResult();
    }

    private static function choiceKey(string $value): string
    {
        $value = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $value) ?? $value));
        if (class_exists(\Normalizer::class)) {
            $normalized = \Normalizer::normalize($value, \Normalizer::FORM_D);
            if (is_string($normalized)) {
                $value = preg_replace('/\p{Mn}+/u', '', $normalized) ?? $normalized;
            }
        }
        return $value;
    }
}
