<?php

namespace App\Controller\Web\Chat1;

use App\Entity\Conversation;
use App\Entity\Message;
use App\Repository\MessageRepository;
use App\Security\ConversationAccess;
use App\Security\CurrentUser;
use App\Service\ChatBlockService;
use App\Util\ChatTimestamp;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/chat/conversation/{id}/send', name: 'chat_send', methods: ['POST'])]
final class SendMessageController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private CurrentUser $currentUser,
        private ConversationAccess $access,
        private MessageRepository $messageRepository,
        private ChatBlockService $chatBlockService,
    ) {
    }

    public function __invoke(Conversation $conversation, Request $request): JsonResponse
    {
        $me = $this->currentUser->requireUser($this);

        $this->access->assertParticipant($conversation, $me);

        $payload = json_decode((string) $request->getContent(), true) ?: [];
        $content = trim((string) ($payload['content'] ?? ''));
        $replyToId = (int) ($payload['replyToId'] ?? 0);

        if ($content === '') {
            return $this->json([
                'ok' => false,
                'error' => 'Contenu vide.',
            ], 400);
        }

        $senderIdentifier = $this->getUserIdentifierValue($me);
        $receiverIdentifier = $this->getReceiverIdentifier($conversation, $me);

        if (
            $senderIdentifier !== null
            && $receiverIdentifier !== null
            && !$this->chatBlockService->canReceiveText($receiverIdentifier, $senderIdentifier, $content)
        ) {
            return $this->json([
                'ok' => false,
                'message' => 'Ce message est bloqué par les réglages de sécurité.',
            ], 403);
        }

        $message = (new Message())
            ->setConversation($conversation)
            ->setSender($me)
            ->setType(Message::TYPE_TEXT)
            ->setContent($content)
            ->setMeta([
                'clientSentAt' => ChatTimestamp::sanitizeClientSentAt($payload['clientSentAt'] ?? null),
            ]);

        if ($replyToId > 0) {
            $replyTo = $this->messageRepository->find($replyToId);

            if ($replyTo instanceof Message && $replyTo->getConversation()?->getId() === $conversation->getId()) {
                $message->setReplyTo($replyTo);
            }
        }

        $this->em->persist($message);
        $conversation->addMessage($message);
        $this->em->flush();

        return $this->json([
            'ok' => true,
            'messageId' => $message->getId(),
            'replyTo' => $this->normalizeReplyTo($message->getReplyTo()),
        ]);
    }

    private function getReceiverIdentifier(Conversation $conversation, object $me): ?string
    {
        $participantA = $conversation->getParticipantA();
        $participantB = $conversation->getParticipantB();

        if ($participantA && method_exists($participantA, 'getId') && method_exists($me, 'getId')) {
            if ($participantA->getId() !== $me->getId()) {
                return $this->getUserIdentifierValue($participantA);
            }
        }

        if ($participantB && method_exists($participantB, 'getId') && method_exists($me, 'getId')) {
            if ($participantB->getId() !== $me->getId()) {
                return $this->getUserIdentifierValue($participantB);
            }
        }

        return null;
    }

    private function getUserIdentifierValue(object $user): ?string
    {
        if (method_exists($user, 'getUserIdentifier')) {
            return (string) $user->getUserIdentifier();
        }

        if (method_exists($user, 'getEmail')) {
            return (string) $user->getEmail();
        }

        return null;
    }

    private function normalizeReplyTo(?Message $replyTo): ?array
    {
        if (!$replyTo instanceof Message) {
            return null;
        }

        if ($replyTo->getDeleted()) {
            return [
                'id' => $replyTo->getId(),
                'authorName' => 'Message supprimé',
                'content' => 'Ce message n’est plus disponible.',
            ];
        }

        $authorName = $replyTo->getSender()?->getPersonalProfile()?->getFullName()
            ?? $replyTo->getSender()?->getEmail()
            ?? 'Utilisateur';

        $preview = match ($replyTo->getType()) {
            Message::TYPE_FILE => $replyTo->getContent() ?: 'Fichier',
            'voice' => 'Message vocal',
            Message::TYPE_CALL => $replyTo->getContent() ?: 'Appel',
            default => $replyTo->getContent() ?: '',
        };

        return [
            'id' => $replyTo->getId(),
            'authorName' => $authorName,
            'content' => $preview,
        ];
    }
}
