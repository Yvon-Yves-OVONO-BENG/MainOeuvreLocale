<?php

namespace App\Controller\Web\Contact;

use App\Entity\User;
use App\Service\PlanManager;
use App\Service\ContactManager;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class ContactController extends AbstractController
{
    #[Route('/contact/show/{slug}', name: 'contact_show', methods: ['POST'], requirements: ['slug' => '[a-f0-9]{64}'])]
    public function showContact(
        #[MapEntity(mapping: ['slug' => 'slug'])] User $targetUser,
        PlanManager $planManager,
        ContactManager $contactManager,
        Request $request
    ): JsonResponse {
        $user = $this->getUser();
        
        // Vérifier que l'utilisateur ne se contacte pas lui-même
        if ($user->getId() === $targetUser->getId()) {
            return $this->json(['error' => 'Vous ne pouvez pas vous contacter vous-même.'], 403);
        }

        // Vérifier le plan de l'utilisateur
        $plan = $planManager->getCurrentPlan($user);
        $remainingContacts = $planManager->getRemainingContacts($user);

        if ($remainingContacts <= 0) {
            return $this->json([
                'error' => 'Vous avez atteint votre limite de contacts pour ce mois. Passez à un plan supérieur pour continuer.',
                'limit_reached' => true,
                'plan' => $plan?->getName() ?? 'Découverte',
                'remaining' => 0,
            ], 403);
        }

        // Récupérer le type de contact demandé
        $type = $request->request->get('type', 'email');
        $value = $type === 'phone' ? $targetUser->getPhone() : $targetUser->getEmail();

        if (!$value) {
            return $this->json(['error' => 'Information non disponible.'], 404);
        }

        // Enregistrer le contact utilisé
        $contactManager->logContact($user, $targetUser, $type);

        // Récupérer le nouveau nombre restant
        $newRemaining = $planManager->getRemainingContacts($user);

        return $this->json([
            'success' => true,
            'type' => $type,
            'value' => $value,
            'remaining' => $newRemaining,
            'plan' => $plan?->getName() ?? 'Découverte',
        ]);
    }

    #[Route('/contact/remaining', name: 'contact_remaining', methods: ['GET'])]
    public function getRemainingContacts(PlanManager $planManager): JsonResponse
    {
        $user = $this->getUser();
        $remaining = $planManager->getRemainingContacts($user);
        $plan = $planManager->getCurrentPlan($user);

        return $this->json([
            'remaining' => $remaining,
            'plan' => $plan?->getName() ?? 'Découverte',
            'max' => $plan?->getMaxContacts() ?? 3,
        ]);
    }
}