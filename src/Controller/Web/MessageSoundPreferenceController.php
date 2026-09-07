<?php

namespace App\Controller\Web;

use App\Entity\MessageSoundPreference;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/chat/message-sounds')]
class MessageSoundPreferenceController extends AbstractController
{
    #[Route('/status', name: 'app_message_sounds_status', methods: ['GET'])]
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

        return $this->json([
            'success' => true,
            'enabled' => $preference->isEnabled(),
            'sound' => $preference->getSound(),
        ]);
    }

    #[Route('/save', name: 'app_message_sounds_save', methods: ['POST'])]
    public function save(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $userIdentifier = $this->getCurrentUserIdentifier();

        if (!$userIdentifier) {
            return $this->json([
                'success' => false,
                'message' => 'Utilisateur introuvable.',
            ], 401);
        }

        if (!$this->isCsrfTokenValid('message_sound_preference', (string) $request->request->get('_token'))) {
            return $this->json([
                'success' => false,
                'message' => 'Token CSRF invalide.',
            ], 403);
        }

        $sound = (string) $request->request->get('sound', MessageSoundPreference::SOUND_SOFT);
        $enabled = filter_var($request->request->get('enabled', true), FILTER_VALIDATE_BOOL);

        if (!in_array($sound, MessageSoundPreference::ALLOWED_SOUNDS, true)) {
            return $this->json([
                'success' => false,
                'message' => 'Son invalide.',
            ], 422);
        }

        $preference = $this->getOrCreatePreference($em, $userIdentifier);
        $preference->setSound($sound);
        $preference->setEnabled($enabled && $sound !== MessageSoundPreference::SOUND_SILENT);

        $em->flush();

        return $this->json([
            'success' => true,
            'enabled' => $preference->isEnabled(),
            'sound' => $preference->getSound(),
            'message' => 'Le son des messages a bien été enregistré.',
        ]);
    }

    private function getOrCreatePreference(EntityManagerInterface $em, string $userIdentifier): MessageSoundPreference
    {
        $preference = $em->getRepository(MessageSoundPreference::class)->findOneBy([
            'userIdentifier' => $userIdentifier,
        ]);

        if (!$preference) {
            $preference = new MessageSoundPreference($userIdentifier);
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