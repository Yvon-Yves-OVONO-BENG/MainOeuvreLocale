<?php

namespace App\Controller\Web;

use App\Entity\CallSession;
use App\Entity\Conversation;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

#[Route('/chat', name: 'chat_call_')]
class CallController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private ?HubInterface $hub = null,
        private ?LoggerInterface $logger = null,
    ) {
    }

    private function requireUser(): User
    {
        $me = $this->getUser();

        if (!$me instanceof User) {
            throw new AccessDeniedException();
        }

        return $me;
    }

    private function assertConversationParticipant(Conversation $conversation, User $me): void
    {
        if (
            $conversation->getParticipantA()?->getId() !== $me->getId()
            && $conversation->getParticipantB()?->getId() !== $me->getId()
        ) {
            throw new AccessDeniedException();
        }
    }

    private function assertCallParticipant(CallSession $call, User $me): void
    {
        if (!$call->isParticipant($me)) {
            throw new AccessDeniedException();
        }
    }

    private function publishMercure(string $topic, array $payload): void
    {
        if (!$this->hub) {
            throw new \RuntimeException('Hub Mercure non disponible.');
        }

        try {
            $this->hub->publish(
                new Update(
                    $topic,
                    json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
                )
            );
        } catch (\Throwable $e) {
            $this->logger?->error('Mercure publish failed', [
                'topic' => $topic,
                'payload' => $payload,
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException('Publication Mercure impossible : ' . $e->getMessage(), 0, $e);
        }
    }

    #[Route('/conversation/{id}/call/start', name: 'start', methods: ['POST'])]
    public function start(Conversation $conversation, Request $request): JsonResponse
    {
        $me = $this->requireUser();
        $this->assertConversationParticipant($conversation, $me);

        try {
            $payload = json_decode($request->getContent() ?: '[]', true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $payload = [];
        }

        $type = ($payload['type'] ?? CallSession::TYPE_AUDIO) === CallSession::TYPE_VIDEO
            ? CallSession::TYPE_VIDEO
            : CallSession::TYPE_AUDIO;

        $callee = $conversation->getParticipantA()?->getId() === $me->getId()
            ? $conversation->getParticipantB()
            : $conversation->getParticipantA();

        if (!$callee instanceof User) {
            return $this->json([
                'ok' => false,
                'error' => 'Destinataire introuvable',
            ], 400);
        }

        try {
            $call = (new CallSession())
                ->setConversation($conversation)
                ->setCaller($me)
                ->setCallee($callee)
                ->setType($type)
                ->setStatus(CallSession::STATUS_RINGING);

            // Si ton entité a des champs obligatoires comme createdAt, ajoute-les ici
            // ->setCreatedAt(new \DateTimeImmutable());

            $this->em->persist($call);
            $this->em->flush();
        } catch (\Throwable $e) {
            error_log('[CALL START DB ERROR] ' . $e->getMessage());

            return $this->json([
                'ok' => false,
                'error' => "Erreur lors de l'enregistrement de l'appel.",
                'details' => $e->getMessage(),
            ], 500);
        }

        $callerName = $me->getPersonalProfile()?->getFullName() ?? $me->getEmail();
        $callerAvatar = $me->getPersonalProfile()?->getPhoto()
            ? '/uploads/profiles/' . $me->getPersonalProfile()->getPhoto()
            : '/uploads/profiles/avatar.png';

        try {
            $this->mercurePublish('/chat/user/' . $callee->getId() . '/incoming-call', [
                'event' => 'incoming_call',
                'callId' => $call->getId(),
                'conversationId' => $conversation->getId(),
                'type' => $call->getType(),
                'fromUserId' => $me->getId(),
                'fromName' => $callerName,
                'fromAvatar' => $callerAvatar,
            ]);
        } catch (\Throwable $e) {
            error_log('[CALL START MERCURE ERROR] ' . $e->getMessage());

            return $this->json([
                'ok' => true,
                'warning' => "L'appel a bien été créé, mais la notification temps réel a échoué.",
                'details' => $e->getMessage(),
                'callId' => $call->getId(),
                'conversationId' => $conversation->getId(),
                'type' => $call->getType(),
                'status' => $call->getStatus(),
            ], 201);
        }

        return $this->json([
            'ok' => true,
            'callId' => $call->getId(),
            'conversationId' => $conversation->getId(),
            'type' => $call->getType(),
            'status' => $call->getStatus(),
        ], 201);
    }

    #[Route('/call/{id}/accept', name: 'accept', methods: ['POST'])]
    public function accept(CallSession $call): JsonResponse
    {
        $me = $this->requireUser();
        $this->assertCallParticipant($call, $me);

        if ($call->getCallee()?->getId() !== $me->getId()) {
            return $this->json([
                'ok' => false,
                'error' => 'Seul le destinataire peut accepter',
            ], 403);
        }

        $call->accept();
        $this->em->flush();

        $this->publishMercure('/chat/call/' . $call->getId(), [
            'event' => 'accepted',
            'callId' => $call->getId(),
            'byUserId' => $me->getId(),
        ]);

        return $this->json([
            'ok' => true,
            'callId' => $call->getId(),
            'status' => $call->getStatus(),
        ]);
    }

    #[Route('/call/{id}/decline', name: 'decline', methods: ['POST'])]
    public function decline(CallSession $call): JsonResponse
    {
        $me = $this->requireUser();
        $this->assertCallParticipant($call, $me);

        $call->decline();
        $this->em->flush();

        $this->publishMercure('/chat/call/' . $call->getId(), [
            'event' => 'declined',
            'callId' => $call->getId(),
            'byUserId' => $me->getId(),
        ]);

        return $this->json([
            'ok' => true,
            'callId' => $call->getId(),
            'status' => $call->getStatus(),
        ]);
    }

    #[Route('/call/{id}/signal', name: 'signal', methods: ['POST'])]
    public function signal(CallSession $call, Request $request): JsonResponse
    {
        $me = $this->requireUser();
        $this->assertCallParticipant($call, $me);

        $payload = json_decode((string) $request->getContent(), true) ?: [];
        $type = $payload['type'] ?? null;

        if (!in_array($type, ['offer', 'answer', 'candidate'], true)) {
            return $this->json([
                'ok' => false,
                'error' => 'Signal invalide',
            ], 400);
        }

        $this->publishMercure('/chat/call/' . $call->getId(), [
            'event' => 'signal',
            'signalType' => $type,
            'callId' => $call->getId(),
            'fromUserId' => $me->getId(),
            'data' => $payload['data'] ?? null,
        ]);

        return $this->json(['ok' => true]);
    }

    #[Route('/call/{id}/end', name: 'end', methods: ['POST'])]
    public function end(CallSession $call): JsonResponse
    {
        $me = $this->requireUser();
        $this->assertCallParticipant($call, $me);

        $call->end();
        $this->em->flush();

        $this->publishMercure('/chat/call/' . $call->getId(), [
            'event' => 'ended',
            'callId' => $call->getId(),
            'byUserId' => $me->getId(),
        ]);

        return $this->json([
            'ok' => true,
            'callId' => $call->getId(),
            'status' => $call->getStatus(),
        ]);
    }


    private function mercurePublish(string $topic, array $payload): void
    {
        if (!$this->hub) {
            throw new \RuntimeException('Hub Mercure non injecté.');
        }

        try {
            $this->hub->publish(
                new Update($topic, json_encode($payload, JSON_UNESCAPED_UNICODE))
            );
        } catch (\Throwable $e) {
            throw new \RuntimeException('Publication Mercure impossible : ' . $e->getMessage(), 0, $e);
        }
    }
}