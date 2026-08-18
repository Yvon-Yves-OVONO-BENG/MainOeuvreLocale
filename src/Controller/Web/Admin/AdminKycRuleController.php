<?php

namespace App\Controller\Web\Admin;

use App\Entity\KycRule;
use App\Repository\KycRuleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use App\Form\KycRuleType;

#[Route('/admin/kyc', name: 'admin_kyc_')]
class AdminKycRuleController extends AbstractController
{
    #[Route('/rules', name: 'rules', methods: ['GET'])]
    public function rules(KycRuleRepository $repo): Response
    {
        $rules = $repo->findAllOrdered();

        $stats = [
            'total' => count($rules),
            'enabled' => $repo->countEnabled(),
            'high' => $repo->countHigh(),
            'groups' => $repo->countGroups(),
            'disabled' => $repo->countDisabled(),
        ];

        return $this->render('admin/kyc/kyc_rules.html.twig', [
            'rules' => $rules,
            'stats' => $stats,
            'groups' => $repo->findDistinctGroups(),
            'csrf_toggle' => $this->container->get('security.csrf.token_manager')->getToken('admin_kyc_toggle')->getValue(),
        ]);
    }

    #[Route('/rules/{id}/toggle', name: 'toggle', methods: ['POST'])]
    public function toggle(
        Request $request,
        KycRule $rule,
        EntityManagerInterface $em
    ): JsonResponse {
        $token = (string) $request->request->get('_token');

        if (!$this->isCsrfTokenValid('admin_kyc_toggle', $token)) {
            return $this->json([
                'ok' => false,
                'error' => 'Jeton CSRF invalide.',
            ], 400);
        }

        $rule->setEnabled(!$rule->isEnabled());
        $rule->setUpdatedAt(new \DateTime());

        $em->flush();

        return $this->json([
            'ok' => true,
            'enabled' => $rule->isEnabled(),
            'id' => $rule->getId(),
        ]);
    }


    #[Route('/rules/{id}/modal/edit', name: 'modal_edit', methods: ['GET', 'POST'])]
    public function modalEdit(
        Request $request,
        KycRule $rule,
        EntityManagerInterface $em
    ): Response {
        $form = $this->createForm(KycRuleType::class, $rule, [
            'action' => $this->generateUrl('admin_kyc_modal_edit', ['id' => $rule->getId()]),
            'method' => 'POST',
        ]);

        $form->handleRequest($request);

        if ($request->isMethod('POST') && $form->isSubmitted()) {
            if ($form->isValid()) {
                $rule->setUpdatedAt(new \DateTime());
                $em->flush();

                return $this->json([
                    'ok' => true,
                    'message' => 'Règle mise à jour avec succès.',
                    'rule' => [
                        'id' => $rule->getId(),
                        'label' => $rule->getLabel(),
                        'description' => $rule->getDescription(),
                        'groupName' => $rule->getGroupName(),
                        'level' => $rule->getLevel(),
                        'enabled' => $rule->isEnabled(),
                        'ruleKey' => $rule->getRuleKey(),
                        'updatedAt' => $rule->getUpdatedAt()?->format('d/m/Y H:i'),
                    ],
                ]);
            }

            $html = $this->renderView('admin/kyc/_modal_edit.html.twig', [
                'rule' => $rule,
                'form' => $form->createView(),
            ]);

            return new Response($html, 422);
        }

        return $this->render('admin/kyc/_modal_edit.html.twig', [
            'rule' => $rule,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/rules/{id}/modal/history', name: 'modal_history', methods: ['GET'])]
    public function modalHistory(KycRule $rule): Response
    {
        // Historique simple tant qu'il n'y a pas d'entité d'audit dédiée.
        $history = [
            [
                'label' => 'Création de la règle',
                'value' => $rule->getCreatedAt()?->format('d/m/Y H:i') ?: '—',
                'tone' => 'slate',
            ],
            [
                'label' => 'Dernière mise à jour',
                'value' => $rule->getUpdatedAt()?->format('d/m/Y H:i') ?: 'Jamais modifiée',
                'tone' => 'indigo',
            ],
            [
                'label' => 'État actuel',
                'value' => $rule->isEnabled() ? 'Active' : 'Inactive',
                'tone' => $rule->isEnabled() ? 'emerald' : 'rose',
            ],
            [
                'label' => 'Niveau de contrôle',
                'value' => strtoupper($rule->getLevel()),
                'tone' => $rule->getLevel() === 'high' ? 'rose' : ($rule->getLevel() === 'medium' ? 'amber' : 'slate'),
            ],
        ];

        return $this->render('admin/kyc/_modal_history.html.twig', [
            'rule' => $rule,
            'history' => $history,
        ]);
    }

    #[Route('/rules/{id}/modal/impact', name: 'modal_impact', methods: ['GET'])]
    public function modalImpact(KycRule $rule): Response
    {
        $impact = [
            'scope' => match ($rule->getGroupName()) {
                'Talents' => 'Profils talent, validation pro, candidatures',
                'Particuliers' => 'Profils particuliers et accès restreints',
                'Compagnies' => 'Vérification entreprise, publication et conformité',
                'Offres' => 'Publication d’offres et visibilité',
                'Risque' => 'Auto-review, contrôle fraude, modération',
                default => 'Comportement global de la plateforme',
            },
            'effect' => match ($rule->getLevel()) {
                'high' => 'Impact fort : peut bloquer ou retarder des actions critiques.',
                'medium' => 'Impact moyen : ajoute des contrôles avant certaines actions.',
                default => 'Impact léger : règle informative ou complémentaire.',
            },
            'enabled' => $rule->isEnabled(),
        ];

        return $this->render('admin/kyc/_modal_impact.html.twig', [
            'rule' => $rule,
            'impact' => $impact,
        ]);
    }
}