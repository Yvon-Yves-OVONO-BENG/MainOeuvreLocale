<?php

namespace App\Controller\Web;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class ChatSettingsController extends AbstractController
{
    /**
     * Affiche les préférences personnelles du chat.
     * Les réglages sont enregistrés par les endpoints AJAX dédiés afin de ne
     * jamais recharger la conversation en cours.
     */
    #[Route('/chat/parametres', name: 'app_chat_settings', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('chat/settings.html.twig');
    }
}
