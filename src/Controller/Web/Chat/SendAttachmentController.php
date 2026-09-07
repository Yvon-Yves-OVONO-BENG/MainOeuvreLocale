<?php

namespace App\Controller\Web\Chat;

use App\Entity\Conversation;
use App\Entity\Message;
use App\Security\ConversationAccess;
use App\Security\CurrentUser;
use App\Repository\MessageRepository;
use App\Service\ChatBlockService;
use App\Service\UploadOptimizer;
use App\Util\ChatTimestamp;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/chat/conversation/{id}', name: 'chat_conversation_')]
final class SendAttachmentController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private CurrentUser $currentUser,
        private ConversationAccess $access,
        private MessageRepository $messageRepository,
        private SluggerInterface $slugger,
        private ChatBlockService $chatBlockService,
        private UploadOptimizer $uploadOptimizer,
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir,
    ) {
    }

    #[Route('/send-file', name: 'send_file', methods: ['POST'])]
    public function sendFile(Conversation $conversation, Request $request): JsonResponse
    {
        $me = $this->currentUser->requireUser($this);
        $this->access->assertParticipant($conversation, $me);

        $senderIdentifier = $this->getUserIdentifierValue($me);
        $receiverIdentifier = $this->getReceiverIdentifier($conversation, $me);

        if (
            $senderIdentifier !== null
            && $receiverIdentifier !== null
            && !$this->chatBlockService->canReceiveFile($receiverIdentifier, $senderIdentifier)
        ) {
            return $this->json([
                'ok' => false,
                'message' => 'L’envoi de fichiers est bloqué par les réglages de sécurité.',
            ], 403);
        }

        /** @var UploadedFile|null $file */
        $file = $request->files->get('file');

        if (!$file instanceof UploadedFile) {
            return $this->json([
                'ok' => false,
                'error' => 'Aucun fichier reçu.',
                'files' => array_keys($request->files->all()),
            ], 400);
        }

        if (!$file->isValid()) {
            return $this->json([
                'ok' => false,
                'error' => 'Fichier invalide.',
                'uploadErrorCode' => $file->getError(),
            ], 400);
        }

        if (($file->getSize() ?: 0) > 25 * 1024 * 1024) {
            return $this->json(['ok' => false, 'error' => 'Le fichier dépasse la limite de 25 Mo.'], 413);
        }

        $tmpPath = $file->getPathname();

        if (!$tmpPath || !is_file($tmpPath) || !is_readable($tmpPath)) {
            return $this->json([
                'ok' => false,
                'error' => 'Le fichier temporaire est introuvable ou illisible.',
                'tmpPath' => $tmpPath,
            ], 500);
        }

        $uploadDir = $this->projectDir . '/public/uploads/chat/files';

        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
            return $this->json([
                'ok' => false,
                'error' => 'Impossible de créer le dossier de stockage.',
            ], 500);
        }

        if (!is_writable($uploadDir)) {
            return $this->json([
                'ok' => false,
                'error' => 'Le dossier de stockage n’est pas accessible en écriture.',
                'dir' => $uploadDir,
            ], 500);
        }

        $originalName = $file->getClientOriginalName() ?: 'fichier';
        $clientExtension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
        $forbiddenExtensions = ['php', 'phtml', 'phar', 'cgi', 'pl', 'py', 'sh', 'bash', 'bat', 'cmd', 'exe', 'com', 'msi', 'dll', 'jar'];

        if ($clientExtension !== '' && in_array($clientExtension, $forbiddenExtensions, true)) {
            return $this->json([
                'ok' => false,
                'error' => 'Ce type de fichier exécutable n’est pas autorisé pour des raisons de sécurité.',
            ], 415);
        }

        $mimeType = $file->getClientMimeType() ?: $file->getMimeType() ?: 'application/octet-stream';
        $size = $file->getSize() ?: 0;

        $generatedName = $this->generateStoredFilename($file, 'file');
        $baseName = pathinfo($generatedName, PATHINFO_FILENAME);

        try {
            $storedName = $this->uploadOptimizer->store($file, $uploadDir, $baseName);
        } catch (FileException $e) {
            return $this->json([
                'ok' => false,
                'error' => 'Échec du transfert du fichier : ' . $e->getMessage(),
                'tmpPath' => $tmpPath,
                'target' => $uploadDir . DIRECTORY_SEPARATOR . $generatedName,
            ], 500);
        }

        $storedPath = $uploadDir . DIRECTORY_SEPARATOR . $storedName;
        $mimeType = function_exists('mime_content_type')
            ? (mime_content_type($storedPath) ?: $mimeType)
            : $mimeType;
        $size = is_file($storedPath) ? (filesize($storedPath) ?: $size) : $size;

        $message = (new Message())
            ->setConversation($conversation)
            ->setSender($me)
            ->setType(Message::TYPE_FILE)
            ->setContent($originalName)
            ->setAttachmentUrl($storedName)
            ->setMeta([
                'originalName' => $originalName,
                'mimeType' => $mimeType,
                'size' => $size,
                'category' => 'file',
                'clientSentAt' => ChatTimestamp::sanitizeClientSentAt($request->request->get('clientSentAt')),
            ]);

        $replyToId = (int) $request->request->get('replyToId', 0);
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
            'filename' => $storedName,
            'originalName' => $originalName,
            'mimeType' => $mimeType,
            'size' => $size,
        ]);
    }

    #[Route('/send-voice', name: 'send_voice', methods: ['POST'])]
    public function sendVoice(Conversation $conversation, Request $request): JsonResponse
    {
        $me = $this->currentUser->requireUser($this);
        $this->access->assertParticipant($conversation, $me);

        $senderIdentifier = $this->getUserIdentifierValue($me);
        $receiverIdentifier = $this->getReceiverIdentifier($conversation, $me);

        if (
            $senderIdentifier !== null
            && $receiverIdentifier !== null
            && !$this->chatBlockService->canReceiveFile($receiverIdentifier, $senderIdentifier)
        ) {
            return $this->json([
                'ok' => false,
                'message' => 'L’envoi de vocaux est bloqué par les réglages de sécurité.',
            ], 403);
        }

        /** @var UploadedFile|null $voice */
        $voice = $request->files->get('voice');

        if (!$voice instanceof UploadedFile) {
            return $this->json([
                'ok' => false,
                'error' => 'Aucun vocal reçu.',
                'files' => array_keys($request->files->all()),
            ], 400);
        }

        if (!$voice->isValid()) {
            return $this->json([
                'ok' => false,
                'error' => 'Fichier vocal invalide.',
                'uploadErrorCode' => $voice->getError(),
            ], 400);
        }

        $tmpPath = $voice->getPathname();

        if (!$tmpPath || !is_file($tmpPath) || !is_readable($tmpPath)) {
            return $this->json([
                'ok' => false,
                'error' => 'Le fichier temporaire du vocal est introuvable ou illisible.',
                'tmpPath' => $tmpPath,
            ], 500);
        }

        $uploadDir = $this->projectDir . '/public/uploads/chat/voices';

        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
            return $this->json([
                'ok' => false,
                'error' => 'Impossible de créer le dossier des vocaux.',
            ], 500);
        }

        if (!is_writable($uploadDir)) {
            return $this->json([
                'ok' => false,
                'error' => 'Le dossier des vocaux n’est pas accessible en écriture.',
                'dir' => $uploadDir,
            ], 500);
        }

        $originalName = $voice->getClientOriginalName() ?: 'message.webm';
        $mimeType = $voice->getClientMimeType() ?: $voice->getMimeType() ?: 'audio/webm';
        $size = $voice->getSize() ?: 0;

        $storedName = $this->generateStoredFilename($voice, 'voice');

        try {
            $voice->move($uploadDir, $storedName);
        } catch (FileException $e) {
            return $this->json([
                'ok' => false,
                'error' => 'Échec de l’envoi du vocal : ' . $e->getMessage(),
                'tmpPath' => $tmpPath,
                'target' => $uploadDir . DIRECTORY_SEPARATOR . $storedName,
            ], 500);
        }

        $message = (new Message())
            ->setConversation($conversation)
            ->setSender($me)
            ->setType('voice')
            ->setContent('Message vocal')
            ->setAttachmentUrl($storedName)
            ->setMeta([
                'originalName' => $originalName,
                'mimeType' => $mimeType,
                'size' => $size,
                'category' => 'voice',
                'clientSentAt' => ChatTimestamp::sanitizeClientSentAt($request->request->get('clientSentAt')),
            ]);

        $replyToId = (int) $request->request->get('replyToId', 0);
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
            'filename' => $storedName,
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

    private function generateStoredFilename(UploadedFile $file, string $mode): string
    {
        $base = pathinfo($file->getClientOriginalName() ?: $mode, PATHINFO_FILENAME);
        $safeBase = (string) $this->slugger->slug($base ?: $mode);
        $safeBase = $safeBase !== '' ? strtolower($safeBase) : $mode;

        $extension = strtolower($file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'bin');

        if ($mode === 'voice') {
            $allowedVoice = ['webm', 'ogg', 'wav', 'mp3', 'm4a'];
            if (!in_array($extension, $allowedVoice, true)) {
                $extension = 'webm';
            }
            return sprintf('voice-%s.%s', bin2hex(random_bytes(8)), $extension);
        }

        $forbidden = ['php', 'phtml', 'phar', 'cgi', 'pl', 'py', 'sh', 'bat', 'cmd', 'exe', 'com', 'msi'];
        if (in_array($extension, $forbidden, true)) {
            $extension = 'bin';
        }

        return sprintf('%s-%s.%s', $safeBase, bin2hex(random_bytes(8)), $extension);
    }
}
