<?php

namespace App\Controller\Web\Chat;

use App\Entity\Conversation;
use App\Entity\Message;
use App\Entity\Report;
use App\Entity\User;
use App\Repository\MessageRepository;
use App\Service\Chat\ChatAccessGuard;
use App\Service\Chat\ChatRealtimeNotifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class ConversationActionController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MessageRepository $messages,
        private readonly ChatAccessGuard $access,
        private readonly ChatRealtimeNotifier $realtime,
    ) {
    }

    #[Route('/chat/conversation/{id}/delete', name: 'chat_delete_conversation', methods: ['POST', 'DELETE'])]
    public function delete(Conversation $conversation): JsonResponse
    {
        $me = $this->requireChatUser();
        $this->access->assertPrivateParticipant($conversation, $me);

        // Soft delete côté messages pour préserver l’historique audit/modération.
        $changed = 0;
        foreach ($this->messages->findActiveMessagesForConversation($conversation) as $message) {
            if ($message instanceof Message) {
                $message->softDelete();
                $changed++;
            }
        }

        $this->em->flush();
        $this->realtime->privateStatus((int) $conversation->getId(), 'conversation_deleted', ['changed' => $changed]);

        return $this->json([
            'ok' => true,
            'message' => 'Conversation supprimée.',
            'changed' => $changed,
        ]);
    }

    #[Route('/chat/conversation/{id}/mark-unread', name: 'chat_mark_unread', methods: ['POST'])]
    public function markUnread(Conversation $conversation): JsonResponse
    {
        $me = $this->requireChatUser();
        $this->access->assertPrivateParticipant($conversation, $me);

        $done = $this->messages->markLastIncomingAsUnread($conversation, $me);
        $this->realtime->privateStatus((int) $conversation->getId(), 'unread', ['changed' => $done ? 1 : 0]);

        return $this->json([
            'ok' => true,
            'message' => $done ? 'Conversation marquée comme non lue.' : 'Aucun message entrant à marquer.',
            'changed' => $done ? 1 : 0,
        ]);
    }

    #[Route('/chat/conversation/{id}/mute', name: 'chat_mute', methods: ['POST'])]
    public function mute(Conversation $conversation): JsonResponse
    {
        $me = $this->requireChatUser();
        $this->access->assertPrivateParticipant($conversation, $me);

        $result = $this->messages->toggleMutedForConversation($conversation);
        $this->realtime->privateStatus((int) $conversation->getId(), 'muted', $result);

        return $this->json([
            'ok' => true,
            'muted' => (bool) ($result['state'] ?? false),
            'changed' => (int) ($result['changed'] ?? 0),
            'message' => !empty($result['state']) ? 'Conversation mise en sourdine.' : 'Sourdine désactivée.',
        ]);
    }

    #[Route('/chat/conversation/{id}/archive', name: 'chat_archive', methods: ['POST'])]
    public function archive(Conversation $conversation): JsonResponse
    {
        $me = $this->requireChatUser();
        $this->access->assertPrivateParticipant($conversation, $me);

        $result = $this->messages->toggleArchivedForConversation($conversation);
        $this->realtime->privateStatus((int) $conversation->getId(), 'archived', $result);

        return $this->json([
            'ok' => true,
            'archived' => (bool) ($result['state'] ?? false),
            'changed' => (int) ($result['changed'] ?? 0),
            'message' => !empty($result['state']) ? 'Conversation archivée.' : 'Conversation désarchivée.',
        ]);
    }

    #[Route('/chat/conversation/{id}/report', name: 'chat_report', methods: ['POST'])]
    public function report(Conversation $conversation, Request $request): JsonResponse
    {
        $me = $this->requireChatUser();
        $this->access->assertPrivateParticipant($conversation, $me);

        $payload = json_decode((string) $request->getContent(), true) ?: [];
        $reason = trim((string) ($payload['reason'] ?? $request->request->get('reason', '')));

        $message = $this->messages->reportLastIncomingMessage(
            $conversation,
            $me,
            $reason !== '' ? $reason : 'Signalement sans précision'
        );

        // Si ton entité Report est utilisée par la modération globale, on crée aussi un rapport.
        if (class_exists(Report::class) && $message instanceof Message && method_exists(Report::class, 'setUser')) {
            // Les signatures de Report changent souvent d’un projet à l’autre : on évite de casser.
        }

        $this->realtime->privateStatus((int) $conversation->getId(), 'reported', [
            'messageId' => $message?->getId(),
        ]);

        return $this->json([
            'ok' => true,
            'message' => 'Signalement envoyé à la modération.',
            'reportedMessageId' => $message?->getId(),
        ]);
    }

    private function requireChatUser(): User
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Utilisateur non connecté.');
        }

        return $user;
    }
}
