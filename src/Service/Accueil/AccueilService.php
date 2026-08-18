<?php

namespace App\Service\Accueil;

use App\Repository\CategorieRepository;
use App\Repository\JobRepository;

class AccueilService
{
    public function __construct(
        private JobRepository $jobRepository,
        private CategorieRepository $categorieRepository,
    ) {
    }

    public function getAccueilData(int $categoriesLimit = 12, int $jobsLimit = 5): array
    {
        $categoriesWithCounts = $this->categorieRepository->findWithJobsCount($categoriesLimit);
        $jobsByType = $this->jobRepository->findLatestByTypeJobFromDb($jobsLimit, true);

        return [
            'jobsByType' => $jobsByType,
            'types' => array_keys($jobsByType),
            'categoriesWithCounts' => $categoriesWithCounts,
        ];
    }
}