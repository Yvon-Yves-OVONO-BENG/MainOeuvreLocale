<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Exception\MissingMandatoryParametersException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

final class SeoController extends AbstractController
{
    private const CANONICAL_HOST = 'https://www.maindoeuvrelocale.com';

    /**
     * Génère le fichier public /sitemap.xml avec les vraies URLs des routes
     * Symfony. Une route absente du projet est simplement ignorée.
     */
    #[Route('/sitemap.xml', name: 'seo_sitemap', methods: ['GET'])]
    public function sitemap(RouterInterface $router): Response
    {
        $publicRoutes = [
            'accueil' => ['changefreq' => 'daily', 'priority' => '1.0'],
            'liste_jobs' => ['changefreq' => 'hourly', 'priority' => '0.9'],
            'liste_talents' => ['changefreq' => 'daily', 'priority' => '0.9'],
            'reputation_companies' => ['changefreq' => 'weekly', 'priority' => '0.7'],
            'reputation_particuliers' => ['changefreq' => 'weekly', 'priority' => '0.7'],
            'marketing_story' => ['changefreq' => 'monthly', 'priority' => '0.7'],
            'marketing_mission' => ['changefreq' => 'monthly', 'priority' => '0.7'],
            'marketing_vision' => ['changefreq' => 'monthly', 'priority' => '0.7'],
            'marketing_services' => ['changefreq' => 'monthly', 'priority' => '0.8'],
            'subscription_plans' => ['changefreq' => 'weekly', 'priority' => '0.7'],
            'pricing_index' => ['changefreq' => 'weekly', 'priority' => '0.7'],
            'support_center' => ['changefreq' => 'monthly', 'priority' => '0.6'],
            'support_faq' => ['changefreq' => 'monthly', 'priority' => '0.6'],
            'support_how_it_works' => ['changefreq' => 'monthly', 'priority' => '0.7'],
            'support_boost_announcement' => ['changefreq' => 'weekly', 'priority' => '0.7'],
            'contact_page' => ['changefreq' => 'monthly', 'priority' => '0.5'],
            'legal_privacy' => ['changefreq' => 'yearly', 'priority' => '0.2'],
            'legal_terms' => ['changefreq' => 'yearly', 'priority' => '0.2'],
            'app_suppression_compte' => ['changefreq' => 'yearly', 'priority' => '0.2'],
        ];

        $routeCollection = $router->getRouteCollection();
        $urls = [];
        $seen = [];

        foreach ($publicRoutes as $routeName => $seo) {
            $route = $routeCollection->get($routeName);

            if ($route === null) {
                continue;
            }

            $parameters = str_contains($route->getPath(), '{_locale}')
                ? ['_locale' => 'fr']
                : [];

            try {
                $generatedUrl = $router->generate(
                    $routeName,
                    $parameters,
                    UrlGeneratorInterface::ABSOLUTE_URL
                );
            } catch (MissingMandatoryParametersException) {
                continue;
            }

            $canonicalUrl = preg_replace(
                '#^https?://[^/]+#i',
                self::CANONICAL_HOST,
                $generatedUrl
            );

            if (!is_string($canonicalUrl) || isset($seen[$canonicalUrl])) {
                continue;
            }

            $seen[$canonicalUrl] = true;
            $urls[] = [
                'loc' => $canonicalUrl,
                'changefreq' => $seo['changefreq'],
                'priority' => $seo['priority'],
            ];
        }

        $response = $this->render('seo/sitemap.xml.twig', [
            'urls' => $urls,
        ]);
        $response->headers->set('Content-Type', 'application/xml; charset=UTF-8');
        $response->setPublic();
        $response->setMaxAge(3600);

        return $response;
    }
}
