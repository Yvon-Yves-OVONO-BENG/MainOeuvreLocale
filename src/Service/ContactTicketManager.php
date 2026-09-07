<?php

namespace App\Service;

use App\Entity\ContactTicket;
use App\Entity\Profession;
use App\Entity\User;
use App\Repository\ContactTicketRepository;
use Doctrine\ORM\EntityManagerInterface;

final class ContactTicketManager
{
    public const PRICE = 200;
    public const CONTACTS_PER_TICKET = 3;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ContactTicketRepository $tickets,
    ) {}

    public function issue(User $user, Profession $profession, string $paymentId, string $paymentMethod): ContactTicket
    {
        $existing = $this->tickets->findOneBy(['paymentId' => $paymentId]);
        if ($existing) {
            if ($existing->getUser()?->getId() !== $user->getId()) {
                throw new \LogicException('Cette référence de paiement est déjà utilisée.');
            }
            return $existing;
        }

        $ticket = (new ContactTicket())
            ->setUser($user)
            ->setProfession($profession)
            ->setQuantity(self::CONTACTS_PER_TICKET)
            ->setRemainingContacts(self::CONTACTS_PER_TICKET)
            ->setPrice(self::PRICE)
            ->setPaymentId($paymentId)
            ->setPaymentMethod($paymentMethod);

        $this->em->persist($ticket);
        $this->em->flush();

        return $ticket;
    }

    public function consume(User $user, Profession $profession): ?ContactTicket
    {
        $ticket = $this->tickets->findAvailable($user, $profession);
        if (!$ticket) {
            return null;
        }

        $ticket->consume();
        return $ticket;
    }

    public function remaining(User $user, ?Profession $profession = null): int
    {
        return $this->tickets->countRemaining($user, $profession);
    }
}
