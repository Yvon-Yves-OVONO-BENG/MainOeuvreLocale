<?php

namespace App\Service;

use App\Repository\JobRepository;

final class JobGeolocationService
{
    public function __construct(
        private readonly JobRepository $jobs,
    ) {
    }

    /** @return array<int, array<string, mixed>> */
    public function searchNearby(
        mixed $latitude,
        mixed $longitude,
        mixed $radiusKm,
        string $q = '',
        string $city = '',
        int $limit = 100,
    ): array {
        [$lat, $lng] = $this->validateCoordinates($latitude, $longitude);
        $radius = $this->validateRadius($radiusKm);

        $rows = $this->jobs->findNearbyPublic($lat, $lng, $radius, $q, $city, $limit);

        return array_map(static function (array $row): array {
            return [
                'id' => (int) $row['id'],
                'title' => (string) $row['title'],
                'description' => mb_substr(strip_tags((string) $row['description']), 0, 180),
                'city' => (string) $row['city'],
                'salaryMin' => $row['salaire_min'] !== null ? (int) $row['salaire_min'] : null,
                'salaryMax' => $row['salaire_max'] !== null ? (int) $row['salaire_max'] : null,
                'profession' => (string) ($row['profession_name'] ?: 'Offre d’emploi'),
                'author' => (string) ($row['full_name'] ?: 'Recruteur'),
                'slug' => (string) $row['slug'],
                'distanceKm' => $row['distance_km'] !== null ? round((float) $row['distance_km'], 2) : null,
                // Une précision d'environ 100 m protège l'adresse exacte du recruteur.
                // Si elle manque, l'interface utilise le centre de la ville de l'offre.
                'mapLatitude' => $row['latitude'] !== null ? round((float) $row['latitude'], 3) : null,
                'mapLongitude' => $row['longitude'] !== null ? round((float) $row['longitude'], 3) : null,
            ];
        }, $rows);
    }

    /** @return array{0: float, 1: float} */
    private function validateCoordinates(mixed $latitude, mixed $longitude): array
    {
        if (!is_numeric($latitude) || !is_numeric($longitude)) {
            throw new \InvalidArgumentException('Autorisez la localisation afin d’afficher les offres proches.');
        }

        $lat = (float) $latitude;
        $lng = (float) $longitude;

        if (!is_finite($lat) || $lat < -90 || $lat > 90 || !is_finite($lng) || $lng < -180 || $lng > 180) {
            throw new \InvalidArgumentException('La position reçue est invalide.');
        }

        return [$lat, $lng];
    }

    private function validateRadius(mixed $radiusKm): float
    {
        if (!is_numeric($radiusKm)) {
            throw new \InvalidArgumentException('Le rayon est invalide.');
        }

        $radius = (float) $radiusKm;
        if (!is_finite($radius) || $radius < 0 || $radius > 100 || fmod($radius, 10.0) !== 0.0) {
            throw new \InvalidArgumentException('Le rayon doit être compris entre 0 et 100 km, par pas de 10 km.');
        }

        return $radius;
    }
}
