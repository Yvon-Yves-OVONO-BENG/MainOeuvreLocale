<?php

namespace App\Controller\Web;

use App\Entity\ChatBlockedUser;
use App\Entity\ChatBlockPreference;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/chat/block-settings')]
class ChatBlockSettingsController extends AbstractController
{
    #[Route('/status', name: 'app_chat_block_settings_status', methods: ['GET'])]
    public function status(EntityManagerInterface $em): JsonResponse
    {
        $userIdentifier = $this->getCurrentUserIdentifier();

        if (!$userIdentifier) {
            return $this->json([
                'success' => false,
                'message' => 'Utilisateur introuvable.',
            ], 401);
        }

        $preference = $this->getOrCreatePreference($em, $userIdentifier);

        $blockedUsers = $em->getRepository(ChatBlockedUser::class)->findBy(
            [
                'blockerIdentifier' => $userIdentifier,
                'active' => true,
            ],
            [
                'createdAt' => 'DESC',
            ]
        );

        return $this->json([
            'success' => true,
            'settings' => [
                'blockUnknownUsers' => $preference->isBlockUnknownUsers(),
                'blockCalls' => $preference->isBlockCalls(),
                'blockFiles' => $preference->isBlockFiles(),
                'blockLinks' => $preference->isBlockLinks(),
                'profanityFilter' => $preference->isProfanityFilter(),
                'hideBlockedConversations' => $preference->isHideBlockedConversations(),
            ],
            'blockedUsers' => array_map(static fn (ChatBlockedUser $blockedUser) => [
                'id' => $blockedUser->getId(),
                'identifier' => $blockedUser->getBlockedIdentifier(),
                'label' => $blockedUser->getLabel(),
                'reason' => $blockedUser->getReason(),
                'createdAt' => $blockedUser->getCreatedAt()->format('d/m/Y H:i'),
            ], $blockedUsers),
        ]);
    }

    #[Route('/save', name: 'app_chat_block_settings_save', methods: ['POST'])]
    public function save(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $userIdentifier = $this->getCurrentUserIdentifier();

        if (!$userIdentifier) {
            return $this->json([
                'success' => false,
                'message' => 'Utilisateur introuvable.',
            ], 401);
        }

        if (!$this->isCsrfTokenValid('chat_block_settings', (string) $request->request->get('_token'))) {
            return $this->json([
                'success' => false,
                'message' => 'Token CSRF invalide.',
            ], 403);
        }

        $preference = $this->getOrCreatePreference($em, $userIdentifier);

        $preference
            ->setBlockUnknownUsers($request->request->getBoolean('blockUnknownUsers'))
            ->setBlockCalls($request->request->getBoolean('blockCalls'))
            ->setBlockFiles($request->request->getBoolean('blockFiles'))
            ->setBlockLinks($request->request->getBoolean('blockLinks'))
            ->setProfanityFilter($request->request->getBoolean('profanityFilter'))
            ->setHideBlockedConversations($request->request->getBoolean('hideBlockedConversations'));

        $em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Les réglages de blocage ont été enregistrés.',
        ]);
    }

    #[Route('/block-user', name: 'app_chat_block_settings_block_user', methods: ['POST'])]
    public function blockUser(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $userIdentifier = $this->getCurrentUserIdentifier();

        if (!$userIdentifier) {
            return $this->json([
                'success' => false,
                'message' => 'Utilisateur introuvable.',
            ], 401);
        }

        if (!$this->isCsrfTokenValid('chat_block_settings', (string) $request->request->get('_token'))) {
            return $this->json([
                'success' => false,
                'message' => 'Token CSRF invalide.',
            ], 403);
        }

        $blockedIdentifier = trim((string) $request->request->get('identifier'));
        $label = trim((string) $request->request->get('label'));
        $reason = trim((string) $request->request->get('reason'));

        if ($blockedIdentifier === '') {
            return $this->json([
                'success' => false,
                'message' => 'Identifiant utilisateur obligatoire.',
            ], 422);
        }

        if ($blockedIdentifier === $userIdentifier) {
            return $this->json([
                'success' => false,
                'message' => 'Vous ne pouvez pas vous bloquer vous-même.',
            ], 422);
        }

        $repository = $em->getRepository(ChatBlockedUser::class);

        $blockedUser = $repository->findOneBy([
            'blockerIdentifier' => $userIdentifier,
            'blockedIdentifier' => $blockedIdentifier,
        ]);

        if (!$blockedUser) {
            $blockedUser = new ChatBlockedUser($userIdentifier, $blockedIdentifier);
            $em->persist($blockedUser);
        }

        $blockedUser
            ->setActive(true)
            ->setLabel($label ?: $blockedIdentifier)
            ->setReason($reason ?: null);

        $em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Utilisateur bloqué.',
        ]);
    }

    #[Route('/unblock-user/{id}', name: 'app_chat_block_settings_unblock_user', methods: ['POST'])]
    public function unblockUser(int $id, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $userIdentifier = $this->getCurrentUserIdentifier();

        if (!$userIdentifier) {
            return $this->json([
                'success' => false,
                'message' => 'Utilisateur introuvable.',
            ], 401);
        }

        if (!$this->isCsrfTokenValid('chat_block_settings', (string) $request->request->get('_token'))) {
            return $this->json([
                'success' => false,
                'message' => 'Token CSRF invalide.',
            ], 403);
        }

        $blockedUser = $em->getRepository(ChatBlockedUser::class)->findOneBy([
            'id' => $id,
            'blockerIdentifier' => $userIdentifier,
        ]);

        if (!$blockedUser) {
            return $this->json([
                'success' => false,
                'message' => 'Blocage introuvable.',
            ], 404);
        }

        $blockedUser->setActive(false);
        $em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Utilisateur débloqué.',
        ]);
    }

    private function getOrCreatePreference(EntityManagerInterface $em, string $userIdentifier): ChatBlockPreference
    {
        $preference = $em->getRepository(ChatBlockPreference::class)->findOneBy([
            'userIdentifier' => $userIdentifier,
        ]);

        if (!$preference) {
            $preference = new ChatBlockPreference($userIdentifier);
            $em->persist($preference);
            $em->flush();
        }

        return $preference;
    }

    private function getCurrentUserIdentifier(): ?string
    {
        $user = $this->getUser();

        if (!$user || !method_exists($user, 'getUserIdentifier')) {
            return null;
        }

        return $user->getUserIdentifier();
    }
}