<?php

namespace App\Controller\Web\Chat1;

use App\Entity\Conversation;
use App\Entity\Message;
use App\Entity\User;
use App\Repository\MessageReactionRepository;
use App\Repository\MessageRepository;
use App\Security\ConversationAccess;
use App\Security\CurrentUser;
use App\Util\ChatTimestamp;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/chat/conversation/{id}/messages', name: 'chat_messages', methods: ['GET'])]
final class GetMessagesController extends AbstractController
{
    private const DEFAULT_AVATAR = '/uploads/profiles/avatar.png';

    public function __construct(
        private MessageRepository $messageRepository,
        private MessageReactionRepository $messageReactionRepository,
        private CurrentUser $currentUser,
        private ConversationAccess $access,
    ) {
    }

    public function __invoke(Conversation $conversation, Request $request): JsonResponse
    {
        $me = $this->currentUser->requireUser($this);

        $this->access->assertParticipant($conversation, $me);

        $changed = $this->messageRepository->markIncomingAsRead($conversation, $me);
        $limit = max(1, min(200, (int) $request->query->get('limit', 50)));
        $messages = $this->messageRepository->findForConversation($conversation, $limit);

        return $this->json([
            'ok' => true,
            'items' => array_map(
                fn (Message $message) => $this->normalizeMessage($message, $me, $request),
                $messages
            ),
            'readChanged' => $changed,
            'lastMessageId' => $messages ? (int) end($messages)->getId() : 0,
            'serverTime' => (new \DateTimeImmutable())->format(DATE_ATOM),
        ]);
    }

    private function normalizeMessage(Message $message, User $me, Request $request): array
    {
        $sender = $message->getSender();
        $meta = $message->getMeta() ?? [];

        return [
            'id' => $message->getId(),
            'mine' => $message->isMine($me),
            'authorName' => $this->authorName($sender),
            'content' => $message->getContent(),
            'type' => $message->getType(),
            'createdAt' => ChatTimestamp::clock($message->getCreatedAt(), $meta),
            'createdAtIso' => ChatTimestamp::iso($message->getCreatedAt(), $meta),
            'status' => $message->getStatus(),
            'deliveredAt' => $message->getDeliveredAt()?->format('H:i'),
            'readAt' => $message->getReadAt()?->format('H:i'),
            'deleted' => $message->getDeleted(),

            // Important : cette URL ne contient jamais /public
            // 'avatarUrl' => $this->avatarUrl($request, $sender),
            'avatarUrl' => $this->getRawProfilePhoto($sender),

            'attachmentUrl' => $this->buildAttachmentUrl($request, $message),
            'attachmentName' => $this->buildAttachmentName($message),
            'meta' => $meta,
            'replyTo' => $this->normalizeReplyTo($message->getReplyTo(), $request),
            'reactions' => $this->normalizeReactions($message, $me),
        ];
    }

    private function getRawProfilePhoto(?User $user): ?string
{
    if (!$user instanceof User) {
        return null;
    }

    $profile = $user->getPersonalProfile();

    if (!$profile) {
        return null;
    }

    $photo = $profile->getPhoto();

    if (!is_string($photo) || trim($photo) === '') {
        return null;
    }

    return trim($photo);
}

    private function authorName(?User $user): string
    {
        if (!$user instanceof User) {
            return 'Utilisateur';
        }

        $profile = $user->getPersonalProfile();

        return $profile?->getDisplayIdentity()
            ?: $profile?->getFullName()
            ?: $user->getEmail()
            ?: 'Utilisateur';
    }

    private function avatarUrl(Request $request, ?User $user): string
    {
        if (!$user instanceof User) {
            return $this->absolutePublicUrl($request, self::DEFAULT_AVATAR);
        }

        $photo = $user->getPersonalProfile()?->getPhoto();

        if (!is_string($photo) || trim($photo) === '') {
            return $this->absolutePublicUrl($request, self::DEFAULT_AVATAR);
        }

        $photo = trim(str_replace('\\', '/', $photo));

        if (preg_match('#^https?://#i', $photo) || str_starts_with($photo, 'data:image/')) {
            return $photo;
        }

        // Nettoyage complet des chemins cassés
        $photo = preg_replace('#^https?://[^/]+#i', '', $photo);
        $photo = preg_replace('#^/maindoeuvrelocale/public/#', '/', $photo);
        $photo = preg_replace('#^maindoeuvrelocale/public/#', '', $photo);
        $photo = preg_replace('#^/maindoeuvrelocale/#', '/', $photo);
        $photo = preg_replace('#^maindoeuvrelocale/#', '', $photo);
        $photo = preg_replace('#^/public/#', '/', $photo);
        $photo = preg_replace('#^public/#', '', $photo);
        $photo = str_replace('/public/uploads/', '/uploads/', $photo);

        if (str_starts_with($photo, '/uploads/')) {
            return $this->absolutePublicUrl($request, $this->encodePublicPath($photo));
        }

        if (str_starts_with($photo, 'uploads/')) {
            return $this->absolutePublicUrl($request, $this->encodePublicPath('/' . $photo));
        }

        if (str_starts_with($photo, '/profiles/')) {
            return $this->absolutePublicUrl($request, $this->encodePublicPath('/uploads' . $photo));
        }

        if (str_starts_with($photo, 'profiles/')) {
            return $this->absolutePublicUrl($request, $this->encodePublicPath('/uploads/' . $photo));
        }

        return $this->absolutePublicUrl(
            $request,
            '/uploads/profiles/' . $this->encodePublicPath($photo, false)
        );
    }

    private function normalizeReplyTo(?Message $replyTo, Request $request): ?array
    {
        if (!$replyTo instanceof Message) {
            return null;
        }

        if ($replyTo->getDeleted()) {
            return [
                'id' => $replyTo->getId(),
                'authorName' => 'Message supprimé',
                'content' => 'Ce message n’est plus disponible.',
                'avatarUrl' => $this->absolutePublicUrl($request, self::DEFAULT_AVATAR),
            ];
        }

        $sender = $replyTo->getSender();

        $preview = match ($replyTo->getType()) {
            Message::TYPE_FILE => $replyTo->getContent() ?: 'Fichier',
            'voice' => 'Message vocal',
            Message::TYPE_CALL => $replyTo->getContent() ?: 'Appel',
            Message::TYPE_SYSTEM => $replyTo->getContent() ?: 'Message système',
            default => $replyTo->getContent() ?: '',
        };

        return [
            'id' => $replyTo->getId(),
            'authorName' => $this->authorName($sender),
            'content' => $preview,
            'avatarUrl' => $this->avatarUrl($request, $sender),
        ];
    }

    private function normalizeReactions(Message $message, User $me): array
    {
        $grouped = $this->messageReactionRepository->getGroupedCountsForMessage($message);

        $myReactions = $this->messageReactionRepository->findBy([
            'message' => $message,
            'user' => $me,
        ]);

        $myEmojis = array_map(
            static fn ($reaction) => (string) $reaction->getEmoji(),
            $myReactions
        );

        return array_map(function (array $row) use ($myEmojis) {
            $emoji = (string) ($row['emoji'] ?? '');
            $total = (int) ($row['total'] ?? 0);

            return [
                'emoji' => $emoji,
                'total' => $total,
                'count' => $total,
                'reactedByMe' => in_array($emoji, $myEmojis, true),
            ];
        }, $grouped);
    }

    private function buildAttachmentName(Message $message): ?string
    {
        if (!$message->getAttachmentUrl()) {
            return null;
        }

        $meta = $message->getMeta() ?? [];

        return $meta['originalName']
            ?? $message->getContent()
            ?? $message->getAttachmentUrl();
    }

    private function buildAttachmentUrl(Request $request, Message $message): ?string
    {
        $storedName = $message->getAttachmentUrl();

        if (!is_string($storedName) || trim($storedName) === '') {
            return null;
        }

        $storedName = trim(str_replace('\\', '/', $storedName));

        if (preg_match('#^https?://#i', $storedName)) {
            return $storedName;
        }

        $storedName = preg_replace('#^/public/#', '/', $storedName);
        $storedName = preg_replace('#^public/#', '', $storedName);
        $storedName = str_replace('/public/uploads/', '/uploads/', $storedName);

        if (str_starts_with($storedName, '/uploads/chat/')) {
            return $this->absolutePublicUrl($request, $this->encodePublicPath($storedName));
        }

        if (str_starts_with($storedName, 'uploads/chat/')) {
            return $this->absolutePublicUrl($request, $this->encodePublicPath('/' . $storedName));
        }

        if ($message->getType() === Message::TYPE_FILE) {
            return $this->absolutePublicUrl(
                $request,
                '/uploads/chat/files/' . $this->encodePublicPath($storedName, false)
            );
        }

        if ($message->getType() === 'voice') {
            return $this->absolutePublicUrl(
                $request,
                '/uploads/chat/voices/' . $this->encodePublicPath($storedName, false)
            );
        }

        return null;
    }

    private function absolutePublicUrl(Request $request, string $path): string
    {
        $path = trim(str_replace('\\', '/', $path));

        if ($path === '') {
            $path = self::DEFAULT_AVATAR;
        }

        if (preg_match('#^https?://#i', $path) || str_starts_with($path, 'data:image/')) {
            return $path;
        }

        $path = '/' . ltrim($path, '/');

        // Sécurité : jamais /public dans l’URL finale
        $path = preg_replace('#^/maindoeuvrelocale/public/#', '/', $path);
        $path = preg_replace('#^/maindoeuvrelocale/#', '/', $path);
        $path = preg_replace('#^/public/#', '/', $path);
        $path = str_replace('/public/uploads/', '/uploads/', $path);

        return $request->getSchemeAndHttpHost() . $path;
    }

    private function encodePublicPath(string $path, bool $keepLeadingSlash = true): string
    {
        $path = trim(str_replace('\\', '/', $path));

        if ($path === '') {
            return '';
        }

        $hasLeadingSlash = str_starts_with($path, '/');

        $parts = array_filter(
            explode('/', ltrim($path, '/')),
            static fn (string $part) => $part !== ''
        );

        $encoded = array_map(
            static fn (string $part) => rawurlencode(rawurldecode($part)),
            $parts
        );

        $result = implode('/', $encoded);

        if ($keepLeadingSlash && $hasLeadingSlash) {
            return '/' . $result;
        }

        return $result;
    }
}
