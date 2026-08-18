<?php

namespace App\Controller\Web\Moderateur;

use App\Repository\UserRepository;
use App\Repository\ReportRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\ExpressionLanguage\Expression;

#[IsGranted(new Expression('is_granted("ROLE_MODERATEUR") or is_granted("ROLE_ADMIN") or is_granted("ROLE_SUPER_ADMIN")'))]
#[Route('/moderateur', name: 'moderateur_')]
class ModerateurSearchController extends AbstractController
{
    #[Route('/search', name: 'search', methods: ['GET'])]
    public function index(
        Request $request,
        UserRepository $users,
        ReportRepository $reports,
    ): Response {
        $q = trim((string) $request->query->get('q', ''));
        $scope = (string) $request->query->get('scope', 'all');

        $results = [
            'users' => [],
            'companies' => [],
            'reports' => [],
        ];

        if ($q !== '') {
            if ($scope === 'all' || $scope === 'users') {
                $results['users'] = $users->searchForModeration($q, 30);
            }
            if ($scope === 'all' || $scope === 'companies') {
                $results['companies'] = $users->searchForModeration($q, 30);
            }
            if ($scope === 'all' || $scope === 'reports') {
                $results['reports'] = $reports->searchForModeration($q, 30);
            }
        }

        return $this->render('moderateur/search.html.twig', [
            'q' => $q,
            'scope' => $scope,
            'results' => $results,
        ]);
    }

    #[Route('/search/advanced', name: 'search_advanced', methods: ['GET'])]
    public function advanced(Request $request): Response
    {
        // UI only : la page propose des filtres ; tu peux brancher ensuite sur index() avec query params.
        return $this->render('moderateur/search_advanced.html.twig', [
            'q' => (string) $request->query->get('q', ''),
        ]);
    }

    #[Route('/search/suggest', name: 'search_suggest', methods: ['GET'])]
    public function suggest(
        Request $request,
        UserRepository $users,
        ReportRepository $reports,
    ): JsonResponse {
        $q = trim((string) $request->query->get('q', ''));
        if (mb_strlen($q) < 2) {
            return $this->json(['items' => []]);
        }

        $items = [];

        foreach ($users->searchForModeration($q, 5) as $u) {
            $items[] = [
                'type' => 'User',
                'label' => sprintf('#%d • %s', $u->getId(), $u->getEmail() ?? '—'),
                'url' => $this->generateUrl('moderateur_search', ['q' => $q, 'scope' => 'users']),
            ];
        }
        foreach ($users->searchForModeration($q, 5) as $c) {
            $items[] = [
                'type' => 'Company',
                'label' => sprintf('#%d • %s', $c->getId(), method_exists($c, 'getName') ? ($c->getName() ?? '—') : '—'),
                'url' => $this->generateUrl('moderateur_search', ['q' => $q, 'scope' => 'companies']),
            ];
        }
        foreach ($reports->searchForModeration($q, 5) as $r) {
            $items[] = [
                'type' => 'Report',
                'label' => sprintf('#%d • %s', $r->getId(), method_exists($r, 'getReason') ? mb_strimwidth((string)$r->getReason(), 0, 28, '…') : 'Signalement'),
                'url' => $this->generateUrl('moderateur_search', ['q' => $q, 'scope' => 'reports']),
            ];
        }

        return $this->json(['items' => array_slice($items, 0, 10)]);
    }
}