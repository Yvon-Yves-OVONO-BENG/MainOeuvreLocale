<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\UserActivityLog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

class UserActivityLogger
{
    public function __construct(private EntityManagerInterface $em) {}

    public function log(User $user, string $event, ?Request $request = null, array $meta = []): void
    {
        $l = new UserActivityLog();
        $l->setUser($user)->setEvent($event)->setMeta($meta);

        if ($request) {
            $l->setIp($request->getClientIp());
            $l->setUserAgent(substr((string)$request->headers->get('User-Agent'), 0, 2000));
        }

        $this->em->persist($l);
        $this->em->flush();
    }
}