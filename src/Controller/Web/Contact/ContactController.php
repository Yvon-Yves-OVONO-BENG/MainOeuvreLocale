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
        // Récupérer le type de contact demandé
        $type = $request->request->get('type', 'email');
        if (!in_array($type, ['email', 'phone'], true)) {
            return $this->json(['error' => 'Type de contact invalide.'], 400);
        }
        $value = $type === 'phone' ? $targetUser->getPhone() : $targetUser->getEmail();

        if (!$value) {
            return $this->json(['error' => 'Information non disponible.'], 404);
        }

        $access = $contactManager->authorizeContact($user, $targetUser, $type);
        if (!$access['allowed']) {
            $categorieName = $targetUser->getProfessionalProfile()?->getProfession()?->getCategorie()?->getNom();
            return $this->json([
                'error' => $categorieName
                    ? sprintf('Aucun ticket disponible pour la catégorie « %s ». Achetez un ticket à 200 FCFA pour débloquer 3 contacts de cette catégorie.', $categorieName)
                    : 'Aucun ticket disponible pour la catégorie de ce profil.',
                'limit_reached' => true,
                'ticket_required' => true,
                'ticket_price' => 200,
                'ticket_contacts' => 3,
                'buy_url' => $this->generateUrl('subscription_choose', ['slug' => 'pro']),
                'plan' => $plan?->getName() ?? 'Découverte',
                'remaining' => 0,
                'ticket_remaining' => $access['ticket_remaining'],
            ], 403);
        }

        // Récupérer le nouveau nombre restant
        $newRemaining = $planManager->getRemainingContacts($user);

        return $this->json([
            'success' => true,
            'type' => $type,
            'value' => $value,
            'remaining' => $newRemaining,
            'ticket_remaining' => $access['ticket_remaining'],
            'plan' => $plan?->getName() ?? 'Découverte',
        ]);
    }

    #[Route('/contact/remaining', name: 'contact_remaining', methods: ['GET'])]
    public function getRemainingContacts(PlanManager $planManager, \App\Service\ContactTicketManager $ticketManager): JsonResponse
    {
        $user = $this->getUser();
        $remaining = $planManager->getRemainingContacts($user);
        $plan = $planManager->getCurrentPlan($user);

        return $this->json([
            'remaining' => $remaining,
            'plan' => $plan?->getName() ?? 'Découverte',
            'max' => $plan?->getMaxContacts() ?? 3,
            'ticket_remaining' => $ticketManager->remaining($user),
            'ticket_price' => 200,
            'ticket_contacts' => 3,
        ]);
    }
}
