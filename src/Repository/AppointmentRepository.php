<?php

namespace App\Repository;

use App\Entity\Appointment;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\QueryBuilder;

class AppointmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Appointment::class);
    }

    // Calendrier du particulier (entre 2 dates)
    public function findCalendarRangeForParticular(User $particular, \DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        return $this->createQueryBuilder('a')
            ->leftJoin('a.job', 'j')->addSelect('j')
            ->leftJoin('a.talent', 't')->addSelect('t')
            ->leftJoin('a.application', 'app')->addSelect('app')
            ->andWhere('a.particular = :me')
            ->andWhere('a.startAt < :end')
            ->andWhere('a.endAt > :start')
            ->setParameter('me', $particular)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('a.startAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOneOwnedByParticular(int $id, User $particular): ?Appointment
    {
        return $this->createQueryBuilder('a')
            ->leftJoin('a.job', 'j')->addSelect('j')
            ->leftJoin('a.talent', 't')->addSelect('t')
            ->andWhere('a.id = :id')
            ->andWhere('a.particular = :me')
            ->setParameter('id', $id)
            ->setParameter('me', $particular)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findOneForTalent(int $id, User $talent): ?Appointment
    {
        return $this->createQueryBuilder('a')
            ->leftJoin('a.job', 'j')->addSelect('j')
            ->leftJoin('a.particular', 'p')->addSelect('p')
            ->andWhere('a.id = :id')
            ->andWhere('a.talent = :talent')
            ->setParameter('id', $id)
            ->setParameter('talent', $talent)
            ->getQuery()
            ->getOneOrNullResult();
    }

    // Rappels J-1 : appointments dans [now+23h, now+25h]
    public function findForReminderJ1(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->createQueryBuilder('a')
            ->leftJoin('a.job', 'j')->addSelect('j')
            ->leftJoin('a.particular', 'p')->addSelect('p')
            ->leftJoin('a.talent', 't')->addSelect('t')
            ->andWhere('a.status IN (:statuses)')
            ->andWhere('a.startAt BETWEEN :from AND :to')
            ->andWhere('a.reminderJ1SentAt IS NULL')
            ->setParameter('statuses', [Appointment::STATUS_PROPOSED, Appointment::STATUS_CONFIRMED])
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('a.startAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    // Rappels H-1 : appointments dans [now+50min, now+70min]
    public function findForReminderH1(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->createQueryBuilder('a')
            ->leftJoin('a.job', 'j')->addSelect('j')
            ->leftJoin('a.particular', 'p')->addSelect('p')
            ->leftJoin('a.talent', 't')->addSelect('t')
            ->andWhere('a.status IN (:statuses)')
            ->andWhere('a.startAt BETWEEN :from AND :to')
            ->andWhere('a.reminderH1SentAt IS NULL')
            ->setParameter('statuses', [Appointment::STATUS_PROPOSED, Appointment::STATUS_CONFIRMED])
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('a.startAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countByStatusForParticular(User $particular): array
    {
        $rows = $this->createQueryBuilder('a')
            ->select('a.status AS status, COUNT(a.id) AS total')
            ->andWhere('a.particular = :me')
            ->setParameter('me', $particular)
            ->groupBy('a.status')
            ->getQuery()
            ->getArrayResult();

        $map = [];
        foreach ($rows as $r) {
            $map[(string)$r['status']] = (int)$r['total'];
        }
        return $map;
    }


    /**
     * @return Appointment[]
     */
    public function findCalendarAppointmentsForTalent(User $user): array
    {
        return $this->createCalendarAppointmentsForTalentQueryBuilder($user)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Appointment[]
     */
    public function findCalendarAppointmentsForTalentBetween(
        User $user,
        ?\DateTimeInterface $start = null,
        ?\DateTimeInterface $end = null
    ): array {
        $qb = $this->createCalendarAppointmentsForTalentQueryBuilder($user);

        if ($start !== null) {
            $qb->andWhere('a.startAt >= :start')
               ->setParameter('start', $start);
        }

        if ($end !== null) {
            $qb->andWhere('a.startAt <= :end')
               ->setParameter('end', $end);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @return Appointment[]
     */
    public function findUpcomingCalendarAppointmentsForTalent(
        User $user,
        \DateTimeInterface $from,
        int $limit = 8
    ): array {
        return $this->createCalendarAppointmentsForTalentQueryBuilder($user)
            ->andWhere('a.startAt >= :from')
            ->setParameter('from', $from)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countCalendarStatusesForTalent(User $user): array
    {
        $result = $this->createQueryBuilder('a')
            ->select(
                'SUM(CASE WHEN a.status = :proposed THEN 1 ELSE 0 END) AS proposedCount',
                'SUM(CASE WHEN a.status = :confirmed THEN 1 ELSE 0 END) AS confirmedCount',
                'SUM(CASE WHEN a.status = :done THEN 1 ELSE 0 END) AS doneCount'
            )
            ->andWhere('a.talent = :user')
            ->setParameter('user', $user)
            ->setParameter('proposed', Appointment::STATUS_PROPOSED)
            ->setParameter('confirmed', Appointment::STATUS_CONFIRMED)
            ->setParameter('done', Appointment::STATUS_DONE)
            ->getQuery()
            ->getSingleResult();

        return [
            'proposedCount' => (int) ($result['proposedCount'] ?? 0),
            'confirmedCount' => (int) ($result['confirmedCount'] ?? 0),
            'doneCount' => (int) ($result['doneCount'] ?? 0),
        ];
    }

    private function createCalendarAppointmentsForTalentQueryBuilder(User $user): QueryBuilder
    {
        return $this->createQueryBuilder('a')
            ->leftJoin('a.job', 'j')->addSelect('j')
            ->leftJoin('a.particular', 'p')->addSelect('p')
            ->leftJoin('p.personalProfile', 'pp')->addSelect('pp')
            ->andWhere('a.talent = :user')
            ->setParameter('user', $user)
            ->orderBy('a.startAt', 'ASC');
    }
}