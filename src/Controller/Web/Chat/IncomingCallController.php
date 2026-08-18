<?php

namespace App\Controller\Web\Chat;

use App\Entity\CallSession;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class IncomingCallController extends AbstractController
{
    #[Route('/chat/calls/incoming', name: 'chat_call_incoming', methods: ['GET'])]
    public function __invoke(EntityManagerInterface $em): JsonResponse
    {
        /** @var User|null $me */
        $me = $this->getUser();

        if (!$me) {
            return $this->json([
                'ok' => false,
                'incoming' => null,
                'message' => 'Utilisateur non connecté.',
            ], 401);
        }

        $now = new \DateTimeImmutable();

        /*
        * Tous les appels ringing plus vieux que 45 secondes deviennent missed.
        * Comme ça, une vieille session ne relance plus "Incoming" au refresh.
        */
        $expiredBefore = $now->modify('-45 seconds');

        $expiredCalls = $em->getRepository(CallSession::class)
            ->createQueryBuilder('c')
            ->andWhere('c.callee = :me')
            ->andWhere('c.status = :ringing')
            ->andWhere('c.startedAt < :expiredBefore')
            ->setParameter('me', $me)
            ->setParameter('ringing', CallSession::STATUS_RINGING)
            ->setParameter('expiredBefore', $expiredBefore)
            ->getQuery()
            ->getResult();

        foreach ($expiredCalls as $expiredCall) {
            if ($expiredCall instanceof CallSession) {
                $expiredCall->setStatus(CallSession::STATUS_MISSED);
                $expiredCall->setEndedAt($now);
            }
        }

        if ($expiredCalls !== []) {
            $em->flush();
        }

        /*
        * On ne retourne que les appels entrants encore récents.
        */
        $recentAfter = $now->modify('-45 seconds');

        $call = $em->getRepository(CallSession::class)
            ->createQueryBuilder('c')
            ->leftJoin('c.conversation', 'conversation')
            ->leftJoin('c.caller', 'caller')
            ->leftJoin('c.callee', 'callee')
            ->addSelect('conversation', 'caller', 'callee')
            ->andWhere('c.callee = :me')
            ->andWhere('c.caller != :me')
            ->andWhere('c.status = :status')
            ->andWhere('c.startedAt >= :recentAfter')
            ->setParameter('me', $me)
            ->setParameter('status', CallSession::STATUS_RINGING)
            ->setParameter('recentAfter', $recentAfter)
            ->orderBy('c.startedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$call instanceof CallSession) {
            return $this->json([
                'ok' => true,
                'incoming' => null,
            ]);
        }

        $caller = $call->getCaller();

        return $this->json([
            'ok' => true,
            'incoming' => [
                'id' => $call->getId(),
                'callId' => $call->getId(),
                'type' => $call->getType(),
                'status' => $call->getStatus(),
                'conversationId' => $call->getConversation()?->getId(),
                'callerId' => $caller?->getId(),
                'callerName' => $caller ? $this->displayUserName($caller) : 'Contact',
                'callerAvatar' => $this->displayUserAvatar($caller),
                'startedAt' => $call->getStartedAt()->format(DATE_ATOM),
            ],
        ]);
    }

    private function displayUserName(?User $user): string
    {
        if (!$user) {
            return 'Contact';
        }

        if (method_exists($user, 'getFullName') && $user->getFullName()) {
            return $user->getFullName();
        }

        if (method_exists($user, 'getName') && $user->getName()) {
            return $user->getName();
        }

        if (method_exists($user, 'getUsername') && $user->getUsername()) {
            return $user->getUsername();
        }

        if (method_exists($user, 'getFirstName') && method_exists($user, 'getLastName')) {
            $name = trim((string) $user->getFirstName() . ' ' . (string) $user->getLastName());

            if ($name !== '') {
                return $name;
            }
        }

        if (method_exists($user, 'getEmail') && $user->getEmail()) {
            return $user->getEmail();
        }

        if (method_exists($user, 'getUserIdentifier') && $user->getUserIdentifier()) {
            return $user->getUserIdentifier();
        }

        return 'Utilisateur #' . $user->getId();
    }

    private function displayUserAvatar(?User $user): string
    {
        if (!$user) {
            return '/uploads/profiles/avatar.png';
        }

        if (
            method_exists($user, 'getPersonalProfile')
            && $user->getPersonalProfile()
            && method_exists($user->getPersonalProfile(), 'getPhoto')
            && $user->getPersonalProfile()->getPhoto()
        ) {
            return '/uploads/profiles/' . $user->getPersonalProfile()->getPhoto();
        }

        if (
            method_exists($user, 'getPhoto')
            && $user->getPhoto()
        ) {
            return '/uploads/profiles/' . $user->getPhoto();
        }

        if (
            method_exists($user, 'getAvatar')
            && $user->getAvatar()
        ) {
            return '/uploads/profiles/' . $user->getAvatar();
        }

        return '/uploads/profiles/avatar.png';
    }
}