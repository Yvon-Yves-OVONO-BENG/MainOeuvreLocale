<?php

namespace App\Controller\Web\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/templates', name: 'admin_templates_')]
class AdminTemplateController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        $this->denyUnlessAdminOrSuperAdmin();

        $templates = [
            [
                'id' => 1,
                'title' => 'Paiement validé mais annonce non visible',
                'category' => 'Paiement',
                'tone' => 'Pro',
                'subject' => 'Suivi de votre annonce après paiement',
                'body' => "Bonjour,\n\nNous confirmons la bonne réception de votre paiement. Votre annonce est actuellement en cours de synchronisation/validation. Si elle n’apparaît pas encore, notre équipe procède à une vérification immédiate.\n\nMerci pour votre patience.",
            ],
            [
                'id' => 2,
                'title' => 'Compte temporairement bloqué',
                'category' => 'Compte',
                'tone' => 'Empathique',
                'subject' => 'Vérification de sécurité sur votre compte',
                'body' => "Bonjour,\n\nUne vérification de sécurité a été déclenchée sur votre compte. Afin de vous assister rapidement, merci de confirmer l’adresse email liée au compte ainsi que le contexte du blocage constaté.\n\nNous revenons vers vous dès validation.",
            ],
            [
                'id' => 3,
                'title' => 'Demande de remboursement en cours',
                'category' => 'Remboursement',
                'tone' => 'Pro',
                'subject' => 'Traitement de votre demande de remboursement',
                'body' => "Bonjour,\n\nVotre demande a bien été reçue. Elle est en cours d’analyse par notre équipe administrative. Le délai de traitement dépend du provider et du statut final de la transaction.\n\nNous vous tiendrons informé.",
            ],
            [
                'id' => 4,
                'title' => 'Publication rejetée',
                'category' => 'Modération',
                'tone' => 'Ferme',
                'subject' => 'Votre publication nécessite des ajustements',
                'body' => "Bonjour,\n\nAprès vérification, votre publication ne peut pas être validée en l’état. Nous vous invitons à corriger les informations manquantes ou non conformes, puis à soumettre à nouveau votre contenu.\n\nNotre équipe reste disponible si besoin.",
            ],
        ];

        return $this->render('admin/templates/templates.html.twig', [
            'templates' => $templates,
            'categories' => ['Tous', 'Paiement', 'Compte', 'Remboursement', 'Modération'],
        ]);
    }

    private function denyUnlessAdminOrSuperAdmin(): void
    {
        if (!$this->isGranted('ROLE_ADMIN') && !$this->isGranted('ROLE_SUPER_ADMIN')) {
            throw $this->createAccessDeniedException('Accès refusé.');
        }
    }
}