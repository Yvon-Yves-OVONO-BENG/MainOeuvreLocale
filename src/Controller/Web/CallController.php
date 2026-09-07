<?php

namespace App\Controller\Web;

use App\Entity\CallSession;
use App\Entity\CallSignal;
use App\Entity\Conversation;
use App\Entity\User;
use App\Repository\CallSignalRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Appels audio/vidéo WebRTC avec signalisation 100 % AJAX.
 * Aucun Hub Mercure n'est nécessaire : l'état et les signaux sont lus en
 * polling court, compatible avec un hébergement mutualisé.
 */
#[Route('/chat', name: 'chat_call_')]
final class CallController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CallSignalRepository $signals,
    ) {
    }

    #[Route('/conversation/{id}/call/start', name: 'start', methods: ['POST'])]
    public function start(Conversation $conversation, Request $request): JsonResponse
    {
        $me = $this->requireUser();
        $this->assertConversationParticipant($conversation, $me);
        $payload = json_decode((string) $request->getContent(), true) ?: [];
        $type = ($payload['type'] ?? CallSession::TYPE_AUDIO) === CallSession::TYPE_VIDEO
            ? CallSession::TYPE_VIDEO : CallSession::TYPE_AUDIO;
        $callee = $conversation->getOtherParticipant($me);

        if (!$callee instanceof User) {
            return $this->json(['ok' => false, 'error' => 'Destinataire introuvable.'], 400);
        }

        // Empêche plusieurs sonneries actives dans la même conversation.
        $active = $this->em->getRepository(CallSession::class)->createQueryBuilder('c')
            ->andWhere('c.conversation = :conversation')
            ->andWhere('c.status IN (:statuses)')
            ->setParameter('conversation', $conversation)
            ->setParameter('statuses', [CallSession::STATUS_RINGING, CallSession::STATUS_ACCEPTED])
            ->orderBy('c.id', 'DESC')->setMaxResults(1)->getQuery()->getOneOrNullResult();

        if ($active instanceof CallSession) {
            $isAccepted = $active->getStatus() === CallSession::STATUS_ACCEPTED;
            $isRecentRinging = $active->getStatus() === CallSession::STATUS_RINGING
                && $active->getStartedAt() > (new \DateTimeImmutable())->modify('-90 seconds');

            if ($isAccepted || $isRecentRinging) {
                return $this->json([
                    'ok' => false,
                    'error' => 'Un appel est déjà en cours dans cette conversation.',
                    'callId' => $active->getId(),
                ], 409);
            }

            // Seule une sonnerie abandonnée peut être requalifiée comme manquée.
            $active->setStatus(CallSession::STATUS_MISSED)->setEndedAt(new \DateTimeImmutable());
        }

        $call = (new CallSession())
            ->setConversation($conversation)
            ->setCaller($me)
            ->setCallee($callee)
            ->setType($type)
            ->setStatus(CallSession::STATUS_RINGING);

        $this->em->persist($call);
        $this->em->flush();

        return $this->json([
            'ok' => true,
            'callId' => $call->getId(),
            'conversationId' => $conversation->getId(),
            'type' => $call->getType(),
            'status' => $call->getStatus(),
        ], 201);
    }

    #[Route('/call/{id}/state', name: 'state', methods: ['GET'])]
    public function state(CallSession $call): JsonResponse
    {
        $me = $this->requireUser();
        $this->assertCallParticipant($call, $me);

        return $this->json([
            'ok' => true,
            'callId' => $call->getId(),
            'status' => $call->getStatus(),
            'type' => $call->getType(),
            'answeredAt' => $call->getAnsweredAt()?->format(DATE_ATOM),
            'endedAt' => $call->getEndedAt()?->format(DATE_ATOM),
        ]);
    }

    #[Route('/call/{id}/accept', name: 'accept', methods: ['POST'])]
    public function accept(CallSession $call): JsonResponse
    {
        $me = $this->requireUser();
        $this->assertCallParticipant($call, $me);
        if ($call->getCallee()?->getId() !== $me->getId()) {
            return $this->json(['ok' => false, 'error' => 'Seul le destinataire peut accepter.'], 403);
        }
        if ($call->getStatus() !== CallSession::STATUS_RINGING) {
            return $this->json(['ok' => false, 'error' => 'Cet appel n’est plus disponible.'], 409);
        }
        $call->accept();
        $this->em->flush();
        return $this->json(['ok' => true, 'callId' => $call->getId(), 'status' => $call->getStatus()]);
    }

    #[Route('/call/{id}/decline', name: 'decline', methods: ['POST'])]
    public function decline(CallSession $call): JsonResponse
    {
        $me = $this->requireUser();
        $this->assertCallParticipant($call, $me);
        if (!in_array($call->getStatus(), [CallSession::STATUS_RINGING, CallSession::STATUS_ACCEPTED], true)) {
            return $this->json(['ok' => true, 'callId' => $call->getId(), 'status' => $call->getStatus()]);
        }
        $call->decline();
        $this->em->flush();
        return $this->json(['ok' => true, 'callId' => $call->getId(), 'status' => $call->getStatus()]);
    }

    #[Route('/call/{id}/signal', name: 'signal', methods: ['POST'])]
    public function signal(CallSession $call, Request $request): JsonResponse
    {
        $me = $this->requireUser();
        $this->assertCallParticipant($call, $me);
        $payload = json_decode((string) $request->getContent(), true) ?: [];
        $type = (string) ($payload['type'] ?? '');
        if (!in_array($type, ['offer', 'answer', 'candidate'], true)) {
            return $this->json(['ok' => false, 'error' => 'Signal invalide.'], 400);
        }
        if (in_array($call->getStatus(), [CallSession::STATUS_DECLINED, CallSession::STATUS_ENDED, CallSession::STATUS_MISSED], true)) {
            return $this->json(['ok' => false, 'error' => 'L’appel est terminé.'], 409);
        }

        $signal = (new CallSignal())
            ->setCallSession($call)
            ->setSender($me)
            ->setType($type)
            ->setPayload($payload['data'] ?? null);
        $this->em->persist($signal);
        $this->em->flush();

        return $this->json(['ok' => true, 'signalId' => $signal->getId()]);
    }

    #[Route('/call/{id}/signals', name: 'signals', methods: ['GET'])]
    public function signals(CallSession $call, Request $request): JsonResponse
    {
        $me = $this->requireUser();
        $this->assertCallParticipant($call, $me);
        $after = max(0, (int) $request->query->get('after', 0));
        $items = $this->signals->findIncomingAfter($call, $me, $after);

        return $this->json([
            'ok' => true,
            'items' => array_map(static fn (CallSignal $signal): array => [
                'id' => $signal->getId(),
                'type' => $signal->getType(),
                'data' => $signal->getPayload(),
                'createdAt' => $signal->getCreatedAt()->format(DATE_ATOM),
            ], $items),
            'lastId' => $items ? (int) end($items)->getId() : $after,
        ]);
    }

    #[Route('/call/{id}/end', name: 'end', methods: ['POST'])]
    public function end(CallSession $call): JsonResponse
    {
        $me = $this->requireUser();
        $this->assertCallParticipant($call, $me);
        if (!in_array($call->getStatus(), [CallSession::STATUS_ENDED, CallSession::STATUS_DECLINED, CallSession::STATUS_MISSED], true)) {
            $call->end();
            $this->em->flush();
        }
        return $this->json(['ok' => true, 'callId' => $call->getId(), 'status' => $call->getStatus()]);
    }

    private function requireUser(): User
    {
        $me = $this->getUser();
        if (!$me instanceof User) throw new AccessDeniedException();
        return $me;
    }

    private function assertConversationParticipant(Conversation $conversation, User $me): void
    {
        if ($conversation->getParticipantA()?->getId() !== $me->getId() && $conversation->getParticipantB()?->getId() !== $me->getId()) {
            throw new AccessDeniedException();
        }
    }

    private function assertCallParticipant(CallSession $call, User $me): void
    {
        if (!$call->isParticipant($me)) throw new AccessDeniedException();
    }
}
