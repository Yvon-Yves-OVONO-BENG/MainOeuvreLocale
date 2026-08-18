<?php

namespace App\Controller\Web\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/faq', name: 'admin_faq_')]
class AdminFaqController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        $this->denyUnlessAdminOrSuperAdmin();

        $faqs = [
            [
                'id' => 1,
                'question' => 'Pourquoi mon paiement est validé mais mon annonce n’apparaît pas ?',
                'answer' => 'Dans certains cas, la validation du paiement précède l’indexation de l’annonce. Le délai est généralement court. Si le problème persiste, le support vérifie la transaction et republie si nécessaire.',
                'category' => 'Paiement',
                'updatedAt' => new \DateTimeImmutable('-2 days'),
                'tags' => ['paiement', 'annonce', 'publication'],
            ],
            [
                'id' => 2,
                'question' => 'Comment réinitialiser un compte bloqué ?',
                'answer' => 'Le support vérifie l’identité du titulaire, contrôle les tentatives suspectes puis déclenche soit un déblocage manuel, soit une procédure sécurisée de réinitialisation.',
                'category' => 'Compte',
                'updatedAt' => new \DateTimeImmutable('-4 days'),
                'tags' => ['compte', 'sécurité'],
            ],
            [
                'id' => 3,
                'question' => 'Sous quel délai un remboursement est-il traité ?',
                'answer' => 'Le traitement dépend du motif, du provider et du statut de la transaction. Une validation interne est effectuée avant émission du remboursement.',
                'category' => 'Remboursement',
                'updatedAt' => new \DateTimeImmutable('-1 week'),
                'tags' => ['remboursement', 'billing'],
            ],
            [
                'id' => 4,
                'question' => 'Pourquoi une publication peut-elle être rejetée ?',
                'answer' => 'Une annonce peut être rejetée pour non-conformité, contenu incomplet, incohérence métier, duplicata ou infraction aux règles de plateforme.',
                'category' => 'Modération',
                'updatedAt' => new \DateTimeImmutable('-3 days'),
                'tags' => ['modération', 'règles', 'publication'],
            ],
        ];

        return $this->render('admin/faq/faq.html.twig', [
            'faqs' => $faqs,
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