<?php

namespace App\Controller\Web\Moderateur;

use App\Repository\UserLogRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted(new Expression('is_granted("ROLE_MODERATEUR") or is_granted("ROLE_ADMIN") or is_granted("ROLE_SUPER_ADMIN")'))]
#[Route('/moderateur/logs', name: 'moderateur_logs_')]
class LogsController extends AbstractController
{

    #[Route('', name: 'index', methods: ['GET'])]
    public function logs(Request $request, UserLogRepository $logRepo): Response
    {
        $q = trim((string)$request->query->get('q',''));
        $action = (string)$request->query->get('action','');
        $days = max(1, min(90, (int)$request->query->get('days', 7)));
        $page = max(1, (int)$request->query->get('page', 1));
        $limit = min(50, max(10, (int)$request->query->get('limit', 10)));
        $offset = ($page - 1) * $limit;

        $res = $logRepo->findForModeration($q, $action, $days, $limit, $offset);
        $stats = $logRepo->stats7dActions();

        return $this->render('moderateur/logs.html.twig', [
            'items'=>$res['items'],
            'total'=>$res['total'],
            'page'=>$page,
            'pages'=>max(1, (int)ceil($res['total'] / $limit)),
            'limit'=>$limit,
            'q'=>$q,
            'action'=>$action,
            'days'=>$days,
            'stats'=>$stats,
        ]);
    }
}