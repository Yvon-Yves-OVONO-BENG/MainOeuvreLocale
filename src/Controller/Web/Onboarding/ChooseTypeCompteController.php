<?php

namespace App\Controller\Web\Onboarding;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ChooseTypeCompteController extends AbstractController
{
    #[Route('/onboarding/type-compte', name: 'app_onboarding_type_compte', methods: ['GET', 'POST'])]
    public function __invoke(): Response
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if ($user->getProfileType() !== null && $user->getProfileType() !== '') {
            return $this->redirectToRoute('profile_edit');
        }

        // Ancienne URL conservée pour compatibilité, sans afficher l'ancien menu déroulant.
        return $this->redirectToRoute('profile_type_choice');
    }
}
