<?php
// src/Service/CurrencyConverter.php
namespace App\Service;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class CurrencyConverter
{
    private ?string $userCountry = null;
    private ?string $userCurrency = null;
    
    // Taux de change fixes (à remplacer par une API en production)
    private const EXCHANGE_RATES = [
        'XOF' => 1,           // FCFA (référence)
        'EUR' => 0.001524,    // 1 FCFA = 0.001524 EUR
        'USD' => 0.00165,     // 1 FCFA = 0.00165 USD
        'CAD' => 0.00223,     // 1 FCFA = 0.00223 CAD
        'CNY' => 0.0119,      // 1 FCFA = 0.0119 CNY
        'GBP' => 0.00131,     // 1 FCFA = 0.00131 GBP
        'CHF' => 0.00150,     // 1 FCFA = 0.00150 CHF
    ];
    
    private const COUNTRY_TO_CURRENCY = [
        'FR' => 'EUR',
        'DE' => 'EUR',
        'IT' => 'EUR',
        'ES' => 'EUR',
        'BE' => 'EUR',
        'US' => 'USD',
        'CA' => 'CAD',
        'CN' => 'CNY',
        'GB' => 'GBP',
        'CH' => 'CHF',
        'CI' => 'XOF', // Côte d'Ivoire
        'SN' => 'XOF', // Sénégal
        'CM' => 'XOF', // Cameroun
        'BF' => 'XOF', // Burkina Faso
        'ML' => 'XOF', // Mali
        'TG' => 'XOF', // Togo
        'BJ' => 'XOF', // Bénin
        'NE' => 'XOF', // Niger
        'GW' => 'XOF', // Guinée-Bissau
    ];
    
    private const CURRENCY_SYMBOLS = [
        'XOF' => 'FCFA',
        'EUR' => '€',
        'USD' => '$',
        'CAD' => 'C$',
        'CNY' => '¥',
        'GBP' => '£',
        'CHF' => 'CHF',
    ];
    
    public function __construct(
        private RequestStack $requestStack,
        private ParameterBagInterface $params
    ) {
        $this->detectUserCountry();
    }
    
    private function detectUserCountry(): void
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            $this->userCountry = 'CI'; // Pays par défaut
            $this->userCurrency = 'XOF';
            return;
        }
        
        // Essayer de détecter via Cloudflare ou autre proxy
        $country = $request->headers->get('CF-IPCountry') 
            ?? $request->headers->get('X-Country-Code')
            ?? $request->headers->get('HTTP_CF_IPCOUNTRY');
        
        // Fallback : utiliser l'IP pour géolocalisation
        if (!$country) {
            $ip = $request->getClientIp();
            $country = $this->geolocalizeIp($ip);
        }
        
        $this->userCountry = strtoupper($country ?? 'CI');
        $this->userCurrency = self::COUNTRY_TO_CURRENCY[$this->userCountry] ?? 'XOF';
    }
    
    private function geolocalizeIp(string $ip): ?string
    {
        // En production, utilisez une API comme ipapi.co ou maxmind
        // Pour le développement, retournez le pays par défaut
        if ($ip === '127.0.0.1' || $ip === '::1') {
            return 'CI'; // Local development
        }
        
        // Exemple avec ipapi.co (gratuit pour 1000 requêtes/mois)
        try {
            $response = @file_get_contents("https://ipapi.co/{$ip}/country/");
            if ($response) {
                return trim($response);
            }
        } catch (\Exception $e) {
            // Silencieux en cas d'erreur
        }
        
        return null;
    }
    
    public function convert(int $priceFCFA): float
    {
        if ($this->userCurrency === 'XOF') {
            return $priceFCFA;
        }
        
        $rate = self::EXCHANGE_RATES[$this->userCurrency] ?? 1;
        return round($priceFCFA * $rate, 2);
    }
    
    public function formatPrice(int $priceFCFA): string
    {
        $converted = $this->convert($priceFCFA);
        $currency = $this->userCurrency;
        $symbol = self::CURRENCY_SYMBOLS[$currency] ?? $currency;
        
        if ($currency === 'XOF') {
            return number_format($converted, 0, ',', ' ') . ' ' . $symbol;
        }
        
        // Format selon la devise
        return match($currency) {
            'EUR' => number_format($converted, 2, ',', ' ') . ' ' . $symbol,
            'USD', 'CAD' => $symbol . ' ' . number_format($converted, 2, '.', ','),
            'CNY' => $symbol . ' ' . number_format($converted, 2, '.', ','),
            default => number_format($converted, 2, ',', ' ') . ' ' . $symbol,
        };
    }
    
    public function getUserCurrency(): string
    {
        return $this->userCurrency;
    }
    
    public function getUserCountry(): string
    {
        return $this->userCountry;
    }
    
    public function getCurrencySymbol(): string
    {
        return self::CURRENCY_SYMBOLS[$this->userCurrency] ?? $this->userCurrency;
    }
}