<?php

namespace App\Service;

use App\Entity\Country;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;  
use Symfony\Component\Intl\Countries;


class UserLogService
{
    private $client;
    private $entityManager;

    public function __construct(HttpClientInterface $client, EntityManagerInterface $entityManager)
    {
        $this->client = $client;
        $this->entityManager = $entityManager;
    }

    public function getCityAndCountry(?string $ip): array
    {
        // 1) IP vide => rien à faire
        if (!$ip) {
            return ['city' => null, 'country' => null, 'country_exists' => false];
        }

        // 2) Si IP locale/privée => ip-api ne saura pas géolocaliser
        // FILTER_FLAG_NO_PRIV_RANGE + FILTER_FLAG_NO_RES_RANGE => vrai seulement si IP publique
        $isPublic = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);

        // Si pas publique, on évite d’appeler ip-api avec ::1/127.0.0.1 etc.
        if (!$isPublic) {
            return [
                'city' => null,
                'country' => null,
                'country_exists' => false,
                'reason' => 'ip_not_public',
                'ip' => $ip,
            ];
        }

        try {
            $url = "http://ip-api.com/json/{$ip}?fields=status,message,country,city";

            $response = $this->client->request('GET', $url);
            $data = $response->toArray(false); // false => ne throw pas si status != 200

            // 3) Si l’API échoue, on renvoie la raison
            if (($data['status'] ?? null) !== 'success') {
                return [
                    'city' => null,
                    'country' => null,
                    'country_exists' => false,
                    'reason' => $data['message'] ?? 'ip_api_fail',
                    'ip' => $ip,
                    'raw' => $data,
                ];
            }

            $city = $data['city'] ?? null;
            $countryEn = $data['country'] ?? null;

            // Convert EN -> ISO2 -> FR
            $countryCode = $countryEn ? array_search($countryEn, Countries::getNames('en'), true) : false;
            $countryFr = $countryCode !== false ? Countries::getName($countryCode, 'fr') : null;

            $pays = null;
            if ($countryFr) {
                $pays = $this->entityManager->getRepository(Country::class)
                    ->findOneBy(['country' => $countryFr]);
            }

            return [
                'city' => $city,
                'country' => $countryFr,
                'country_exists' => $pays !== null,
                'country_entity' => $pays,
                'ip' => $ip,
            ];

        } catch (\Throwable $e) {
            return [
                'city' => null,
                'country' => null,
                'country_exists' => false,
                'reason' => 'exception',
                'error' => $e->getMessage(),
                'ip' => $ip,
            ];
        }
    }

}
