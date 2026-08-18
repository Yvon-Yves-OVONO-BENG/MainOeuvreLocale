<?php

namespace App\Controller\Web\Annonce;

use App\Repository\AnnonceRepository;
use App\Repository\RatingRepository;
use App\Service\Search\SearchInputSanitizer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AnnonceSearchController extends AbstractController
{
    #[Route('/annonces', name: 'annonce_search', methods: ['GET'])]
    public function __invoke(
        Request $request,
        AnnonceRepository $annonceRepository,
        RatingRepository $ratingRepository,
        SearchInputSanitizer $searchInputSanitizer,
    ): Response
    {
        try {
            $keyword = $searchInputSanitizer->sanitizeKeyword($request->query->get('q', ''));
        } catch (\InvalidArgumentException $exception) {
            $keyword = '';
            $this->addFlash('warning', $exception->getMessage());
        }

        try {
            $city = $searchInputSanitizer->sanitizeLocation($request->query->get('city', ''), 120);
        } catch (\InvalidArgumentException $exception) {
            $city = '';
            $this->addFlash('warning', $exception->getMessage());
        }

        $filters = [
            'q' => $keyword,
            'city' => $city,
        ];

        $pagination = $annonceRepository->searchPublic(
            $filters,
            max(1, $request->query->getInt('page', 1)),
            12
        );
        $userIds = array_values(array_unique(array_filter(array_map(
            static fn ($annonce): ?int => $annonce->getUser()?->getId(),
            $pagination['items']
        ))));

        return $this->render('annonce/search.html.twig', [
            'annonces' => $pagination['items'],
            'total' => $pagination['total'],
            'page' => $pagination['page'],
            'pages' => $pagination['pages'],
            'limit' => $pagination['limit'],
            'filters' => $filters,
            'ratingStats' => $ratingRepository->getStatsForTalents($userIds),
        ]);
    }
}
