<?php

namespace App\Controller\Web\Admin;

use App\Entity\Job;
use App\Entity\StatusJob;
use App\Entity\User;
use App\Repository\JobRepository;
use App\Service\Job\JobModerationNotificationService;
use App\Service\Newsletter\NewsletterService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\ExpressionLanguage\Expression;

#[IsGranted(new Expression('is_granted("ROLE_MODERATEUR") or is_granted("ROLE_ADMIN") or is_granted("ROLE_SUPER_ADMIN")'))]
class JobModerationController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private JobRepository $jobRepository,
        private JobModerationNotificationService $notificationService,
        private NewsletterService $offerAlertService,
    ) {
    }

    /**
     * Page principale de modération des offres
     */
    #[Route('/admin/jobs/moderation', name: 'admin_jobs_moderation')]
    public function index(): Response
    {
        // Charger les relations nécessaires en LEFT JOIN afin qu'une ancienne
        // référence orpheline ne bloque jamais toute la page de modération.
        $allJobs = $this->jobRepository->findAllForModeration();
    
        // Séparer pour le compteur
        $pendingJobs = array_filter($allJobs, function($job) {
            return $job->getModerationStatus() === 'pending';
        });
    
        return $this->render('admin/job_moderation.html.twig', [
            'pendingJobs' => $allJobs, // ✅ Envoyer tous les jobs au template
            'pendingCount' => count($pendingJobs),
        ]);
    }

    /**
     * Approuver un job (AJAX)
     */
    /**
 * Approuver un job (AJAX)
 */
#[Route('/admin/job/{slug}/approve', name: 'admin_job_approve', methods: ['POST'])]
public function approveJob(
    #[MapEntity(expr: 'repository.findOneForModeration(slug)')]
    Job $job,
    Request $request,
): JsonResponse
{
    if (!$this->isCsrfTokenValid('moderate_job', (string) $request->request->get('_token', ''))) {
        return $this->json(['success' => false, 'message' => 'Votre session a expiré. Rechargez la page puis réessayez.'], 403);
    }
    // Une publication ne doit pas annoncer un succès si l'échéance la masque aussitôt.
    $now = new \DateTimeImmutable();
    $expiration = $job->getDateExpirationAt();
    $requestedExpiration = trim((string) $request->request->get('expires_at', ''));
    if ($requestedExpiration !== '') {
        try {
            $expiration = (new \DateTime($requestedExpiration))->setTimezone(new \DateTimeZone(date_default_timezone_get()));
        } catch (\Exception) {
            return $this->json(['success' => false, 'message' => 'La date d’expiration est invalide.'], 422);
        }
    }
    if (!$expiration || $expiration <= $now) {
        return $this->json(['success' => false, 'message' => 'Cette offre est expirée. Rechargez la page puis choisissez une nouvelle échéance en cliquant sur Approuver.'], 422);
    }
    $publishedStatus = $this->entityManager->getRepository(StatusJob::class)->findPublished();
    if (!$publishedStatus) {
        return $this->json(['success' => false, 'message' => 'Le statut PUBLIÉE est absent de la base. Ajoutez ce statut avant de publier.'], 422);
    }
    /** @var User $admin */
    $admin = $this->getUser();
    $comment = $request->request->get('comment', '');

    // ✅ Accepter "pending" ET "modified"
    $allowedStatuses = ['pending', 'modified', 'rejected', 'approved'];
    if (!in_array($job->getModerationStatus(), $allowedStatuses)) {
        return new JsonResponse([
            'success' => false,
            'message' => 'Ce job ne peut pas être approuvé (statut actuel : ' . $job->getModerationStatus() . ').',
        ], 400);
    }

    $job->setDateExpirationAt($expiration);

    // Mettre à jour les informations de modération
    if (method_exists($job, 'setModerationStatus')) {
        $job->setModerationStatus('approved');
    }

    if (method_exists($job, 'setVerifiedBy')) {
        $job->setVerifiedBy($admin);
    }

    if (method_exists($job, 'setVerifiedAt')) {
        $job->setVerifiedAt(new \DateTime());
    }

    if ($comment && method_exists($job, 'setModerationComment')) {
        $job->setModerationComment($comment);
    }

    // Mettre le statut "PUBLIÉE"
    $statusRepo = $this->entityManager->getRepository(StatusJob::class);
    $publishedStatus = $statusRepo->findPublished();

    if ($publishedStatus) {
        $job->setStatus($publishedStatus);
    }

    $this->entityManager->flush();

    $offerAlertsSent = 0;
    try {
        $offerAlertsSent = $this->offerAlertService->sendJobPublishedNewsletter($job);
    } catch (\Throwable) {
        // La publication ne doit jamais échouer si le serveur d'email est indisponible.
    }

    // Envoyer notification au créateur
    try {
        $this->notificationService->sendJobModifiedNotification(
            $job,
            $admin,
            $comment ?: 'Votre offre a été approuvée et est maintenant publiée. Félicitations !'
        );
    } catch (\Throwable $e) {
        // Silencieux
    }

    return new JsonResponse([
        'success' => true,
        'message' => 'L\'offre a été approuvée et publiée avec succès.',
        'moderationStatus' => $job->getModerationStatus(),
        'verifiedBy' => $admin->getEmail(),
        'verifiedAt' => $job->getVerifiedAt()?->format('d/m/Y H:i'),
        'offerAlertsSent' => $offerAlertsSent,
    ]);
}

    /**
     * Rejeter un job (AJAX)
     */
    #[Route('/admin/job/{slug}/reject', name: 'admin_job_reject', methods: ['POST'])]
    public function rejectJob(
        #[MapEntity(expr: 'repository.findOneForModeration(slug)')]
        Job $job,
        Request $request,
    ): JsonResponse
    {
    
        /** @var User $admin */
        $admin = $this->getUser();
        $reason = $request->request->get('reason', 'Non conforme aux conditions de publication.');
    
        // ✅ Accepter le rejet pour les statuts "pending" et "modified"
        $allowedStatuses = ['pending', 'modified', 'approved'];
        if (!in_array($job->getModerationStatus(), $allowedStatuses)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Ce job ne peut pas être rejeté (statut actuel : ' . $job->getModerationStatus() . ').',
            ], 400);
        }
    
        // Mettre à jour les informations de modération
        if (method_exists($job, 'setModerationStatus')) {
            $job->setModerationStatus('rejected');
        }
    
        if (method_exists($job, 'setVerifiedBy')) {
            $job->setVerifiedBy($admin);
        }
    
        if (method_exists($job, 'setVerifiedAt')) {
            $job->setVerifiedAt(new \DateTime());
        }
    
        if (method_exists($job, 'setModerationComment')) {
            $job->setModerationComment($reason);
        }
    
        // Mettre le statut "REFUSÉE"
        $statusRepo = $this->entityManager->getRepository(StatusJob::class);
        $rejectedStatus = $statusRepo->findOneBy(['statusJob' => 'REFUSÉE']);
    
        if ($rejectedStatus) {
            $job->setStatus($rejectedStatus);
        }
    
        $this->entityManager->flush();
    
        // Envoyer notification au créateur
        try {
            $this->notificationService->sendJobModifiedNotification(
                $job,
                $admin,
                "Votre offre n'a malheureusement pas été retenue pour la raison suivante : {$reason}"
            );
        } catch (\Throwable $e) {
            // Silencieux - logger si nécessaire
        }
    
        return new JsonResponse([
            'success' => true,
            'message' => 'L\'offre a été rejetée avec succès.',
            'moderationStatus' => $job->getModerationStatus(),
        ]);
    }

    /**
     * Modifier un job par l'admin (AJAX)
     */
    #[Route('/admin/job/{slug}/modify', name: 'admin_job_modify', methods: ['POST'])]
    public function modifyJob(
        #[MapEntity(expr: 'repository.findOneForModeration(slug)')]
        Job $job,
        Request $request,
    ): JsonResponse
    {
        /** @var User $admin */
        $admin = $this->getUser();
        $note = $request->request->get('note', 'Modification par l\'équipe de modération.');
        $modifications = $request->request->all('modifications');

        // Appliquer les modifications
        $modifiedFields = [];
        foreach ($modifications as $field => $value) {
            if (empty($value)) {
                continue;
            }

            $setter = 'set' . ucfirst($field);
            if (method_exists($job, $setter)) {
                // Conversion de type si nécessaire
                if (in_array($field, ['salaireMin', 'salaireMax'])) {
                    $value = (int) $value;
                }
                
                $job->$setter($value);
                $modifiedFields[] = $field;
            }
        }

        if (empty($modifiedFields)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Aucune modification valide fournie.',
            ], 400);
        }

        // Marquer comme modifié par l'admin
        if (method_exists($job, 'setModerationStatus')) {
            $job->setModerationStatus('modified');
        }

        if (method_exists($job, 'setModifiedByAdmin')) {
            $job->setModifiedByAdmin($admin);
        }

        if (method_exists($job, 'setModifiedByAdminAt')) {
            $job->setModifiedByAdminAt(new \DateTime());
        }

        if (method_exists($job, 'setAdminModificationNote')) {
            $job->setAdminModificationNote($note);
        }

        $this->entityManager->flush();

        // Envoyer notification au créateur
        try {
            $this->notificationService->sendJobModifiedNotification($job, $admin, $note);
        } catch (\Throwable $e) {
            // Silencieux
        }

        return new JsonResponse([
            'success' => true,
            'message' => 'L\'offre a été modifiée avec succès. Le créateur a été notifié.',
            'moderationStatus' => $job->getModerationStatus(),
            'modifiedFields' => $modifiedFields,
        ]);
    }

    /**
     * Voir les détails d'un job (pour la modération)
     */
    #[Route('/admin/job/{slug}/details', name: 'admin_job_details')]
    public function details(
        #[MapEntity(expr: 'repository.findOneForModeration(slug)')]
        Job $job,
    ): Response
    {
        return $this->render('admin/job_details.html.twig', [
            'job' => $job,
        ]);
    }

    /**
     * Tableau de bord de modération avec statistiques
     */
    #[Route('/admin/jobs/moderation/dashboard', name: 'admin_jobs_moderation_dashboard')]
    public function dashboard(): Response
    {
        // Statistiques
        $stats = [
            'pending' => $this->jobRepository->count(['moderationStatus' => 'pending']),
            'approved' => $this->jobRepository->count(['moderationStatus' => 'approved']),
            'rejected' => $this->jobRepository->count(['moderationStatus' => 'rejected']),
            'modified' => $this->jobRepository->count(['moderationStatus' => 'modified']),
            'total' => $this->jobRepository->count([]),
        ];

        // Derniers jobs modérés
        $recentModerated = $this->jobRepository->createQueryBuilder('j')
            ->where('j.moderationStatus IN (:statuses)')
            ->setParameter('statuses', ['approved', 'rejected', 'modified'])
            ->orderBy('j.verifiedAt', 'DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        return $this->render('admin/job_moderation_dashboard.html.twig', [
            'stats' => $stats,
            'recentModerated' => $recentModerated,
        ]);
    }

    /**
     * Approuver plusieurs jobs en masse
     */
    #[Route('/admin/jobs/batch-approve', name: 'admin_jobs_batch_approve', methods: ['POST'])]
    public function batchApprove(Request $request): JsonResponse
    {
        /** @var User $admin */
        $admin = $this->getUser();
        $jobSlugs = $request->request->all('job_ids');

        if (empty($jobSlugs)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Aucun job sélectionné.',
            ], 400);
        }

        $jobs = $this->jobRepository->findBy(['slug' => $jobSlugs]);
        $approvedCount = 0;
        $approvedJobs = [];

        $publishedStatus = $this->entityManager
            ->getRepository(StatusJob::class)
            ->findPublished();

        if (!$publishedStatus) {
            return $this->json(['success' => false, 'message' => 'Le statut PUBLIÉE est absent de la base.'], 422);
        }
        foreach ($jobs as $job) {
            if ($job->getModerationStatus() === 'pending' && (!$job->getDateExpirationAt() || $job->getDateExpirationAt() <= new \DateTimeImmutable())) {
                return $this->json(['success' => false, 'message' => 'La sélection contient des offres expirées. Approuvez-les individuellement pour choisir une nouvelle échéance.'], 422);
            }
        }

        foreach ($jobs as $job) {
            if ($job->getModerationStatus() === 'pending') {
                if (method_exists($job, 'setModerationStatus')) {
                    $job->setModerationStatus('approved');
                }
                if (method_exists($job, 'setVerifiedBy')) {
                    $job->setVerifiedBy($admin);
                }
                if (method_exists($job, 'setVerifiedAt')) {
                    $job->setVerifiedAt(new \DateTime());
                }
                if ($publishedStatus) {
                    $job->setStatus($publishedStatus);
                }
                $approvedCount++;
                $approvedJobs[] = $job;

                // Notification au créateur
                try {
                    $this->notificationService->sendJobModifiedNotification(
                        $job,
                        $admin,
                        'Votre offre a été approuvée et est maintenant publiée. Félicitations !'
                    );
                } catch (\Throwable $e) {
                    // Silencieux
                }
            }
        }

        $this->entityManager->flush();

        $offerAlertsSent = 0;
        foreach ($approvedJobs as $approvedJob) {
            try {
                $offerAlertsSent += $this->offerAlertService->sendJobPublishedNewsletter($approvedJob);
            } catch (\Throwable) {
                // La validation en masse reste réussie même si un email échoue.
            }
        }

        return new JsonResponse([
            'success' => true,
            'message' => "$approvedCount offre(s) approuvée(s) avec succès.",
            'approvedCount' => $approvedCount,
            'offerAlertsSent' => $offerAlertsSent,
        ]);
    }
    
    #[Route('/admin/jobs/batch-reject', name: 'admin_jobs_batch_reject', methods: ['POST'])]
    public function batchReject(Request $request): JsonResponse
    {
        /** @var User $admin */
        $admin = $this->getUser();
        $jobSlugs = $request->request->all('job_ids');
        $reason = $request->request->get('reason', 'Non conforme aux conditions de publication.');
    
        if (empty($jobSlugs)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Aucun job sélectionné.',
            ], 400);
        }
    
        $jobs = $this->jobRepository->findBy(['slug' => $jobSlugs]);
        $rejectedCount = 0;
    
        $rejectedStatus = $this->entityManager
            ->getRepository(StatusJob::class)
            ->findOneBy(['statusJob' => 'REFUSÉE']);
    
        foreach ($jobs as $job) {
            $allowedStatuses = ['pending', 'modified'];
            if (in_array($job->getModerationStatus(), $allowedStatuses)) {
                if (method_exists($job, 'setModerationStatus')) {
                    $job->setModerationStatus('rejected');
                }
                if (method_exists($job, 'setVerifiedBy')) {
                    $job->setVerifiedBy($admin);
                }
                if (method_exists($job, 'setVerifiedAt')) {
                    $job->setVerifiedAt(new \DateTime());
                }
                if (method_exists($job, 'setModerationComment')) {
                    $job->setModerationComment($reason);
                }
                if ($rejectedStatus) {
                    $job->setStatus($rejectedStatus);
                }
                $rejectedCount++;
    
                // Notification au créateur
                try {
                    $this->notificationService->sendJobModifiedNotification(
                        $job,
                        $admin,
                        "Votre offre n'a malheureusement pas été retenue pour la raison suivante : {$reason}"
                    );
                } catch (\Throwable $e) {
                    // Silencieux
                }
            }
        }
    
        $this->entityManager->flush();
    
        return new JsonResponse([
            'success' => true,
            'message' => "$rejectedCount offre(s) rejetée(s) avec succès.",
            'rejectedCount' => $rejectedCount,
        ]);
    }
}
