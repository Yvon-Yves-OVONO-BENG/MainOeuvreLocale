<?php

namespace App\Service\Search;

use App\Repository\AnnonceRepository;
use App\Repository\CategorieRepository;
use App\Repository\CountryRepository;
use App\Repository\JobRepository;
use App\Repository\PersonalProfileRepository;
use App\Repository\ProfessionRepository;

final class SearchSuggestionService
{
    /** @var list<string> */
    private const CAMEROON_REGIONS = [
        'Adamaoua',
        'Centre',
        'Est',
        'Extrême-Nord',
        'Littoral',
        'Nord',
        'Nord-Ouest',
        'Ouest',
        'Sud',
        'Sud-Ouest',
    ];

    public function __construct(
        private readonly ProfessionRepository $professionRepository,
        private readonly CategorieRepository $categorieRepository,
        private readonly JobRepository $jobRepository,
        private readonly AnnonceRepository $annonceRepository,
        private readonly PersonalProfileRepository $personalProfileRepository,
        private readonly CountryRepository $countryRepository,
        private readonly SearchInputSanitizer $sanitizer,
    ) {
    }

    /** @return list<array{label: string, type: string}> */
    public function suggest(string $query, int $limit = 10): array
    {
        $candidates = [];

        foreach ($this->professionRepository->findUniqueNonEmptyOrdered() as $profession) {
            $label = trim((string) $profession->getProfession());
            if ($label !== '') {
                $candidates[] = ['label' => $label, 'type' => 'Profession'];
            }
        }

        foreach ($this->categorieRepository->findUniqueNonEmptyOrdered(true) as $categorie) {
            $label = trim((string) $categorie->getNom());
            if ($label !== '') {
                $candidates[] = ['label' => $label, 'type' => 'Catégorie'];
            }
        }

        return $this->rankCandidates($query, $candidates, $limit);
    }

    /** @return list<array{label: string, type: string}> */
    public function suggestLocations(string $query, int $limit = 10): array
    {
        $candidates = [];

        foreach ($this->jobRepository->findDistinctCitiesForSuggestions() as $city) {
            $candidates[] = ['label' => $city, 'type' => 'Ville'];
        }

        foreach ($this->annonceRepository->findDistinctCitiesForSuggestions() as $city) {
            $candidates[] = ['label' => $city, 'type' => 'Ville'];
        }

        foreach ($this->personalProfileRepository->findDistinctCitiesForSuggestions() as $city) {
            $candidates[] = ['label' => $city, 'type' => 'Ville'];
        }

        foreach (self::CAMEROON_REGIONS as $region) {
            $candidates[] = ['label' => $region, 'type' => 'Région'];
        }

        foreach ($this->countryRepository->findUniqueNonEmptyOrdered() as $country) {
            $label = trim((string) $country->getCountry());
            if ($label !== '') {
                $candidates[] = ['label' => $label, 'type' => 'Pays'];
            }
        }

        return $this->rankCandidates($query, $candidates, $limit);
    }

    /**
     * @param list<array{label: string, type: string}> $candidates
     * @return list<array{label: string, type: string}>
     */
    private function rankCandidates(string $query, array $candidates, int $limit): array
    {
        $needle = $this->sanitizer->normalize($query);
        if ($this->sanitizer->length($needle) < 2) {
            return [];
        }

        $seen = [];
        $startsWith = [];
        $contains = [];

        foreach ($candidates as $candidate) {
            $label = trim($candidate['label']);
            if ($label === '') {
                continue;
            }

            $normalized = $this->sanitizer->normalize($label);
            if ($normalized === '' || isset($seen[$normalized]) || !str_contains($normalized, $needle)) {
                continue;
            }

            $seen[$normalized] = true;
            $candidate['label'] = $label;

            if (str_starts_with($normalized, $needle)) {
                $startsWith[] = $candidate;
            } else {
                $contains[] = $candidate;
            }
        }

        $sort = static fn (array $a, array $b): int => strnatcasecmp($a['label'], $b['label']);
        usort($startsWith, $sort);
        usort($contains, $sort);

        return array_slice(array_merge($startsWith, $contains), 0, max(1, min(20, $limit)));
    }
}
