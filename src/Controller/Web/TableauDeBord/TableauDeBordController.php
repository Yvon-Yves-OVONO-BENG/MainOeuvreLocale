<?php

namespace App\Controller\Web\TableauDeBord;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[IsGranted('ROLE_USER', message: 'Accès refusé. Connectez-vous')]
class TableauDeBordController extends AbstractController
{
    #[Route('/tableau-de-bord', name: 'tableau_de_bord')]
    public function index(): Response
    {
        $user = $this->getUser();

        // ✅ Vérifier si l'utilisateur a choisi son type de profil
        if ($user->getProfileType() === null || $user->getProfileType() === '') {
            return $this->redirectToRoute('profile_type_choice');
        }

        // Redirection selon le rôle
        if (in_array('ROLE_COMPANY', $user->getRoles(), true)) {
            return $this->redirectToRoute('tableau_de_bord_company');
        }

        if (in_array('ROLE_TALENT', $user->getRoles(), true)) {
            return $this->redirectToRoute('tableau_de_bord_talent');
        }

        if (in_array('ROLE_PARTICULIER', $user->getRoles(), true)) {
            return $this->redirectToRoute('tableau_de_bord_particulier');
        }

        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return $this->redirectToRoute('tableau_de_bord_administrateur');
        }

        if (in_array('ROLE_SUPER_ADMIN', $user->getRoles(), true)) {
            return $this->redirectToRoute('tableau_de_bord_super_administrateur');
        }

        if (in_array('ROLE_MODERATEUR', $user->getRoles(), true)) {
            return $this->redirectToRoute('tableau_de_bord_moderateur');
        }

        // ✅ Fallback : si aucun rôle spécifique, rediriger vers le choix
        return $this->redirectToRoute('profile_type_choice');
    }
}