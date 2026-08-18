<?php

namespace App\Service;

use App\Repository\ProfessionRepository;

class ProfessionService
{
    public function __construct(
        private readonly ProfessionRepository $professionRepository
    ) {
    }

    public function getByCategorie(int $categorieId): array
    {
        $professions = $this->professionRepository->findUniqueNonEmptyOrdered($categorieId);

        return array_map(
            static fn ($profession): array => [
                'id' => $profession->getId(),
                'name' => $profession->getProfession(),
            ],
            $professions
        );
    }

    public function getApiByCategoriePayload(int $categorieId): array
    {
        return [
            'ok' => true,
            'categorieId' => $categorieId,
            'professions' => $this->getByCategorie($categorieId),
        ];
    }
}