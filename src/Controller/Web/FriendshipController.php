<?php

namespace App\Controller\Web;

use App\Entity\User;
use App\Entity\Message;
use App\Entity\Friendship;
use App\Entity\Conversation;
use App\Repository\FriendshipRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

#[Route('/friendship', name: 'friendship_')]
class FriendshipController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private FriendshipRepository $friendshipRepository
    ) {}

    private function requireUser(): User
    {
        $me = $this->getUser();
        if (!$me instanceof User) {
            throw new AccessDeniedException();
        }
        return $me;
    }

    // =========================================================
    // ✅ ÉTAT RELATION (clé UX)
    // =========================================================
    #[Route('/state/{userId}', name: 'state', methods: ['GET'])]
    public function state(int $userId): JsonResponse
    {
        $me = $this->requireUser();
        $other = $this->em->getRepository(User::class)->find($userId);

        if (!$other) {
            return $this->json(['state' => 'none']);
        }

        $f = $this->friendshipRepository->findBetweenUsers($me, $other);

        if (!$f) return $this->json(['state' => 'none', 'friendshipId' => null]);

        if ($f->isBlocked()) {
            return $this->json(['state' => 'blocked', 'friendshipId' => $f->getId()]);
        }

        if ($f->isAccepted()) {
            return $this->json(['state'=>'friends', 'friendshipId'=>$f->getId()]);
        }


        if ($f->isPending()) {
            if ($f->getRequester() === $me) {
                return $this->json(['state' => 'pending_sent', 'friendshipId' => $f->getId()]);
            }
            return $this->json(['state' => 'pending_received', 'friendshipId' => $f->getId()]);
        }

        return $this->json(['state' => 'none']);
    }

    // =========================================================
    // ✅ ENVOYER DEMANDE
    // =========================================================
    #[Route('/request/{userId<\d+>}', name: 'request', methods: ['POST'])]
    public function request(int $userId): JsonResponse
    {
        $me = $this->requireUser();
        $other = $this->em->getRepository(User::class)->find($userId);

        if (!$other || $other === $me) {
            return $this->json(['ok' => false], 400);
        }

        $existing = $this->friendshipRepository->findBetweenUsers($me, $other);

        if ($existing) {
            return $this->json(['ok' => true, 'state' => 'exists']);
        }

        $f = (new Friendship())
            ->setRequester($me)
            ->setAddressee($other)
            ->setStatus(Friendship::STATUS_PENDING);

        $this->em->persist($f);
        $this->em->flush();

        return $this->json(['ok' => true]);
    }

    // =========================================================
    // ✅ ANNULER DEMANDE
    // =========================================================
    #[Route('/cancel/{userId<\d+>}', name: 'cancel', methods: ['POST'])]
    public function cancel(int $userId): JsonResponse
    {
        $me = $this->requireUser();
        $other = $this->em->getRepository(User::class)->find($userId);

        $f = $this->friendshipRepository->findBetweenUsers($me, $other);

        if ($f && $f->getRequester() === $me && $f->isPending()) {
            $this->em->remove($f);
            $this->em->flush();
        }

        return $this->json(['ok' => true]);
    }

    // =========================================================
    // ✅ ACCEPTER DEMANDE
    // =========================================================
    #[Route('/accept/{id<\d+>}', name: 'accept', methods: ['POST'])]
    public function accept(Friendship $friendship): JsonResponse
    {
        $me = $this->requireUser();

        if ($friendship->getAddressee() !== $me) {
            throw new AccessDeniedException();
        }

        $friendship->setStatus(Friendship::STATUS_ACCEPTED);

        // ✅ créer conversation
        $conv = (new Conversation())
            ->setParticipantA($friendship->getRequester())
            ->setParticipantB($friendship->getAddressee());

        $this->em->persist($conv);

        // ✅ message auto
        $msg = (new Message())
            ->setConversation($conv)
            ->setSender($me)
            ->setContent("Bonjour 👋, j'accepte votre amitié.");

        $this->em->persist($msg);
        $conv->addMessage($msg);

        $this->em->flush();

        return $this->json(['ok' => true]);
    }

    // =========================================================
    // ✅ REFUSER DEMANDE
    // =========================================================
    #[Route('/reject/{id<\d+>}', name: 'reject', methods: ['POST'])]
    public function reject(Friendship $friendship): JsonResponse
    {
        $me = $this->requireUser();

        if ($friendship->getAddressee() !== $me) {
            throw new AccessDeniedException();
        }

        $this->em->remove($friendship);
        $this->em->flush();

        return $this->json(['ok' => true]);
    }

    // =========================================================
    // ✅ BLOQUER USER
    // =========================================================
    #[Route('/block/{userId<\d+>}', name: 'block', methods: ['POST'])]
    public function block(int $userId): JsonResponse
    {
        $me = $this->requireUser();
        $other = $this->em->getRepository(User::class)->find($userId);

        if (!$other) return $this->json(['ok' => false], 400);

        $f = $this->friendshipRepository->findBetweenUsers($me, $other);

        if (!$f) {
            $f = (new Friendship())
                ->setRequester($me)
                ->setAddressee($other);
            $this->em->persist($f);
        }

        $f->setStatus(Friendship::STATUS_BLOCKED);
        $f->setBlockedBy($me);

        $this->em->flush();

        return $this->json(['ok' => true]);
    }

    #[Route('/pending/list', name: 'pending_list', methods: ['GET'])]
    public function pendingList(FriendshipRepository $repo): JsonResponse
    {
        /** @var User $me */
        $me = $this->getUser();
        if (!$me) return $this->json(['ok'=>false], 401);

        $rows = $repo->findIncomingPending($me);

        $data = array_map(function($f){
            $u = $f->getRequester();

            return [
                'id' => $f->getId(),
                'userId' => $u->getId(),
                'name' => $u->getFullName() ?? $u->getEmail(),
                'email' => $u->getEmail(),
                'avatar' => $u->getPersonalProfile()?->getPhoto(),
            ];
        }, $rows);

        return $this->json(['ok'=>true,'items'=>$data]);
    }


    #[Route('/friends/list', name: 'friends_list', methods: ['GET'])]
    public function friendsList(FriendshipRepository $repo): JsonResponse
    {
        /** @var User $me */
        $me = $this->getUser();
        if (!$me) return $this->json(['ok'=>false], 401);

        $friends = $repo->findFriendsUsersFlat($me);

        $data = array_map(function($u){
            return [
                'userId' => $u->getId(),
                'name' => $u->getFullName() ?? $u->getEmail(),
                'email' => $u->getEmail(),
                'avatar' => $u->getPersonalProfile()?->getPhoto(),
            ];
        }, $friends);

        return $this->json(['ok'=>true,'items'=>$data]);
    }


    #[Route('/network', name: 'network', methods: ['GET'])]
    public function network(FriendshipRepository $repo): Response
    {
        /** @var User $me */
        $me = $this->getUser();
        if (!$me) {
            throw $this->createAccessDeniedException();
        }
        
        return $this->render('friendship/network.html.twig', [
            'friends' => $repo->findFriendsUsersFlat($me),
            'pendingRequests' => $repo->findIncomingPending($me),
        ]);
    }


}
