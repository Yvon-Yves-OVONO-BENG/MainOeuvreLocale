<?php

namespace App\Controller\Web\Moderateur;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\ExpressionLanguage\Expression;

#[IsGranted(new Expression('is_granted("ROLE_MODERATEUR") or is_granted("ROLE_ADMIN") or is_granted("ROLE_SUPER_ADMIN")'))]
#[Route('/moderateur', name: 'moderateur_')]
class ModerateurSettingsController extends AbstractController
{
    #[Route('/settings', name: 'settings', methods: ['GET'])]
    public function index(): Response
    {
        // 👉 Remplace par une vraie persistance DB si tu veux.
        $settings = [
            'auto_flag' => true,
            'hide_suspicious' => false,
            'require_reason_length' => 10,
        ];

        return $this->render('moderateur/settings.html.twig', [
            'settings' => $settings
        ]);
    }

    #[Route('/settings/save', name: 'settings_save', methods: ['POST'])]
    public function save(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent() ?: '[]', true);

        // 👉 Ici tu persist en DB (ModerationSetting) si tu veux.
        // On répond OK pour UI.
        return $this->json(['ok' => true, 'saved' => $data]);
    }
}