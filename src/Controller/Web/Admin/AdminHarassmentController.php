<?php

namespace App\Controller\Web\Admin;

use App\Repository\MessageRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/harassment', name: 'admin_harassment_')]
class AdminHarassmentController extends AbstractController
{
    #[Route('/investigate', name: 'investigate', methods: ['GET'])]
    public function investigate(
        Request $request,
        UserRepository $userRepository,
        MessageRepository $messageRepository
    ): Response {
        $reporterId   = trim((string) $request->query->get('reporterId', ''));
        $harasserId   = trim((string) $request->query->get('harasserId', ''));
        $reporterText = trim((string) $request->query->get('reporter', ''));
        $harasserText = trim((string) $request->query->get('harasser', ''));

        $reporter = null;
        $harasser = null;

        if ($reporterId !== '' && ctype_digit($reporterId)) {
            $reporter = $userRepository->find((int) $reporterId);
        } elseif ($reporterText !== '') {
            $reporter = $userRepository->findOneBy(['email' => $reporterText]);
        }

        if ($harasserId !== '' && ctype_digit($harasserId)) {
            $harasser = $userRepository->find((int) $harasserId);
        } elseif ($harasserText !== '') {
            $harasser = $userRepository->findOneBy(['email' => $harasserText]);
        }

        $thread = [];
        if ($reporter && $harasser) {
            $thread = $messageRepository->findConversationBetweenUsers($reporter, $harasser);
        }

        return $this->render('admin/harassment_investigate.html.twig', [
            'reporter' => $reporter,
            'harasser' => $harasser,
            'thread'   => $thread,
        ]);
    }

    #[Route('/user-search', name: 'user_search', methods: ['GET'])]
    public function userSearch(Request $request, UserRepository $userRepository): JsonResponse
    {
        $q = trim((string) $request->query->get('q', ''));

        if (mb_strlen($q) < 2) {
            return $this->json(['items' => []]);
        }

        $items = $userRepository->searchForPicker($q, 10);

        return $this->json([
            'items' => array_map(static function ($u) {
                return [
                    'id'    => $u->getId(),
                    'label' => $u->getEmail() ?? ('User #' . $u->getId()),
                    'roles' => $u->getRoles(),
                ];
            }, $items),
        ]);
    }
}