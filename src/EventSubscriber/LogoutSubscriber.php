<?php

namespace App\EventSubscriber;

use App\Entity\User;
use App\Repository\UserLogRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Http\Event\LogoutEvent;

class LogoutSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private RequestStack $requestStack,
        private UserLogRepository $userLogRepo
    ) {}

    public function onLogout(LogoutEvent $event)
    {
        $user = $event->getToken()?->getUser();
        if (!$user instanceof User) return;

        // Cherche la dernière connexion non déconnectée
        $log = $this->userLogRepo->findOneBy([
            'user' => $user,
            'disconnectedAt' => null
        ], ['logedAt' => 'DESC']);

        if ($log) {
            $log->setDisconnectedAt(new \DateTime());
            $log->setLastSeenAt(new \DateTimeImmutable());
            $log->setAction('logout');
            $user->setIsOnline(false);
            $user->setLastDisconnectedAt(new \DateTimeImmutable());

            $this->em->persist($user);
            $this->em->persist($log);

            $this->em->flush();
        }
    }

        public static function getSubscribedEvents(): array
        {
            return [
                LogoutEvent::class => 'onLogout',
            ];
        }
    }