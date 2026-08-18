<?php

namespace App\Service;

use App\Repository\AnnonceRepository;

final class AnnonceGeolocationService
{
    public function __construct(
        private readonly AnnonceRepository $annonces,
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

        $rows = $this->annonces->findNearbyPublic($lat, $lng, $radius, $q, $city, $limit);

        return array_map(static function (array $row): array {
            return [
                'id' => (int) $row['id'],
                'title' => (string) $row['titre'],
                'description' => mb_substr(strip_tags((string) $row['description']), 0, 180),
                'city' => (string) $row['ville'],
                'dailySalary' => $row['salaire_journalier'] !== null ? (int) $row['salaire_journalier'] : null,
                'profession' => (string) ($row['profession_name'] ?: 'Service'),
                'author' => (string) ($row['full_name'] ?: 'Talent'),
                'slug' => (string) $row['slug'],
                'distanceKm' => round((float) $row['distance_km'], 2),
                // Une précision d'environ 100 m protège l'adresse exacte du talent.
                'mapLatitude' => round((float) $row['latitude'], 3),
                'mapLongitude' => round((float) $row['longitude'], 3),
            ];
        }, $rows);
    }

    /** @return array{0: float, 1: float} */
    private function validateCoordinates(mixed $latitude, mixed $longitude): array
    {
        if (!is_numeric($latitude) || !is_numeric($longitude)) {
            throw new \InvalidArgumentException('Autorisez la localisation afin d’afficher les annonces proches.');
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
        if (!is_finite($radius) || $radius < 0 || $radius > 100) {
            throw new \InvalidArgumentException('Le rayon doit être compris entre 0 et 100 km.');
        }

        return $radius;
    }
}
