<?php

declare(strict_types=1);

namespace App\Controller\Web\Support;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('', name: 'support_')]
final class SupportController extends AbstractController
{
    #[Route('/centre-aide', name: 'center', methods: ['GET'])]
    public function center(): Response
    {
        return $this->render('support/center.html.twig', [
            'support_active' => 'center',
        ]);
    }

    #[Route('/faq', name: 'faq', methods: ['GET'])]
    public function faq(): Response
    {
        return $this->render('support/faq.html.twig', [
            'support_active' => 'faq',
            'faqs' => $this->getFaqs(),
        ]);
    }

    #[Route('/comment-ca-marche', name: 'how_it_works', methods: ['GET'])]
    public function howItWorks(): Response
    {
        return $this->render('support/how_it_works.html.twig', [
            'support_active' => 'how',
        ]);
    }

    #[Route('/boost-annonce', name: 'boost_announcement', methods: ['GET'])]
    public function boostAnnouncement(): Response
    {
        return $this->render('support/boost_announcement.html.twig', [
            'support_active' => 'boost',
            'boost_plans' => [
                [
                    'key' => 'essentiel',
                    'name' => 'Essentiel',
                    'price' => 200,
                    'duration' => '1 semaine',
                    'icon' => 'fa-bolt',
                    'tone' => 'green',
                    'features' => [
                        'Visibilité renforcée pendant 7 jours',
                        'Mise en avant dans les résultats',
                        'Renouvelable selon vos besoins',
                    ],
                ],
                [
                    'key' => 'plus',
                    'name' => 'Plus',
                    'price' => 500,
                    'duration' => '3 semaines',
                    'icon' => 'fa-star',
                    'tone' => 'blue',
                    'featured' => true,
                    'features' => [
                        'Visibilité prioritaire pendant 21 jours',
                        'Positionnement supérieur dans les résultats',
                        'Excellent équilibre durée et budget',
                    ],
                ],
                [
                    'key' => 'premium',
                    'name' => 'Premium',
                    'price' => 1000,
                    'duration' => '8 semaines',
                    'icon' => 'fa-crown',
                    'tone' => 'gold',
                    'features' => [
                        'Visibilité prioritaire pendant 56 jours',
                        'Priorité maximale parmi les annonces boostées',
                        'Durée fixe pour une campagne longue',
                    ],
                ],
            ],
        ]);
    }

    /**
     * @return list<array{question: string, answer: string, category: string}>
     */
    private function getFaqs(): array
    {
        return [
            [
                'question' => 'Comment créer un compte sur Main d’Œuvre Locale ?',
                'answer' => 'Cliquez sur « Inscription », renseignez vos informations, puis terminez la vérification demandée. Après votre première connexion, choisissez le type de compte correspondant à votre activité.',
                'category' => 'Compte',
            ],
            [
                'question' => 'Comment compléter ou modifier mon profil ?',
                'answer' => 'Ouvrez « Mon compte », puis « Mon profil ». Ajoutez une photo claire, votre localisation, vos compétences, votre expérience et les éléments utiles pour rassurer les autres utilisateurs.',
                'category' => 'Profil',
            ],
            [
                'question' => 'Comment publier une annonce ?',
                'answer' => 'Depuis votre espace, ouvrez la rubrique de gestion des annonces, cliquez sur « Nouvelle annonce », complétez le formulaire puis enregistrez. Une annonce précise obtient généralement de meilleurs contacts.',
                'category' => 'Annonces',
            ],
            [
                'question' => 'Comment publier une mission ou une offre ?',
                'answer' => 'Accédez à la section « Missions » ou à votre tableau de bord, puis utilisez le bouton de publication. Indiquez le métier recherché, la ville, les conditions, la rémunération et la date souhaitée.',
                'category' => 'Missions',
            ],
            [
                'question' => 'Comment trouver un talent ou un prestataire près de moi ?',
                'answer' => 'Utilisez la page « Talents » et ses filtres. Recherchez par profession, ville ou rayon géographique, puis consultez les profils et leurs informations avant de prendre contact.',
                'category' => 'Recherche',
            ],
            [
                'question' => 'Comment contacter un utilisateur ?',
                'answer' => 'Ouvrez son profil ou l’annonce concernée et utilisez l’action de contact disponible. Lorsque la messagerie est accessible, Colombe vous permet de retrouver et de poursuivre vos conversations.',
                'category' => 'Messagerie',
            ],
            [
                'question' => 'À quoi sert le boost d’annonce ?',
                'answer' => 'Le boost augmente temporairement la visibilité d’une annonce. Elle bénéficie d’une meilleure priorité dans les emplacements prévus afin de recevoir davantage de consultations et de contacts.',
                'category' => 'Boost',
            ],
            [
                'question' => 'Quels sont les tarifs du boost d’annonce ?',
                'answer' => 'Les offres disponibles sont : Essentiel à 200 FCFA pour 1 semaine, Plus à 500 FCFA pour 3 semaines et Premium à 1 000 FCFA pour 8 semaines.',
                'category' => 'Boost',
            ],
            [
                'question' => 'Comment modifier, masquer ou supprimer une annonce ?',
                'answer' => 'Dans « Mes annonces », ouvrez les actions de la ligne concernée. Vous pouvez modifier les informations, changer son état d’affichage ou utiliser l’action de suppression lorsque celle-ci est disponible.',
                'category' => 'Annonces',
            ],
            [
                'question' => 'Que faire en cas de contenu ou de comportement inapproprié ?',
                'answer' => 'Utilisez l’option de signalement disponible sur le contenu ou le profil concerné. Décrivez clairement le problème afin que l’équipe de modération puisse l’examiner rapidement.',
                'category' => 'Sécurité',
            ],
        ];
    }
}
