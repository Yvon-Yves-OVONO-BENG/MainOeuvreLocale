<?php

namespace App\Controller\Web\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/kyc', name: 'admin_kyc_')]
#[IsGranted('ROLE_SUPER_ADMIN')]
final class AdminKycController extends AbstractController
{
    #[Route('/rules', name: 'rules', methods: ['GET'])]
    public function rules(): Response
    {
        $rules = [
            [
                'title' => 'Pièce d’identité lisible',
                'description' => 'Le document doit être net, complet et non tronqué.',
                'severity' => 'high',
            ],
            [
                'title' => 'Correspondance nom / document',
                'description' => 'Le nom saisi sur le compte doit correspondre au justificatif.',
                'severity' => 'medium',
            ],
            [
                'title' => 'Selfie de vérification',
                'description' => 'Le selfie doit être récent et cohérent avec la pièce fournie.',
                'severity' => 'high',
            ],
            [
                'title' => 'Vérification pays à risque',
                'description' => 'Contrôle renforcé sur certains profils ou pays sensibles.',
                'severity' => 'critical',
            ],
        ];

        return $this->render('admin/kyc/rules.html.twig', [
            'rules' => $rules,
        ]);
    }
}