<?php

namespace App\Repository;

use App\Entity\SupportTicket;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class SupportTicketRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SupportTicket::class);
    }

    public function findLatestTickets(int $limit = 10): array
    {
        return $this->createQueryBuilder('t')
            ->orderBy('t.updatedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;
    }

    public function findOpenTickets(): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.status = :status')
            ->setParameter('status', SupportTicket::STATUS_OPEN)
            ->orderBy('t.updatedAt', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    public function findPendingTickets(): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.status = :status')
            ->setParameter('status', SupportTicket::STATUS_PENDING)
            ->orderBy('t.updatedAt', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }


    public function findByFilters(?string $q = null, ?string $status = null, ?string $priority = null): array
    {
        $qb = $this->createQueryBuilder('t')
            ->orderBy('t.updatedAt', 'DESC');

        if ($q !== null && $q !== '') {
            $qb
                ->andWhere('
                    t.subject LIKE :q
                    OR t.message LIKE :q
                    OR t.customerName LIKE :q
                    OR t.customerEmail LIKE :q
                    OR t.ownerName LIKE :q
                ')
                ->setParameter('q', '%' . $q . '%');
        }

        if ($status !== null && $status !== '') {
            $qb
                ->andWhere('t.status = :status')
                ->setParameter('status', $status);
        }

        if ($priority !== null && $priority !== '') {
            $qb
                ->andWhere('t.priority = :priority')
                ->setParameter('priority', $priority);
        }

        return $qb->getQuery()->getResult();
    }
}