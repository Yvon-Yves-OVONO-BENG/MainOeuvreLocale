<?php

namespace App\Controller\Web\Job;

use App\Entity\Job;
use App\Entity\StatusJob;
use App\Entity\User;
use App\Form\JobType;
use App\Service\Job\JobManagerService;
use App\Service\Job\JobModerationService;
use App\Service\Job\JobModerationNotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class AjouterJobController extends AbstractController
{
    public function __construct(
        private TranslatorInterface $translator,
        private CsrfTokenManagerInterface $csrfTokenManager,
        private JobModerationService $moderationService,
        private JobModerationNotificationService $notificationService,
    ) {
    }

    /*
     * La création et la modification sont désormais séparées par leur URL :
     * - /jobs-new              : création ;
     * - /jobs/{slug}/edit      : modification par identifiant public opaque ;
     * - /jobs-new/{slug}       : ancienne URL conservée pour ne casser aucun lien.
     */
    #[Route('/jobs-new', name: 'job_new', methods: ['GET', 'POST'])]
    #[Route(
        '/jobs/{slug}/edit',
        name: 'job_edit',
        requirements: ['slug' => '[a-f0-9]{64}'],
        methods: ['GET', 'POST']
    )]
    #[Route(
        '/jobs-new/{slug}',
        name: 'job_edit_legacy',
        methods: ['GET', 'POST']
    )]
    public function new(
        Request $request,
        JobManagerService $jobManagerService,
        EntityManagerInterface $entityManager,
        ?string $slug = null,
    ): Response {
        $slug = trim((string) $slug);

        /** @var User|null $user */
        $user = $this->getUser();

        $isAdmin = $user && $this->isGranted('ROLE_ADMIN');
        $isModerator = $user && $this->isGranted('ROLE_MODERATEUR');
        $isStaff = $isAdmin || $isModerator;

        /*
         * IMPORTANT : une modification charge toujours l'entité au moyen de
         * son identifiant public. L'identifiant Doctrine n'entre jamais dans l'URL.
         */
        if ($slug !== '') {
            $job = $entityManager
                ->getRepository(Job::class)
                ->findOneBy(['slug' => $slug]);

            if (!$job instanceof Job) {
                throw $this->createNotFoundException(
                    sprintf('Aucune offre correspondant au slug « %s ».', $slug)
                );
            }
        } else {
            $job = new Job();
            $jobManagerService->initializeJob($job, $user);
        }

        $isNewJob = $job->getId() === null;
        $originalModerationStatus = $job->getModerationStatus();

        /*
         * Gestion du modèle de brief.
         * Il ne doit jamais être réappliqué sur une offre déjà enregistrée.
         */
        $briefSlug = trim((string) $request->query->get('brief', ''));
        $selectedBrief = $jobManagerService->getSelectedBrief($briefSlug);

        if ($briefSlug !== '' && !$selectedBrief) {
            $this->addFlash(
                'toast_error',
                'Le modèle de brief demandé est introuvable.'
            );
        }

        if ($isNewJob && $selectedBrief && $request->isMethod('GET')) {
            $jobManagerService->applyBriefTemplate($job, $selectedBrief);
        }

        /*
         * En modification, le formulaire POST toujours vers l'URL contenant
         * l'identifiant du job. Un slug vide ne peut donc plus créer un doublon.
         */
        $formAction = $this->generateUrl(
            $isNewJob ? 'job_new' : 'job_edit',
            $isNewJob ? [] : ['slug' => $job->getSlug()]
        );

        $form = $this->createForm(JobType::class, $job, [
            'action' => $formAction,
            'method' => 'POST',
            'attr' => [
                'class' => 'mol-form',
                'data-turbo' => 'false',
                'data-job-validation' => '1',
            ],
            'is_staff' => $isStaff,
        ]);

        $form->handleRequest($request);

        $csrfToken = $this->csrfTokenManager
            ->getToken('ajouter_job')
            ->getValue();

        if ($form->isSubmitted() && $form->isValid()) {
            $csrfTokenFormulaire = (string) $request->request->get(
                'csrfToken',
                ''
            );

            if (!$this->csrfTokenManager->isTokenValid(
                new CsrfToken('ajouter_job', $csrfTokenFormulaire)
            )) {
                $this->addFlash(
                    'toast_error',
                    $this->translator->trans(
                        "La demande n'a pas été traitée. Veuillez réessayer."
                    )
                );

                return $this->redirectToRoute(
                    $isNewJob ? 'job_new' : 'job_edit',
                    $isNewJob ? [] : ['slug' => $job->getSlug()]
                );
            }

            $salaryError = $jobManagerService->validateSalaryRange($job);

            if ($salaryError !== null) {
                $this->addFlash('toast_error', $salaryError);

                return $this->render('job/new.html.twig', [
                    'form' => $form->createView(),
                    'csrfToken' => $csrfToken,
                    'slug' => $job->getSlug() ?? '',
                    'isEdit' => !$isNewJob,
                    'selectedBrief' => $selectedBrief,
                    'job' => $job,
                    'swalJob' => null,
                ]);
            }

            if ($isNewJob) {
                $job->setModerationStatus('pending');

                $pendingStatus = $entityManager
                    ->getRepository(StatusJob::class)
                    ->findOneBy([
                        'statusJob' => 'EN_ATTENTE_VALIDATION',
                    ]);

                if ($pendingStatus instanceof StatusJob) {
                    $job->setStatus($pendingStatus);
                }
            } elseif ($isStaff) {
                /*
                 * Une offre déjà approuvée reste approuvée lorsqu'un membre du
                 * staff corrige sa date d'expiration ou une autre information.
                 * Elle ne revient donc pas artificiellement dans la file à modérer.
                 */
                $statusLabel = mb_strtoupper(
                    trim((string) $job->getStatus()?->getStatusJob()),
                    'UTF-8'
                );
                $statusLabel = strtr($statusLabel, [
                    'É' => 'E',
                    'È' => 'E',
                    'Ê' => 'E',
                    'À' => 'A',
                    'Â' => 'A',
                    'Ù' => 'U',
                    'Û' => 'U',
                    'Î' => 'I',
                    'Ô' => 'O',
                ]);

                $isPublishedStatus = str_starts_with($statusLabel, 'PUBLI');

                if ($isPublishedStatus || $originalModerationStatus === 'approved') {
                    $job->setModerationStatus('approved');

                    if (method_exists($job, 'setVerifiedBy')) {
                        $job->setVerifiedBy($user);
                    }

                    if (method_exists($job, 'setVerifiedAt')) {
                        $job->setVerifiedAt(new \DateTimeImmutable());
                    }
                } else {
                    $job->setModerationStatus('modified');
                }

                if (method_exists($job, 'setModifiedByAdmin')) {
                    $job->setModifiedByAdmin($user);
                }

                if (method_exists($job, 'setModifiedByAdminAt')) {
                    $job->setModifiedByAdminAt(new \DateTimeImmutable());
                }
            }

            /*
             * Une modification ne doit jamais basculer vers un INSERT.
             */
            if (!$isNewJob && $job->getId() === null) {
                throw new \LogicException(
                    'Impossible de modifier une offre sans identifiant.'
                );
            }

            $jobManagerService->save($job, !$isNewJob);

            if ($isNewJob) {
                $request->getSession()->set(
                    'swal_job_id',
                    $job->getId()
                );

                return $this->redirectToRoute('job_new');
            }

            if ($isStaff) {
                $modificationNote = $job->getAdminModificationNote()
                    ?: 'Optimisation de votre offre pour une meilleure visibilité et conformité avec nos standards de qualité.';

                $notificationSent = $this->notificationService
                    ->sendJobModifiedNotification(
                        $job,
                        $user,
                        $modificationNote
                    );

                if ($notificationSent) {
                    $this->addFlash(
                        'toast_success',
                        '✅ Job modifié avec succès. Le créateur de l’offre a été notifié par email et messagerie interne.'
                    );
                } else {
                    $this->addFlash(
                        'toast_warning',
                        '⚠️ Job modifié avec succès, mais la notification n’a pas pu être envoyée. Vérifiez les logs.'
                    );
                }

                return $this->redirectToRoute('admin_jobs_moderation');
            }

            $this->addFlash(
                'toast_success',
                'Job mis à jour avec succès.'
            );

            return $this->redirectToRoute('talent_publisher_jobs');
        }

        $swalJob = null;

        $swalJobId = $request->getSession()->remove('swal_job_id');

        if ($swalJobId !== null) {
            $swalJob = $entityManager
                ->getRepository(Job::class)
                ->find($swalJobId);
        }

        return $this->render('job/new.html.twig', [
            'form' => $form->createView(),
            'csrfToken' => $csrfToken,
            'slug' => $job->getSlug() ?? '',
            'isEdit' => !$isNewJob,
            'selectedBrief' => $selectedBrief,
            'job' => $job,
            'swalJob' => $swalJob,
        ]);
    }
}
