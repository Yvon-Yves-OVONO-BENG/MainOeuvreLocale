<?php

namespace App\Repository;

use App\Entity\Calendrier;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class CalendrierRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Calendrier::class);
    }

    /**
     * @return Calendrier[]
     */
    public function findBetweenForAuthor(User $author, \DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.job', 'j')->addSelect('j')
            ->leftJoin('c.talent', 't')->addSelect('t')
            ->leftJoin('t.personalProfile', 'tp')->addSelect('tp')
            ->andWhere('c.author = :author')
            ->andWhere('c.startAt >= :start')
            ->andWhere('c.startAt < :end')
            ->setParameter('author', $author)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('c.startAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array<string,int>
     */
    public function getStatusStatsForAuthor(User $author): array
    {
        $rows = $this->createQueryBuilder('c')
            ->select('c.status AS status, COUNT(c.id) AS total')
            ->andWhere('c.author = :author')
            ->setParameter('author', $author)
            ->groupBy('c.status')
            ->getQuery()
            ->getArrayResult();

        $stats = [];
        foreach ($rows as $row) {
            $stats[$row['status']] = (int) $row['total'];
        }

        return $stats;
    }
}