<?php

namespace App\Controller\Web;

use App\Entity\NotificationPreference;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/notifications')]
#[IsGranted('ROLE_USER')]
class NotificationPreferenceController extends AbstractController
{
    #[Route('/status', name: 'app_notifications_status', methods: ['GET'])]
    public function status(EntityManagerInterface $em): JsonResponse
    {
        $preference = $this->getOrCreatePreference($em);

        return $this->json([
            'success' => true,
            'enabled' => $preference->enabled,
        ]);
    }

    #[Route('/toggle', name: 'app_notifications_toggle', methods: ['POST'])]
    public function toggle(Request $request, EntityManagerInterface $em): JsonResponse
    {
        if (!$this->isCsrfTokenValid('notification_preference', (string) $request->request->get('_token'))) {
            return $this->json([
                'success' => false,
                'message' => 'Token CSRF invalide.',
            ], 403);
        }

        $preference = $this->getOrCreatePreference($em);
        $preference->toggle();

        $em->flush();

        return $this->json([
            'success' => true,
            'enabled' => $preference->enabled,
            'message' => $preference->enabled
                ? 'Les notifications sont réactivées.'
                : 'Les notifications sont désactivées.',
        ]);
    }

    private function getOrCreatePreference(EntityManagerInterface $em): NotificationPreference
    {
        $user = $this->getUser();

        if (!$user) {
            throw $this->createAccessDeniedException();
        }

        $userIdentifier = method_exists($user, 'getUserIdentifier')
            ? $user->getUserIdentifier()
            : (string) $user;

        $preference = $em->getRepository(NotificationPreference::class)->findOneBy([
            'userIdentifier' => $userIdentifier,
            'channel' => 'chat',
        ]);

        if (!$preference) {
            $preference = new NotificationPreference($userIdentifier, 'chat');
            $em->persist($preference);
            $em->flush();
        }

        return $preference;
    }
}