<?php

namespace App\EventSubscriber;

use App\Entity\User;
use App\Entity\UserLog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class UserActivitySubscriber implements EventSubscriberInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private RequestStack $requestStack,
        private TokenStorageInterface $tokenStorage,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => 'onRequest'];
    }

    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) return;

        $token = $this->tokenStorage->getToken();
        $user = $token?->getUser();

        if (!$user instanceof User) return;

        $req = $this->requestStack->getCurrentRequest();
        if (!$req) return;

        $session = $req->getSession();
        if (!$session || !$session->isStarted()) return;

        $logId = $session->get('active_userlog_id');
        if (!$logId) return;

        /** @var UserLog|null $log */
        $log = $this->em->getRepository(UserLog::class)->find($logId);
        if (!$log) return;

        // sécurité : si log déjà fermé, stop
        if ($log->getDisconnectedAt() !== null) return;

        // throttle: update max 1 fois / 60 sec
        $now = new \DateTimeImmutable();
        $last = $log->getLastSeenAt();

        if ($last instanceof \DateTimeInterface) {
            $diff = $now->getTimestamp() - $last->getTimestamp();
            if ($diff < 60) return;
        }

        $log->setLastSeenAt(new \DateTimeImmutable('now'));
        $this->em->flush();
    }
}