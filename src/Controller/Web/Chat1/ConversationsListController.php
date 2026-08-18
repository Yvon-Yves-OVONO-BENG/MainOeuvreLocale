<?php

namespace App\Controller\Web\Chat1;

use App\Entity\Conversation;
use App\Entity\User;
use App\Repository\ConversationRepository;
use App\Repository\MessageRepository;
use App\Security\CurrentUser;
use App\Util\ChatTimestamp;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/chat/conversations', name: 'chat_conversations', methods: ['GET'])]
final class ConversationsListController extends AbstractController
{
    public function __construct(
        private ConversationRepository $conversationRepository,
        private MessageRepository $messageRepository,
        private CurrentUser $currentUser,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $me = $this->currentUser->requireUser($this);
        $limit = max(1, min(100, (int) $request->query->get('limit', 50)));

        $convs = $this->conversationRepository->findForUser($me, $limit);
        $basePath = $request->getBasePath();

        $items = [];
        
        foreach ($convs as $c) {
            if (!$c instanceof Conversation) {
                continue;
            }
            
            $a = $c->getParticipantA();
            $b = $c->getParticipantB();

            if (!$a instanceof User || !$b instanceof User) {
                continue;
            }

            if ($a->getId() === $me->getId()) {
                $other = $b;
            } elseif ($b->getId() === $me->getId()) {
                $other = $a;
            } else {
                continue;
            }

            $name = $other->getPersonalProfile()?->getFullName()
                ?? $other->getEmail()
                ?? 'Utilisateur';

            $avatar = $other->getPersonalProfile()?->getPhoto();

            $lastMessage = $this->messageRepository->findLastMessageForConversation($c);
            $unread = $this->messageRepository->countUnreadForConversation($c, $me);

            $presence = $other->getPresenceStatus();

            $items[] = [
                'id' => $c->getId(),
                'userId' => $other->getId(),
                'name' => $name,
                'avatarUrl' => $avatar
                    ? $basePath . '/uploads/profiles/' . $avatar
                    : $basePath . '/uploads/profiles/avatar.png',
                'lastMessageAt' => $c->getLastMessageAt()
                    ? ChatTimestamp::iso($c->getLastMessageAt())
                    : null,
                'lastMessage' => $lastMessage?->getContent(),
                'unreadCount' => $unread,
                'online' => $presence === 'online',
                'presence' => $presence,
                'lastSeenAt' => $other->getLastSeenAt()
                    ? ChatTimestamp::iso($other->getLastSeenAt())
                    : null,
                'lastDisconnectedAt' => $other->getLastDisconnectedAt()
                    ? ChatTimestamp::iso($other->getLastDisconnectedAt())
                    : null,
            ];
        }
        

        $response = $this->json([
            'ok' => true,
            'count' => count($items),
            'items' => $items,
        ]);

        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0, private');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        return $response;
    }
}
