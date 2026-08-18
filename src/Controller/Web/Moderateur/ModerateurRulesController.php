<?php

namespace App\Controller\Web\Moderateur;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\ExpressionLanguage\Expression;

#[IsGranted(new Expression('is_granted("ROLE_MODERATEUR") or is_granted("ROLE_ADMIN") or is_granted("ROLE_SUPER_ADMIN")'))]
#[Route('/moderateur', name: 'moderateur_')]
class ModerateurRulesController extends AbstractController
{
    #[Route('/rules', name: 'rules', methods: ['GET'])]
    public function index(): Response
    {
        $rules = [
            ['code' => 'SPAM', 'title' => 'Spam & publicité', 'level' => 'High', 'desc' => 'Contenu répétitif, pubs non autorisées, redirections.'],
            ['code' => 'ABUSE', 'title' => 'Harcèlement & abus', 'level' => 'Critical', 'desc' => 'Insultes, menaces, intimidation, pression.'],
            ['code' => 'FRAUD', 'title' => 'Fraude', 'level' => 'Critical', 'desc' => 'Usurpation, arnaque, fausses informations.'],
            ['code' => 'NSFW', 'title' => 'Contenu sensible', 'level' => 'High', 'desc' => 'Contenu inapproprié (selon tes règles).'],
            ['code' => 'DATA', 'title' => 'Données personnelles', 'level' => 'Medium', 'desc' => 'Partage d’infos privées sans consentement.'],
        ];

        return $this->render('moderateur/rule.html.twig', [
            'rules' => $rules
        ]);
    }
}