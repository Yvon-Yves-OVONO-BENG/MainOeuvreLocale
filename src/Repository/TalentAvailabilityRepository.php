<?php

namespace App\Repository;

use App\Entity\TalentAvailability;
use App\Entity\ProfessionalProfile;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class TalentAvailabilityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TalentAvailability::class);
    }

    /**
     * @return string[]
     */
    public function findDateStringsByProfile(ProfessionalProfile $profile): array
    {
        $rows = $this->createQueryBuilder('a')
            ->select('a.availableDate')
            ->andWhere('a.professionalProfile = :profile')
            ->setParameter('profile', $profile)
            ->orderBy('a.availableDate', 'ASC')
            ->getQuery()
            ->getResult();

        return array_map(
            static fn(array $row) => $row['availableDate'] instanceof \DateTimeInterface
                ? $row['availableDate']->format('Y-m-d')
                : (new \DateTime($row['availableDate']))->format('Y-m-d'),
            $rows
        );
    }
}