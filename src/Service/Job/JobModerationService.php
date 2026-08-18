<?php
// src/Service/Job/JobModerationService.php
namespace App\Service\Job;

use App\Entity\Job;
use App\Entity\StatusJob;
use App\Entity\User;
use App\Repository\JobRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\Translation\TranslatorInterface;

class JobModerationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private JobRepository $jobRepository,
        private MailerInterface $mailer,
        private TranslatorInterface $translator,
    ) {}

    /**
     * Approuve un job
     */
    public function approveJob(Job $job, User $admin, ?string $comment = null): void
    {
        $job->approve($admin, $comment);
        
        // Mettre le statut "publié"
        $statusRepo = $this->entityManager->getRepository(StatusJob::class);
        $publishedStatus = $statusRepo->findOneBy(['slug' => 'published']);
        if ($publishedStatus) {
            $job->setStatus($publishedStatus);
        }
        
        $this->entityManager->flush();
        
        // Envoyer notification au créateur
        $this->sendApprovalNotification($job);
    }

    /**
     * Rejette un job
     */
    public function rejectJob(Job $job, User $admin, string $reason): void
    {
        $job->reject($admin, $reason);
        
        // Mettre le statut "rejeté"
        $statusRepo = $this->entityManager->getRepository(StatusJob::class);
        $rejectedStatus = $statusRepo->findOneBy(['slug' => 'rejected']);
        if ($rejectedStatus) {
            $job->setStatus($rejectedStatus);
        }
        
        $this->entityManager->flush();
        
        // Envoyer notification au créateur
        $this->sendRejectionNotification($job, $reason);
    }

    /**
     * Modifie un job par l'admin
     */
    public function modifyJobByAdmin(Job $job, User $admin, string $note, array $modifications): void
    {
        // Appliquer les modifications
        foreach ($modifications as $field => $value) {
            if (property_exists($job, $field)) {
                $setter = 'set' . ucfirst($field);
                if (method_exists($job, $setter)) {
                    $job->$setter($value);
                }
            }
        }
        
        $job->markAsModified($admin, $note);
        $this->entityManager->flush();
        
        // Notifier le créateur
        $this->sendModificationNotification($job, $note);
    }

    /**
     * Récupère les jobs en attente de modération
     */
    public function getPendingJobs(): array
    {
        return $this->jobRepository->findBy(
            ['moderationStatus' => 'pending'],
            ['createdAt' => 'DESC']
        );
    }

    /**
     * Récupère les jobs modérés (approuvés/rejetés)
     */
    public function getModeratedJobs(): array
    {
        return $this->jobRepository->findBy(
            ['moderationStatus' => ['approved', 'rejected']],
            ['verifiedAt' => 'DESC']
        );
    }

    /**
     * Notification d'approbation
     */
    private function sendApprovalNotification(Job $job): void
    {
        $creator = $job->getCreatedBy();
        if (!$creator || !$creator->getEmail()) {
            return;
        }

        $email = (new Email())
            ->from('noreply@mol.com')
            ->to($creator->getEmail())
            ->subject('✅ Votre offre a été approuvée !')
            ->html($this->renderEmailTemplate('approval', $job));

        $this->mailer->send($email);
    }

    /**
     * Notification de rejet
     */
    private function sendRejectionNotification(Job $job, string $reason): void
    {
        $creator = $job->getCreatedBy();
        if (!$creator || !$creator->getEmail()) {
            return;
        }

        $email = (new Email())
            ->from('noreply@mol.com')
            ->to($creator->getEmail())
            ->subject('❌ Votre offre nécessite des modifications')
            ->html($this->renderEmailTemplate('rejection', $job, ['reason' => $reason]));

        $this->mailer->send($email);
    }

    /**
     * Notification de modification par admin
     */
    private function sendModificationNotification(Job $job, string $note): void
    {
        $creator = $job->getCreatedBy();
        if (!$creator || !$creator->getEmail()) {
            return;
        }

        $email = (new Email())
            ->from('noreply@mol.com')
            ->to($creator->getEmail())
            ->subject('📝 Votre offre a été modifiée par un administrateur')
            ->html($this->renderEmailTemplate('modification', $job, ['note' => $note]));

        $this->mailer->send($email);
    }

    private function renderEmailTemplate(string $type, Job $job, array $extra = []): string
    {
        // Templates d'emails à créer dans templates/emails/
        return match($type) {
            'approval' => "<h2>Félicitations !</h2><p>Votre offre \"{$job->getTitle()}\" a été approuvée et est maintenant visible.</p>",
            'rejection' => "<h2>Modifications requises</h2><p>Votre offre \"{$job->getTitle()}\" n'a pas été approuvée.</p><p><strong>Raison :</strong> {$extra['reason']}</p>",
            'modification' => "<h2>Offre modifiée</h2><p>Un administrateur a modifié votre offre \"{$job->getTitle()}\".</p><p><strong>Note :</strong> {$extra['note']}</p>",
            default => '',
        };
    }
}