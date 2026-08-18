<?php

namespace App\Controller\Web\Moderateur;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\ExpressionLanguage\Expression;

#[IsGranted(new Expression('is_granted("ROLE_MODERATEUR") or is_granted("ROLE_ADMIN") or is_granted("ROLE_SUPER_ADMIN")'))]
#[Route('/moderateur/templates', name: 'moderateur_template_')]
class ModerateurTemplatesController extends AbstractController
{
    #[Route('/{slug}', name: 'show', methods: ['GET'])]
    public function show(string $slug): Response
    {
        $templates = [
            'warning' => [
                'title' => '⚠️ Avertissement',
                'blocks' => [
                    [
                        'title' => 'Avertissement – contenu inapproprié',
                        'text'  => "Bonjour,\n\nNous avons constaté un contenu non conforme à nos règles. Merci de le modifier/supprimer immédiatement.\n\nEn cas de récidive, des restrictions peuvent être appliquées.\n\nCordialement,\nÉquipe Modération",
                    ],
                    [
                        'title' => 'Avertissement – comportement abusif',
                        'text'  => "Bonjour,\n\nVotre comportement enfreint nos règles (harcèlement/abus). Merci de rester respectueux.\n\nProchain incident = sanction.\n\nCordialement,\nÉquipe Modération",
                    ],
                ]
            ],
            'review' => [
                'title' => '🔍 Demande d’informations',
                'blocks' => [
                    [
                        'title' => 'Vérification – informations manquantes',
                        'text'  => "Bonjour,\n\nPour finaliser la vérification, merci de nous transmettre :\n- Pièce/justificatif\n- Détails manquants (nom, adresse, etc.)\n\nDès réception, nous traitons rapidement.\n\nCordialement,\nÉquipe Modération",
                    ],
                    [
                        'title' => 'Vérification – incohérence détectée',
                        'text'  => "Bonjour,\n\nNous avons détecté une incohérence dans votre profil/données. Merci de confirmer ou corriger.\n\nSans réponse, l’accès peut être limité temporairement.\n\nCordialement,\nÉquipe Modération",
                    ],
                ]
            ],
            'closure' => [
                'title' => '✅ Clôture',
                'blocks' => [
                    [
                        'title' => 'Clôture – traité',
                        'text'  => "Bonjour,\n\nVotre demande a été traitée. Le dossier est clôturé.\n\nMerci,\nÉquipe Modération",
                    ],
                    [
                        'title' => 'Clôture – non fondé',
                        'text'  => "Bonjour,\n\nAprès analyse, le signalement n’est pas confirmé. Le dossier est clôturé.\n\nMerci,\nÉquipe Modération",
                    ],
                ]
            ],
        ];

        if (!isset($templates[$slug])) {
            throw $this->createNotFoundException('Template introuvable.');
        }

        return $this->render('moderateur/templates_show.html.twig', [
            'slug' => $slug,
            'tpl' => $templates[$slug],
        ]);
    }
}