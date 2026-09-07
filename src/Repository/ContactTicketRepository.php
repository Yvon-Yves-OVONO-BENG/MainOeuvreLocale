<?php

namespace App\Repository;

use App\Entity\ContactTicket;
use App\Entity\Profession;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ContactTicket> */
final class ContactTicketRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ContactTicket::class);
    }

    public function findAvailable(User $user, Profession $profession): ?ContactTicket
    {
        $categorie = $profession->getCategorie();
        if (!$categorie) {
            return null;
        }

        return $this->createQueryBuilder('ticket')
            ->innerJoin('ticket.profession', 'ticketProfession')
            ->andWhere('ticket.user = :user')
            ->andWhere('ticketProfession.categorie = :categorie')
            ->andWhere('ticket.isActive = true')
            ->andWhere('ticket.remainingContacts > 0')
            ->setParameter('user', $user)
            ->setParameter('categorie', $categorie)
            ->orderBy('ticket.createdAt', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function countRemaining(User $user, ?Profession $profession = null): int
    {
        $qb = $this->createQueryBuilder('ticket')
            ->select('COALESCE(SUM(ticket.remainingContacts), 0)')
            ->andWhere('ticket.user = :user')
            ->andWhere('ticket.isActive = true')
            ->setParameter('user', $user);

        if ($profession?->getCategorie()) {
            $qb->innerJoin('ticket.profession', 'ticketProfession')
                ->andWhere('ticketProfession.categorie = :categorie')
                ->setParameter('categorie', $profession->getCategorie());
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }
}
