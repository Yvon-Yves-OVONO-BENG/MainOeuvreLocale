<?php

namespace App\Repository;

use App\Entity\User;
use App\Entity\UserSetting;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class UserSettingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserSetting::class);
    }

    public function getOrCreate(User $user): UserSetting
    {
        $s = $this->findOneBy(['user' => $user]);
        if ($s) return $s;

        $s = new UserSetting();
        $s->setUser($user);

        $em = $this->getEntityManager();
        $em->persist($s);
        $em->flush();

        return $s;
    }
}