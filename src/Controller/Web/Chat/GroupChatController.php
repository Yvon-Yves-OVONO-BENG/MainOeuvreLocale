<?php

namespace App\Controller\Web\Chat;

use App\Entity\ChatGroup;
use App\Entity\ChatGroupJoinRequest;
use App\Entity\ChatGroupMember;
use App\Entity\ChatGroupMessage;
use App\Entity\ChatGroupRecommendation;
use App\Entity\Friendship;
use App\Entity\User;
use App\Security\CurrentUser;
use App\Service\ChatGroupAccessService;
use App\Service\UploadOptimizer;
use App\Util\ChatTimestamp;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\ChatGroupMessageReaction;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/chat/groups', name: 'chat_group_')]
final class GroupChatController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private CurrentUser $currentUser,
        private ChatGroupAccessService $groupAccess,
        private SluggerInterface $slugger,
        private UploadOptimizer $uploadOptimizer,
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir,
    ) {
    }

    #[Route('/create', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $me = $this->currentUser->requireUser($this);

        $name = trim((string) $request->request->get('name'));
        $description = trim((string) $request->request->get('description'));
        $visibility = (string) $request->request->get('visibility', ChatGroup::VISIBILITY_PRIVATE);
        $invitees = trim((string) $request->request->get('invitees', ''));

        if ($name === '') {
            return $this->json([
                'ok' => false,
                'message' => 'Le nom du groupe est obligatoire.',
            ], 422);
        }

        $group = (new ChatGroup())
            ->setName($name)
            ->setDescription($description ?: null)
            ->setVisibility($visibility)
            ->setOwner($me)
            ->setSlug($this->generateGroupSlug($name))
            ->setApprovalRequired(true)
            ->setAllowFiles(true)
            ->setAllowVoice(true);

        $ownerMember = (new ChatGroupMember())
            ->setChatGroup($group)
            ->setUser($me)
            ->setRole(ChatGroupMember::ROLE_OWNER)
            ->setStatus(ChatGroupMember::STATUS_ACTIVE);

        $this->em->persist($group);
        $this->em->persist($ownerMember);

        $invited = [];

        foreach ($this->splitIdentifiers($invitees) as $identifier) {
            $user = $this->findUserByIdentifier($identifier);

            if (!$user || $user->getId() === $me->getId()) {
                continue;
            }

            $existing = $this->em->getRepository(ChatGroupMember::class)->findOneBy([
                'chatGroup' => $group,
                'user' => $user,
            ]);

            if ($existing) {
                continue;
            }

            $member = (new ChatGroupMember())
                ->setChatGroup($group)
                ->setUser($user)
                ->setRole(ChatGroupMember::ROLE_MEMBER)
                ->setStatus(ChatGroupMember::STATUS_PENDING);

            $this->em->persist($member);

            $invited[] = $this->userLabel($user);
        }

        $this->em->flush();

        return $this->json([
            'ok' => true,
            'message' => 'Groupe créé avec succès.',
            'group' => $this->normalizeGroup($group, $me),
            'invited' => $invited,
        ]);
    }

    #[Route('/{id}/request-join', name: 'request_join', methods: ['POST'])]
    public function requestJoin(ChatGroup $group, Request $request): JsonResponse
    {
        $me = $this->currentUser->requireUser($this);

        if ($this->groupAccess->getMembership($group, $me)) {
            return $this->json([
                'ok' => true,
                'message' => 'Vous êtes déjà membre de ce groupe.',
            ]);
        }

        $existing = $this->em->getRepository(ChatGroupJoinRequest::class)->findOneBy([
            'chatGroup' => $group,
            'requester' => $me,
            'status' => ChatGroupJoinRequest::STATUS_PENDING,
        ]);

        if ($existing) {
            return $this->json([
                'ok' => true,
                'message' => 'Votre demande est déjà en attente de validation.',
            ]);
        }

        $joinRequest = (new ChatGroupJoinRequest())
            ->setChatGroup($group)
            ->setRequester($me)
            ->setMessage(trim((string) $request->request->get('message')) ?: null);


        $recommendations = $this->em->getRepository(ChatGroupRecommendation::class)->findBy([
            'chatGroup' => $group,
            'recommendedTo' => $me,
            'status' => ChatGroupRecommendation::STATUS_PENDING,
        ]);

        foreach ($recommendations as $recommendation) {
            $recommendation->setStatus(ChatGroupRecommendation::STATUS_REQUESTED);
        }

        $this->em->persist($joinRequest);
        $this->em->flush();

        return $this->json([
            'ok' => true,
            'message' => 'Demande envoyée au créateur du groupe.',
        ]);
    }

    #[Route('/join-requests/{id}/approve', name: 'approve_join_request', methods: ['POST'])]
    public function approveJoinRequest(ChatGroupJoinRequest $joinRequest): JsonResponse
    {
        $me = $this->currentUser->requireUser($this);
        $group = $joinRequest->getChatGroup();

        if (!$group) {
            return $this->json(['ok' => false, 'message' => 'Groupe introuvable.'], 404);
        }

        $this->groupAccess->assertAdminOrOwner($group, $me);

        $requester = $joinRequest->getRequester();

        if (!$requester) {
            return $this->json(['ok' => false, 'message' => 'Utilisateur introuvable.'], 404);
        }

        $member = $this->em->getRepository(ChatGroupMember::class)->findOneBy([
            'chatGroup' => $group,
            'user' => $requester,
        ]);

        if (!$member) {
            $member = (new ChatGroupMember())
                ->setChatGroup($group)
                ->setUser($requester)
                ->setRole(ChatGroupMember::ROLE_MEMBER);

            $this->em->persist($member);
        }

        $member->setStatus(ChatGroupMember::STATUS_ACTIVE);

        $joinRequest
            ->setStatus(ChatGroupJoinRequest::STATUS_APPROVED)
            ->setReviewedBy($me);

        $this->em->flush();

        return $this->json([
            'ok' => true,
            'message' => 'Utilisateur intégré au groupe.',
        ]);
    }

    #[Route('/join-requests/{id}/reject', name: 'reject_join_request', methods: ['POST'])]
    public function rejectJoinRequest(ChatGroupJoinRequest $joinRequest): JsonResponse
    {
        $me = $this->currentUser->requireUser($this);
        $group = $joinRequest->getChatGroup();

        if (!$group) {
            return $this->json(['ok' => false, 'message' => 'Groupe introuvable.'], 404);
        }

        $this->groupAccess->assertAdminOrOwner($group, $me);

        $joinRequest
            ->setStatus(ChatGroupJoinRequest::STATUS_REJECTED)
            ->setReviewedBy($me);

        $this->em->flush();

        return $this->json([
            'ok' => true,
            'message' => 'Demande refusée.',
        ]);
    }

    #[Route('/{id}/recommend', name: 'recommend', methods: ['POST'])]
    public function recommend(ChatGroup $group, Request $request): JsonResponse
    {
        $me = $this->currentUser->requireUser($this);
        $this->groupAccess->assertMember($group, $me);

        $userId = (int) $request->request->get('userId', 0);
        $identifier = trim((string) $request->request->get('identifier'));
        $note = trim((string) $request->request->get('note'));

        $recommendedTo = null;

        if ($userId > 0) {
            $recommendedTo = $this->em->getRepository(User::class)->find($userId);
        }

        if (!$recommendedTo && $identifier !== '') {
            $recommendedTo = $this->findUserByIdentifier($identifier);
        }

        if (!$recommendedTo instanceof User) {
            return $this->json([
                'ok' => false,
                'message' => 'Utilisateur introuvable.',
            ], 404);
        }

        if ($recommendedTo->getId() === $me->getId()) {
            return $this->json([
                'ok' => false,
                'message' => 'Vous ne pouvez pas vous recommander ce groupe.',
            ], 422);
        }

        if (!$this->areFriends($me, $recommendedTo)) {
            return $this->json([
                'ok' => false,
                'message' => 'Vous pouvez recommander un groupe uniquement à vos amis.',
            ], 403);
        }

        $membership = $this->em->getRepository(ChatGroupMember::class)->findOneBy([
            'chatGroup' => $group,
            'user' => $recommendedTo,
            'status' => ChatGroupMember::STATUS_ACTIVE,
        ]);

        if ($membership) {
            return $this->json([
                'ok' => true,
                'message' => 'Cet utilisateur est déjà membre du groupe.',
            ]);
        }

        $existing = $this->em->getRepository(ChatGroupRecommendation::class)->findOneBy([
            'chatGroup' => $group,
            'recommendedBy' => $me,
            'recommendedTo' => $recommendedTo,
            'status' => ChatGroupRecommendation::STATUS_PENDING,
        ]);

        if ($existing) {
            return $this->json([
                'ok' => true,
                'message' => 'Ce groupe a déjà été recommandé à cet ami.',
            ]);
        }

        $recommendation = (new ChatGroupRecommendation())
            ->setChatGroup($group)
            ->setRecommendedBy($me)
            ->setRecommendedTo($recommendedTo)
            ->setNote($note ?: null);

        $this->em->persist($recommendation);
        $this->em->flush();

        return $this->json([
            'ok' => true,
            'message' => 'Groupe recommandé avec succès.',
        ]);
    }

    #[Route('/{id}/recommendable-friends', name: 'recommendable_friends', methods: ['GET'])]
    public function recommendableFriends(ChatGroup $group): JsonResponse
    {
        $me = $this->currentUser->requireUser($this);
        $this->groupAccess->assertMember($group, $me);

        $friendships = $this->em->createQueryBuilder()
            ->select('f', 'requester', 'addressee')
            ->from(Friendship::class, 'f')
            ->leftJoin('f.requester', 'requester')
            ->leftJoin('f.addressee', 'addressee')
            ->where('(f.requester = :me OR f.addressee = :me)')
            ->andWhere('f.status = :status')
            ->setParameter('me', $me)
            ->setParameter('status', Friendship::STATUS_ACCEPTED)
            ->orderBy('f.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        $items = [];

        foreach ($friendships as $friendship) {
            $friend = $friendship->getRequester()?->getId() === $me->getId()
                ? $friendship->getAddressee()
                : $friendship->getRequester();

            if (!$friend instanceof User) {
                continue;
            }

            $membership = $this->em->getRepository(ChatGroupMember::class)->findOneBy([
                'chatGroup' => $group,
                'user' => $friend,
            ]);

            $recommendation = $this->em->getRepository(ChatGroupRecommendation::class)->findOneBy([
                'chatGroup' => $group,
                'recommendedBy' => $me,
                'recommendedTo' => $friend,
                'status' => ChatGroupRecommendation::STATUS_PENDING,
            ]);

            $items[] = [
                'id' => $friend->getId(),
                'name' => $this->userLabel($friend),
                'email' => $friend->getEmail(),
                'initial' => mb_strtoupper(mb_substr($this->userLabel($friend), 0, 1)),
                'alreadyMember' => $membership?->getStatus() === ChatGroupMember::STATUS_ACTIVE,
                'membershipStatus' => $membership?->getStatus(),
                'alreadyRecommended' => $recommendation !== null,
            ];
        }

        return $this->json([
            'ok' => true,
            'items' => $items,
        ]);
    }

    #[Route('/{id}/messages', name: 'messages', methods: ['GET'])]
    public function messages(ChatGroup $group): JsonResponse
    {
        $me = $this->currentUser->requireUser($this);
        $this->groupAccess->assertMember($group, $me);

        $messages = $this->em->getRepository(ChatGroupMessage::class)->findBy(
            ['chatGroup' => $group],
            ['createdAt' => 'DESC'],
            60
        );

        $messages = array_reverse($messages);

        return $this->json([
            'ok' => true,
            'items' => array_map(fn (ChatGroupMessage $message) => $this->normalizeMessage($message, $me), $messages),
        ]);
    }

    #[Route('/{id}/send', name: 'send', methods: ['POST'])]
    public function send(ChatGroup $group, Request $request): JsonResponse
    {
        $me = $this->currentUser->requireUser($this);
        $this->groupAccess->assertMember($group, $me);

        $payload = json_decode((string) $request->getContent(), true) ?: [];
        $content = trim((string) ($payload['content'] ?? ''));

        $replyToId = (int) ($payload['replyToId'] ?? 0);

        if ($content === '') {
            return $this->json([
                'ok' => false,
                'message' => 'Message vide.',
            ], 400);
        }

        $message = (new ChatGroupMessage())
            ->setChatGroup($group)
            ->setSender($me)
            ->setType(ChatGroupMessage::TYPE_TEXT)
            ->setContent($content)
            ->setMeta([
                'clientSentAt' => ChatTimestamp::sanitizeClientSentAt($payload['clientSentAt'] ?? null),
            ]);

        if ($replyToId > 0) {
            $replyTo = $this->em->getRepository(ChatGroupMessage::class)->find($replyToId);

            if ($replyTo instanceof ChatGroupMessage && $replyTo->getChatGroup()?->getId() === $group->getId()) {
                $message->setReplyTo($replyTo);
            }
        }

        $this->em->persist($message);
        $this->em->flush();

        $this->publishGroupMessage($group, $message);

        return $this->json([
            'ok' => true,
            'message' => $this->normalizeMessage($message, $me),
        ]);
    }

    #[Route('/{id}/send-file', name: 'send_file', methods: ['POST'])]
    public function sendFile(ChatGroup $group, Request $request): JsonResponse
    {
        $me = $this->currentUser->requireUser($this);
        $this->groupAccess->assertMember($group, $me);

        if (!$group->isAllowFiles()) {
            return $this->json([
                'ok' => false,
                'message' => 'L’envoi de fichiers est désactivé dans ce groupe.',
            ], 403);
        }

        /** @var UploadedFile|null $file */
        $file = $request->files->get('file');

        if (!$file instanceof UploadedFile || !$file->isValid()) {
            return $this->json([
                'ok' => false,
                'message' => 'Fichier invalide.',
            ], 400);
        }

        $originalName = $file->getClientOriginalName() ?: 'fichier';
        $clientExtension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
        $forbiddenExtensions = ['php', 'phtml', 'phar', 'cgi', 'pl', 'py', 'sh', 'bash', 'bat', 'cmd', 'exe', 'com', 'msi', 'dll', 'jar'];

        if ($clientExtension !== '' && in_array($clientExtension, $forbiddenExtensions, true)) {
            return $this->json([
                'ok' => false,
                'message' => 'Ce type de fichier exécutable n’est pas autorisé pour des raisons de sécurité.',
            ], 415);
        }

        $mimeType = $file->getClientMimeType() ?: $file->getMimeType() ?: 'application/octet-stream';
        $size = $file->getSize() ?: 0;

        if ($size > 25 * 1024 * 1024) {
            return $this->json([
                'ok' => false,
                'message' => 'Le fichier dépasse 25 Mo.',
            ], 413);
        }

        $type = $this->detectMessageType($originalName, $mimeType);

        if ($type === ChatGroupMessage::TYPE_VOICE && !$group->isAllowVoice()) {
            return $this->json([
                'ok' => false,
                'message' => 'Les vocaux sont désactivés dans ce groupe.',
            ], 403);
        }

        $uploadDir = $this->projectDir . '/public/uploads/chat/groups';

        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
            return $this->json([
                'ok' => false,
                'message' => 'Impossible de créer le dossier de stockage.',
            ], 500);
        }

        $generatedName = $this->generateStoredFilename($file, $type);
        $baseName = pathinfo($generatedName, PATHINFO_FILENAME);

        try {
            $storedName = $this->uploadOptimizer->store($file, $uploadDir, $baseName);
        } catch (FileException $exception) {
            return $this->json([
                'ok' => false,
                'message' => 'Échec de l’envoi du fichier.',
            ], 500);
        }

        $storedPath = $uploadDir . DIRECTORY_SEPARATOR . $storedName;
        $mimeType = function_exists('mime_content_type')
            ? (mime_content_type($storedPath) ?: $mimeType)
            : $mimeType;
        $size = is_file($storedPath) ? (filesize($storedPath) ?: $size) : $size;
        $type = $this->detectMessageType($storedName, $mimeType);

        $message = (new ChatGroupMessage())
            ->setChatGroup($group)
            ->setSender($me)
            ->setType($type)
            ->setContent($originalName)
            ->setAttachmentUrl($storedName)
            ->setMeta([
                'originalName' => $originalName,
                'mimeType' => $mimeType,
                'size' => $size,
                'url' => '/uploads/chat/groups/' . $storedName,
                'clientSentAt' => ChatTimestamp::sanitizeClientSentAt($request->request->get('clientSentAt')),
            ]);

        $replyToId = (int) $request->request->get('replyToId', 0);

        if ($replyToId > 0) {
            $replyTo = $this->em->getRepository(ChatGroupMessage::class)->find($replyToId);

            if ($replyTo instanceof ChatGroupMessage && $replyTo->getChatGroup()?->getId() === $group->getId()) {
                $message->setReplyTo($replyTo);
            }
        }

        $this->em->persist($message);
        $this->em->flush();

        $this->publishGroupMessage($group, $message);

        return $this->json([
            'ok' => true,
            'message' => $this->normalizeMessage($message, $me),
        ]);
    }

    #[Route('/messages/{id}/delete', name: 'delete_message', methods: ['POST'])]
    public function deleteMessage(ChatGroupMessage $message): JsonResponse
    {
        $me = $this->currentUser->requireUser($this);
        $group = $message->getChatGroup();

        if (!$group) {
            return $this->json([
                'ok' => false,
                'message' => 'Groupe introuvable.',
            ], 404);
        }

        $this->groupAccess->assertMember($group, $me);

        $isMine = $message->getSender()?->getId() === $me->getId();
        $isOwnerOrAdmin = $this->groupAccess->isAdminOrOwner($group, $me);

        if (!$isMine && !$isOwnerOrAdmin) {
            return $this->json([
                'ok' => false,
                'message' => 'Vous ne pouvez supprimer que vos propres messages.',
            ], 403);
        }

        $message
            ->setDeleted(true)
            ->setDeletedBy($me)
            ->setContent(null)
            ->setAttachmentUrl(null)
            ->setMeta([]);

        $this->em->flush();

        $this->publishGroupMessage($group, $message, 'message_deleted');

        return $this->json([
            'ok' => true,
            'message' => 'Message supprimé.',
        ]);
    }


    #[Route('/mine', name: 'mine', methods: ['GET'])]
    public function mine(): JsonResponse
    {
        $me = $this->currentUser->requireUser($this);

        $memberships = $this->em->getRepository(ChatGroupMember::class)->findBy([
            'user' => $me,
            'status' => ChatGroupMember::STATUS_ACTIVE,
        ]);

        $groups = array_values(array_filter(array_map(
            static fn (ChatGroupMember $member) => $member->getChatGroup(),
            $memberships
        )));

        return $this->json([
            'ok' => true,
            'items' => array_map(fn (ChatGroup $group) => $this->normalizeGroupListItem($group, $me), $groups),
        ]);
    }

    #[Route('/discover', name: 'discover', methods: ['GET'])]
    public function discover(): JsonResponse
    {
        $me = $this->currentUser->requireUser($this);

        $groups = $this->em->createQueryBuilder()
            ->select('g')
            ->from(ChatGroup::class, 'g')
            ->where('g.active = true')
            ->andWhere('g.owner != :me')
            ->andWhere('g.visibility IN (:visibilities)')
            ->setParameter('me', $me)
            ->setParameter('visibilities', [
                ChatGroup::VISIBILITY_PUBLIC,
                ChatGroup::VISIBILITY_RECOMMENDED,
            ])
            ->orderBy('g.createdAt', 'DESC')
            ->setMaxResults(40)
            ->getQuery()
            ->getResult();

        $items = [];

        foreach ($groups as $group) {
            $membership = $this->em->getRepository(ChatGroupMember::class)->findOneBy([
                'chatGroup' => $group,
                'user' => $me,
            ]);

            if ($membership && $membership->getStatus() === ChatGroupMember::STATUS_ACTIVE) {
                continue;
            }

            $pendingRequest = $this->em->getRepository(ChatGroupJoinRequest::class)->findOneBy([
                'chatGroup' => $group,
                'requester' => $me,
                'status' => ChatGroupJoinRequest::STATUS_PENDING,
            ]);

            $item = $this->normalizeGroupListItem($group, $me);
            $item['alreadyMember'] = false;
            $item['requestPending'] = $pendingRequest !== null;

            $items[] = $item;
        }

        return $this->json([
            'ok' => true,
            'items' => $items,
        ]);
    }

    #[Route('/join-requests', name: 'join_requests', methods: ['GET'])]
    public function joinRequests(): JsonResponse
    {
        $me = $this->currentUser->requireUser($this);

        $requests = $this->em->createQueryBuilder()
            ->select('r', 'g', 'u')
            ->from(ChatGroupJoinRequest::class, 'r')
            ->join('r.chatGroup', 'g')
            ->join('r.requester', 'u')
            ->where('g.owner = :me')
            ->andWhere('r.status = :status')
            ->setParameter('me', $me)
            ->setParameter('status', ChatGroupJoinRequest::STATUS_PENDING)
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->json([
            'ok' => true,
            'items' => array_map(fn (ChatGroupJoinRequest $request) => [
                'id' => $request->getId(),
                'groupId' => $request->getChatGroup()?->getId(),
                'groupName' => $request->getChatGroup()?->getName(),
                'requesterName' => $this->userLabel($request->getRequester()),
                'requesterEmail' => $request->getRequester()?->getEmail(),
                'message' => $request->getMessage(),
                'createdAt' => $request->getCreatedAt()->format('d/m/Y H:i'),
            ], $requests),
        ]);
    }

    #[Route('/{id}/presence/ping', name: 'presence_ping', methods: ['POST'])]
    public function presencePing(ChatGroup $group): JsonResponse
    {
        $me = $this->currentUser->requireUser($this);
        $member = $this->groupAccess->assertMember($group, $me);

        $member->setLastSeenAt(new \DateTimeImmutable());
        $this->em->flush();

        return $this->json([
            'ok' => true,
        ]);
    }


    #[Route('/messages/{id}/reaction/toggle', name: 'toggle_reaction', methods: ['POST'])]
    public function toggleReaction(ChatGroupMessage $message, Request $request): JsonResponse
    {
        $me = $this->currentUser->requireUser($this);
        $group = $message->getChatGroup();

        if (!$group) {
            return $this->json([
                'ok' => false,
                'message' => 'Groupe introuvable.',
            ], 404);
        }

        $this->groupAccess->assertMember($group, $me);

        $payload = json_decode((string) $request->getContent(), true) ?: [];
        $emoji = trim((string) ($payload['emoji'] ?? $request->request->get('emoji', '')));

        if ($emoji === '') {
            return $this->json([
                'ok' => false,
                'message' => 'Emoji manquant.',
            ], 422);
        }

        $repository = $this->em->getRepository(ChatGroupMessageReaction::class);

        $reaction = $repository->findOneBy([
            'message' => $message,
            'user' => $me,
            'emoji' => $emoji,
        ]);

        if ($reaction) {
            $this->em->remove($reaction);
            $action = 'removed';
        } else {
            $reaction = (new ChatGroupMessageReaction())
                ->setMessage($message)
                ->setUser($me)
                ->setEmoji($emoji);

            $this->em->persist($reaction);
            $action = 'added';
        }

        $this->em->flush();

        return $this->json([
            'ok' => true,
            'action' => $action,
        ]);
    }


    private function areFriends(User $a, User $b): bool
    {
        $friendship = $this->em->createQueryBuilder()
            ->select('f')
            ->from(Friendship::class, 'f')
            ->where('
                (f.requester = :a AND f.addressee = :b)
                OR
                (f.requester = :b AND f.addressee = :a)
            ')
            ->andWhere('f.status = :status')
            ->setParameter('a', $a)
            ->setParameter('b', $b)
            ->setParameter('status', Friendship::STATUS_ACCEPTED)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $friendship instanceof Friendship;
    }

    private function normalizeGroupListItem(ChatGroup $group, User $viewer): array
    {
        $memberCount = $this->em->getRepository(ChatGroupMember::class)->count([
            'chatGroup' => $group,
            'status' => ChatGroupMember::STATUS_ACTIVE,
        ]);

        $membership = $this->em->getRepository(ChatGroupMember::class)->findOneBy([
            'chatGroup' => $group,
            'user' => $viewer,
        ]);

        return [
            'id' => $group->getId(),
            'name' => $group->getName(),
            'description' => $group->getDescription(),
            'visibility' => $group->getVisibility(),
            'ownerName' => $this->userLabel($group->getOwner()),
            'isOwner' => $group->getOwner()?->getId() === $viewer->getId(),
            'memberCount' => $memberCount,
            'membershipStatus' => $membership?->getStatus(),
            'createdAt' => $group->getCreatedAt()->format('d/m/Y'),
        ];
    }


    #[Route('/{id}/detail', name: 'detail', methods: ['GET'])]
    public function detail(ChatGroup $group): JsonResponse
    {
        $me = $this->currentUser->requireUser($this);

        $this->groupAccess->assertMember($group, $me);

        if ($this->groupAccess->isOwner($group, $me)) {
            $this->groupAccess->ensureOwnerMembership($group, $me);
        }

        $members = $this->em->getRepository(ChatGroupMember::class)->findBy(
            [
                'chatGroup' => $group,
                'status' => ChatGroupMember::STATUS_ACTIVE,
            ],
            [
                'joinedAt' => 'ASC',
            ]
        );

        $canManage = $this->groupAccess->isAdminOrOwner($group, $me);

        return $this->json([
            'ok' => true,
            'group' => [
                'id' => $group->getId(),
                'name' => $group->getName(),
                'description' => $group->getDescription(),
                'visibility' => $group->getVisibility(),
                'ownerName' => $this->userLabel($group->getOwner()),
                'memberCount' => count($members),
                'isOwner' => $group->getOwner()?->getId() === $me->getId(),
                'canManage' => $canManage,
                'allowFiles' => $group->isAllowFiles(),
                'allowVoice' => $group->isAllowVoice(),
            ],
            'members' => array_map(fn (ChatGroupMember $member) => [
                'id' => $member->getId(),
                'userId' => $member->getUser()?->getId(),
                'name' => $this->userLabel($member->getUser()),
                'email' => $member->getUser()?->getEmail(),
                'role' => $member->getRole(),
                'isOwner' => $group->getOwner()?->getId() === $member->getUser()?->getId(),
                'isMe' => $member->getUser()?->getId() === $me->getId(),
                'canRemove' => $canManage
                    && $member->getUser()?->getId() !== $me->getId()
                    && $group->getOwner()?->getId() !== $member->getUser()?->getId(),
                'presence' => $this->getMemberPresence($member),
                'lastSeenLabel' => $this->getMemberLastSeenLabel($member),
            ], $members),
        ]);
    }

    #[Route('/{id}/members/{memberId}/remove', name: 'remove_member', methods: ['POST'])]
    public function removeMember(ChatGroup $group, int $memberId): JsonResponse
    {
        $me = $this->currentUser->requireUser($this);

        $this->groupAccess->assertAdminOrOwner($group, $me);

        $member = $this->em->getRepository(ChatGroupMember::class)->findOneBy([
            'id' => $memberId,
            'chatGroup' => $group,
            'status' => ChatGroupMember::STATUS_ACTIVE,
        ]);

        if (!$member) {
            return $this->json([
                'ok' => false,
                'message' => 'Membre introuvable.',
            ], 404);
        }

        if ($member->getUser()?->getId() === $group->getOwner()?->getId()) {
            return $this->json([
                'ok' => false,
                'message' => 'Le créateur du groupe ne peut pas être retiré.',
            ], 403);
        }

        if ($member->getUser()?->getId() === $me->getId()) {
            return $this->json([
                'ok' => false,
                'message' => 'Vous ne pouvez pas vous retirer ici.',
            ], 403);
        }

        $member->setStatus(ChatGroupMember::STATUS_REMOVED);

        $systemMessage = (new ChatGroupMessage())
            ->setChatGroup($group)
            ->setSender($me)
            ->setType('system')
            ->setContent(sprintf('%s a été retiré du groupe.', $this->userLabel($member->getUser())));

        $this->em->persist($systemMessage);
        $this->em->flush();


        return $this->json([
            'ok' => true,
            'message' => 'Le membre a été retiré du groupe.',
        ]);
    }


    #[Route('/{id}/invite', name: 'invite', methods: ['POST'])]
    public function invite(ChatGroup $group, Request $request): JsonResponse
    {
        $me = $this->currentUser->requireUser($this);
        $this->groupAccess->assertAdminOrOwner($group, $me);

        $identifier = trim((string) $request->request->get('identifier'));

        if ($identifier === '') {
            return $this->json([
                'ok' => false,
                'message' => 'Email ou identifiant obligatoire.',
            ], 422);
        }

        $user = $this->findUserByIdentifier($identifier);

        if (!$user) {
            return $this->json([
                'ok' => false,
                'message' => 'Utilisateur introuvable.',
            ], 404);
        }

        if ($user->getId() === $me->getId()) {
            return $this->json([
                'ok' => false,
                'message' => 'Vous êtes déjà dans ce groupe.',
            ], 422);
        }

        $member = $this->em->getRepository(ChatGroupMember::class)->findOneBy([
            'chatGroup' => $group,
            'user' => $user,
        ]);

        if (!$member) {
            $member = (new ChatGroupMember())
                ->setChatGroup($group)
                ->setUser($user)
                ->setRole(ChatGroupMember::ROLE_MEMBER);

            $this->em->persist($member);
        }

        if ($member->getStatus() === ChatGroupMember::STATUS_ACTIVE) {
            return $this->json([
                'ok' => true,
                'message' => 'Cet utilisateur est déjà membre du groupe.',
            ]);
        }

        $member->setStatus(ChatGroupMember::STATUS_ACTIVE);

        $systemMessage = (new ChatGroupMessage())
            ->setChatGroup($group)
            ->setSender($me)
            ->setType('system')
            ->setContent(sprintf('%s a été invité dans le groupe.', $this->userLabel($user)));

        $this->em->persist($systemMessage);
        $this->em->flush();


        return $this->json([
            'ok' => true,
            'message' => 'Utilisateur invité avec succès.',
        ]);
    }

    #[Route('/recommended-for-me', name: 'recommended_for_me', methods: ['GET'])]
    public function recommendedForMe(): JsonResponse
    {
        $me = $this->currentUser->requireUser($this);

        $recommendations = $this->em->getRepository(ChatGroupRecommendation::class)->findBy(
            [
                'recommendedTo' => $me,
                'status' => ChatGroupRecommendation::STATUS_PENDING,
            ],
            [
                'createdAt' => 'DESC',
            ]
        );

        $items = [];

        foreach ($recommendations as $recommendation) {
            $group = $recommendation->getChatGroup();

            if (!$group || !$group->isActive()) {
                continue;
            }

            $pendingRequest = $this->em->getRepository(ChatGroupJoinRequest::class)->findOneBy([
                'chatGroup' => $group,
                'requester' => $me,
                'status' => ChatGroupJoinRequest::STATUS_PENDING,
            ]);

            $item = $this->normalizeGroupListItem($group, $me);
            $item['recommendationId'] = $recommendation->getId();
            $item['recommendedBy'] = $this->userLabel($recommendation->getRecommendedBy());
            $item['note'] = $recommendation->getNote();
            $item['requestPending'] = $pendingRequest !== null;

            $items[] = $item;
        }

        return $this->json([
            'ok' => true,
            'items' => $items,
        ]);
    }

    private function getMemberPresence(ChatGroupMember $member): string
    {
        $lastSeenAt = $member->getLastSeenAt();

        if (!$lastSeenAt) {
            return 'offline';
        }

        $seconds = time() - $lastSeenAt->getTimestamp();

        if ($seconds <= 90) {
            return 'online';
        }

        if ($seconds <= 900) {
            return 'away';
        }

        return 'offline';
    }

    private function getMemberLastSeenLabel(ChatGroupMember $member): string
    {
        $lastSeenAt = $member->getLastSeenAt();

        if (!$lastSeenAt) {
            return 'Déconnecté';
        }

        $seconds = time() - $lastSeenAt->getTimestamp();

        if ($seconds <= 90) {
            return 'En ligne';
        }

        if ($seconds <= 900) {
            return 'Vient de se déconnecter';
        }

        return 'Déconnecté';
    }

    private function publishGroupMessage(ChatGroup $group, ChatGroupMessage $message, string $event = 'message'): void
    {
        $topic = sprintf('/groups/%d/messages', $group->getId());

    }

    private function normalizeGroup(ChatGroup $group, User $viewer): array
    {
        return [
            'id' => $group->getId(),
            'name' => $group->getName(),
            'description' => $group->getDescription(),
            'visibility' => $group->getVisibility(),
            'owner' => $this->userLabel($group->getOwner()),
            'isOwner' => $group->getOwner()?->getId() === $viewer->getId(),
        ];
    }

    private function normalizeMessage(ChatGroupMessage $message, User $viewer): array
    {
        $sender = $message->getSender();
        $mine = $sender?->getId() === $viewer->getId();
        $meta = $message->getMeta();

        if ($message->isDeleted()) {
            return [
                'id' => $message->getId(),
                'type' => 'deleted',
                'content' => 'Message supprimé',
                'mine' => $mine,
                'authorName' => $this->userLabel($sender),
                'createdAt' => ChatTimestamp::clock($message->getCreatedAt(), $meta),
                'createdAtIso' => ChatTimestamp::iso($message->getCreatedAt(), $meta),
            ];
        }

        return [
            'id' => $message->getId(),
            'type' => $message->getType(),
            'content' => $message->getContent(),
            'attachmentUrl' => $message->getMeta()['url'] ?? null,
            'meta' => $meta,
            'mine' => $mine,
            'canDelete' => $mine || $this->groupAccess->isAdminOrOwner($message->getChatGroup(), $viewer),
            'authorName' => $this->userLabel($sender),
            'createdAt' => ChatTimestamp::clock($message->getCreatedAt(), $meta),
            'createdAtIso' => ChatTimestamp::iso($message->getCreatedAt(), $meta),
            'replyTo' => $this->normalizeReplyTo($message->getReplyTo()),
            'reactions' => $this->normalizeReactions($message, $viewer),
        ];
    }

    private function normalizeReplyTo(?ChatGroupMessage $replyTo): ?array
    {
        if (!$replyTo instanceof ChatGroupMessage) {
            return null;
        }

        if ($replyTo->isDeleted()) {
            return [
                'id' => $replyTo->getId(),
                'authorName' => 'Message supprimé',
                'content' => 'Ce message n’est plus disponible.',
            ];
        }

        return [
            'id' => $replyTo->getId(),
            'authorName' => $this->userLabel($replyTo->getSender()),
            'content' => $replyTo->getContent() ?: match ($replyTo->getType()) {
                ChatGroupMessage::TYPE_IMAGE => 'Image',
                ChatGroupMessage::TYPE_AUDIO => 'Audio',
                ChatGroupMessage::TYPE_VOICE => 'Vocal',
                ChatGroupMessage::TYPE_PDF => 'PDF',
                ChatGroupMessage::TYPE_WORD => 'Document Word',
                default => 'Fichier',
            },
        ];
    }

    private function normalizeReactions(ChatGroupMessage $message, User $viewer): array
    {
        $reactions = $this->em->getRepository(ChatGroupMessageReaction::class)->findBy([
            'message' => $message,
        ]);

        $items = [];

        foreach ($reactions as $reaction) {
            $emoji = $reaction->getEmoji();

            if (!isset($items[$emoji])) {
                $items[$emoji] = [
                    'emoji' => $emoji,
                    'count' => 0,
                    'reactedByMe' => false,
                ];
            }

            $items[$emoji]['count']++;

            if ($reaction->getUser()?->getId() === $viewer->getId()) {
                $items[$emoji]['reactedByMe'] = true;
            }
        }

        return array_values($items);
    }

    private function findUserByIdentifier(string $identifier): ?User
    {
        $identifier = trim($identifier);

        if ($identifier === '') {
            return null;
        }

        return $this->em->getRepository(User::class)->findOneBy([
            'email' => $identifier,
        ]);
    }

    private function userLabel(?User $user): string
    {
        if (!$user) {
            return 'Utilisateur';
        }

        return $user->getPersonalProfile()?->getFullName()
            ?? $user->getEmail()
            ?? 'Utilisateur';
    }

    private function splitIdentifiers(string $value): array
    {
        return array_values(array_filter(array_map(
            static fn (string $item) => trim($item),
            preg_split('/[\s,;]+/', $value) ?: []
        )));
    }

    private function generateGroupSlug(string $name): string
    {
        return \App\Util\HashedSlugGenerator::generate();
    }

    private function detectMessageType(string $name, string $mimeType): string
    {
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $mimeType = strtolower($mimeType);

        if (str_starts_with($mimeType, 'image/')) {
            return ChatGroupMessage::TYPE_IMAGE;
        }

        if (str_starts_with($mimeType, 'audio/')) {
            return ChatGroupMessage::TYPE_AUDIO;
        }

        if ($extension === 'pdf') {
            return ChatGroupMessage::TYPE_PDF;
        }

        if (in_array($extension, ['doc', 'docx'], true)) {
            return ChatGroupMessage::TYPE_WORD;
        }

        return ChatGroupMessage::TYPE_FILE;
    }

    private function generateStoredFilename(UploadedFile $file, string $type): string
    {
        $base = pathinfo($file->getClientOriginalName() ?: $type, PATHINFO_FILENAME);
        $safeBase = strtolower((string) $this->slugger->slug($base ?: $type));

        $extension = strtolower($file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'bin');

        $forbidden = ['php', 'phtml', 'phar', 'cgi', 'pl', 'py', 'sh', 'bat', 'cmd', 'exe', 'com', 'msi'];

        if (in_array($extension, $forbidden, true)) {
            $extension = 'bin';
        }

        return sprintf('%s-%s.%s', $safeBase ?: $type, bin2hex(random_bytes(8)), $extension);
    }
}
