<?php

namespace App\Controller\Web;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[IsGranted('ROLE_USER', message: 'Accès refusé. Connectez-vous')]
class PaymentBoostController extends AbstractController
{
    #[Route('/payment-boost/{plan}/{montant}', name: 'payment_boost')]
    public function paymentBoost($plan = "", $montant = ""): Response
    {
        return $this->render('payment_boost/payment_boost.html.twig', [
            'plan' => $plan,
            'montant' => $montant,

        ]);
    }
}
