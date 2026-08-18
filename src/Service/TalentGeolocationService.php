<?php

namespace App\Service;

use App\Entity\ProfessionalProfile;
use App\Repository\ProfessionalProfileRepository;

final class TalentGeolocationService
{
    public const MIN_RADIUS_KM = 0.0;
    public const MAX_RADIUS_KM = 100.0;

    public function __construct(
        private readonly ProfessionalProfileRepository $profiles,
    ) {
    }

    public function applyConsent(
        ProfessionalProfile $profile,
        bool $enabled,
        mixed $latitude,
        mixed $longitude,
    ): void {
        if (!$enabled) {
            // La désactivation retire aussi les coordonnées enregistrées.
            $profile->disableGeolocation();
            return;
        }

        [$lat, $lng] = $this->validateCoordinates($latitude, $longitude);

        $profile
            ->setCoordinates($lat, $lng)
            ->setGeolocationEnabled(true);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function searchNearby(
        mixed $latitude,
        mixed $longitude,
        mixed $radiusKm,
        ?int $professionId = null,
        string $q = '',
        string $city = '',
        int $limit = 100,
    ): array {
        [$lat, $lng] = $this->validateCoordinates($latitude, $longitude);
        $radius = $this->validateRadius($radiusKm);

        $rows = $this->profiles->findNearbyTalents(
            $lat,
            $lng,
            $radius,
            $professionId,
            $q,
            $city,
            $limit,
        );

        return array_map(static function (array $row): array {
            $exactLatitude = (float) $row['latitude'];
            $exactLongitude = (float) $row['longitude'];

            return [
                'id' => (int) $row['id'],
                'userId' => (int) $row['user_id'],
                'name' => (string) ($row['full_name'] ?: 'Talent'),
                'slug' => (string) ($row['slug'] ?: ''),
                'profession' => (string) ($row['profession_name'] ?: 'Profession non renseignée'),
                'professionId' => $row['profession_id'] !== null ? (int) $row['profession_id'] : null,
                'city' => (string) ($row['city'] ?: ''),
                'country' => (string) ($row['country_name'] ?: ''),
                'photo' => (string) ($row['photo'] ?: ''),
                'experienceYears' => (string) ($row['experience_years'] ?: ''),
                'distanceKm' => round((float) $row['distance_km'], 2),
                // La carte publique reçoit une position approximative (~100 m),
                // tandis que le calcul du rayon reste exact côté serveur.
                'mapLatitude' => round($exactLatitude, 3),
                'mapLongitude' => round($exactLongitude, 3),
                'locationUpdatedAt' => $row['location_updated_at'],
            ];
        }, $rows);
    }

    /** @return array{0: float, 1: float} */
    private function validateCoordinates(mixed $latitude, mixed $longitude): array
    {
        if (!is_numeric($latitude) || !is_numeric($longitude)) {
            throw new \InvalidArgumentException(
                'La position n’a pas pu être enregistrée. Autorisez la localisation puis réessayez.'
            );
        }

        $lat = (float) $latitude;
        $lng = (float) $longitude;

        if (!is_finite($lat) || $lat < -90 || $lat > 90) {
            throw new \InvalidArgumentException('Latitude invalide.');
        }

        if (!is_finite($lng) || $lng < -180 || $lng > 180) {
            throw new \InvalidArgumentException('Longitude invalide.');
        }

        return [$lat, $lng];
    }

    private function validateRadius(mixed $radiusKm): float
    {
        if (!is_numeric($radiusKm)) {
            throw new \InvalidArgumentException('Le rayon de recherche est invalide.');
        }

        $radius = (float) $radiusKm;
        if (!is_finite($radius) || $radius < self::MIN_RADIUS_KM || $radius > self::MAX_RADIUS_KM) {
            throw new \InvalidArgumentException('Le rayon doit être compris entre 0 et 100 km.');
        }

        return $radius;
    }
}
