<?php

namespace App\Service\Job;

use App\Entity\Job;
use App\Util\HashedSlugGenerator;
use App\Entity\User;
use App\Repository\StatusJobRepository;
use App\Service\BriefTemplateCatalog;
use Doctrine\ORM\EntityManagerInterface;

class JobManagerService
{
    public function __construct(
        private EntityManagerInterface $em,
        private StatusJobRepository $statusJobRepo,
        private BriefTemplateCatalog $briefTemplateCatalog,
    ) {
    }

    public function findOrCreate(?string $slug = null): Job
    {
        $slug = trim((string) $slug);

        if ($slug !== '') {
            $job = $this->em->getRepository(Job::class)->findOneBy(['slug' => $slug]);

            if (!$job instanceof Job) {
                throw new \RuntimeException('Job introuvable.');
            }

            return $job;
        }

        return new Job();
    }

    public function initializeJob(Job $job, ?User $user = null): void
    {
        if (null === $job->getId() && null === $job->getCreatedAt()) {
            $job->setCreatedAt(new \DateTime());
        }

        if (null === $job->getStatus()) {
            $defaultStatus = $this->statusJobRepo->findOneBy(['statusJob' => 'pending']);
            if ($defaultStatus) {
                $job->setStatus($defaultStatus);
            }
        }

        if ($user && null === $job->getCreatedBy()) {
            $job->setCreatedBy($user);
        }
    }

    public function getSelectedBrief(?string $briefSlug): ?array
    {
        $briefSlug = trim((string) $briefSlug);

        if ($briefSlug === '') {
            return null;
        }

        return $this->briefTemplateCatalog->findBySlug($briefSlug);
    }

    public function applyBriefTemplate(Job $job, array $selectedBrief): void
    {
        if (!$job->getTitle()) {
            $job->setTitle((string) ($selectedBrief['job_title'] ?? 'Nouvelle mission'));
        }

        if (!$job->getDescription()) {
            $job->setDescription((string) ($selectedBrief['description'] ?? ''));
        }

        if ($job->getSalaireMin() === null && isset($selectedBrief['budget_min'])) {
            $job->setSalaireMin((int) $selectedBrief['budget_min']);
        }

        if ($job->getSalaireMax() === null && isset($selectedBrief['budget_max'])) {
            $job->setSalaireMax((int) $selectedBrief['budget_max']);
        }

        if ($job->getDateExpirationAt() === null && isset($selectedBrief['delay_days'])) {
            $days = max(1, (int) $selectedBrief['delay_days']);
            $job->setDateExpirationAt(
                (new \DateTimeImmutable())->modify(sprintf('+%d days', $days))
            );
        }

        if (!$job->getExperience() && isset($selectedBrief['experience'])) {
            $job->setExperience((string) $selectedBrief['experience']);
        }

        if (!$job->getMission() && isset($selectedBrief['mission'])) {
            $job->setMission((string) $selectedBrief['mission']);
        }

        if (!$job->getProfil() && isset($selectedBrief['profil'])) {
            $job->setProfil((string) $selectedBrief['profil']);
        }
    }

    public function validateSalaryRange(Job $job): ?string
    {
        if ($job->getSalaireMin() !== null && $job->getSalaireMax() !== null) {
            if ($job->getSalaireMin() > $job->getSalaireMax()) {
                return 'Le salaire minimum ne peut pas dépasser le salaire maximum.';
            }
        }

        return null;
    }

    public function save(Job $job, bool $isEdit = false): void
    {
        /*
         * Protection anti-doublon : une opération annoncée comme modification
         * doit obligatoirement porter sur une entité déjà enregistrée.
         */
        if ($isEdit && $job->getId() === null) {
            throw new \LogicException(
                'Impossible de modifier un job sans identifiant : insertion annulée.'
            );
        }

        if (!$isEdit) {
            if (!$job->getSlug()) {
                $job->setSlug(HashedSlugGenerator::generate());
            }

            if (!$job->getReference()) {
                // Référence publique non séquentielle : elle ne révèle plus le volume d'annonces.
                $job->setReference(
                    sprintf('JOB-%s-%s', date('Y'), strtoupper(bin2hex(random_bytes(5))))
                );
            }

            $this->em->persist($job);
        } elseif (!$this->em->contains($job)) {
            /*
             * Un job modifié doit être une entité Doctrine gérée, chargée depuis
             * la base. On refuse toute entité détachée au lieu de créer une ligne.
             */
            throw new \LogicException(
                'Le job à modifier n’est pas géré par Doctrine : mise à jour annulée.'
            );
        }

        $this->em->flush();

    }
}
