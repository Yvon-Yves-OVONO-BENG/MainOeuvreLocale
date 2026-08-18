<?php

namespace App\Controller\Web\Boost;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class BoostSuccessController extends AbstractController
{
   #[Route('/boost/success', name: 'boost_payment_success', methods: ['GET'])]
    public function boostSuccess(): Response
    {
        $this->addFlash('success', 'Paiement effectué. Votre profil est boosté ✅');
        return $this->redirectToRoute('tableau_de_bord');
    }

}
