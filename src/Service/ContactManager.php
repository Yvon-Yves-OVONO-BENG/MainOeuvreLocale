<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\ContactLog;
use App\Repository\ContactLogRepository;
use Doctrine\ORM\EntityManagerInterface;

class ContactManager
{
    public function __construct(
        private EntityManagerInterface $em,
        private ContactLogRepository $contactLogRepository,
        private PlanManager $planManager,
        private ContactTicketManager $ticketManager,
    ) {}

    // Dans ContactManager::logContact()
    public function logContact(User $user, User $targetUser, string $type, ?\App\Entity\ContactTicket $ticket = null): void
    {
        // Log pour vérifier que la méthode est appelée
        error_log('🔍 logContact appelée');
        error_log('   User: ' . $user->getId() . ' - ' . $user->getEmail());
        error_log('   Target: ' . $targetUser->getId());
        error_log('   Type: ' . $type);
        
        if ($this->contactLogRepository->hasContactedThisMonth($user, $targetUser, $type)) {
            error_log('⚠️ Contact déjà enregistré ce mois-ci');
            return;
        }
    
        $log = new ContactLog();
        $log->setUser($user);
        $log->setTargetUser($targetUser);
        $log->setType($type);
        $log->setTicket($ticket);
        $log->setCreatedAt(new \DateTime());
    
        $this->em->persist($log);
        $this->em->flush();
        
        error_log('✅ Contact enregistré avec ID: ' . $log->getId());
    }

    /**
     * Autorise et journalise l'affichage d'un contact.
     * Le quota du plan est utilisé en priorité. Une fois épuisé, un ticket ne
     * fonctionne que pour la profession choisie lors de son achat.
     *
     * @return array{allowed: bool, reason?: string, ticket_remaining: int}
     */
    public function authorizeContact(User $user, User $targetUser, string $type): array
    {
        if ($this->contactLogRepository->hasContactedThisMonth($user, $targetUser)
            || $this->contactLogRepository->hasTicketAccess($user, $targetUser)) {
            return [
                'allowed' => true,
                'ticket_remaining' => $this->ticketManager->remaining($user),
            ];
        }

        if ($this->planManager->getRemainingContacts($user) > 0) {
            $this->logContact($user, $targetUser, $type);
            return [
                'allowed' => true,
                'ticket_remaining' => $this->ticketManager->remaining($user),
            ];
        }

        $profession = $targetUser->getProfessionalProfile()?->getProfession();
        if (!$profession) {
            return [
                'allowed' => false,
                'reason' => 'Ce profil ne possède pas encore de profession permettant d’utiliser un ticket.',
                'ticket_remaining' => $this->ticketManager->remaining($user),
            ];
        }

        $ticket = $this->ticketManager->consume($user, $profession);
        if (!$ticket) {
            return [
                'allowed' => false,
                'reason' => sprintf(
                    'Aucun ticket disponible pour la profession « %s ». Achetez un ticket à 200 FCFA pour débloquer 3 contacts de cette profession.',
                    $profession->getProfession()
                ),
                'ticket_remaining' => $this->ticketManager->remaining($user),
            ];
        }

        $this->logContact($user, $targetUser, $type, $ticket);

        return [
            'allowed' => true,
            'ticket_remaining' => $this->ticketManager->remaining($user, $profession),
        ];
    }

    public function getMonthlyUsage(User $user): int
    {
        return $this->contactLogRepository->countMonthlyUsage($user);
    }

    public function getRemainingContacts(User $user): int
    {
        $plan = $this->planManager->getCurrentPlan($user);
        $maxContacts = $plan?->getMaxContacts() ?? 3;
        $used = $this->getMonthlyUsage($user);

        if ($maxContacts >= 99999) {
            return PHP_INT_MAX;
        }

        return max(0, $maxContacts - $used);
    }

    public function hasReachedLimit(User $user): bool
    {
        $plan = $this->planManager->getCurrentPlan($user);
        $maxContacts = $plan?->getMaxContacts() ?? 3;
        
        if ($maxContacts >= 99999) {
            return false;
        }
        
        return $this->contactLogRepository->hasReachedMonthlyLimit($user, $maxContacts);
    }

    public function getRecentContacts(User $user, int $limit = 10): array
    {
        return $this->contactLogRepository->findRecentContacts($user, $limit);
    }

    public function getMonthlyStats(User $user, int $months = 6): array
    {
        return $this->contactLogRepository->getMonthlyStats($user, $months);
    }
}
