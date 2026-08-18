<?php

namespace App\Controller\Web\Moderateur;

use App\Entity\ModerationRule;
use App\Repository\ModerationRuleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/moderateur/rules', name: 'moderateur_rules_')]
#[IsGranted(new Expression('is_granted("ROLE_MODERATEUR") or is_granted("ROLE_ADMIN") or is_granted("ROLE_SUPER_ADMIN")'))]
class RulesController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, ModerationRuleRepository $repo): Response
    {
        $q = trim((string)$request->query->get('q', ''));
        $category = (string)$request->query->get('category', '');
        $severity = (string)$request->query->get('severity', '');
        $page = max(1, (int)$request->query->get('page', 1));
        $limit = min(50, max(10, (int)$request->query->get('limit', 10)));
        $offset = ($page - 1) * $limit;

        $total = $repo->countForIndex($q, $category, $severity);
        $rows = $repo->findForIndex($q, $category, $severity, $limit, $offset);
        $pages = max(1, (int)ceil($total / $limit));

        return $this->render('moderateur/rules.html.twig', [
            'rows' => $rows,
            'kpi' => $repo->kpis(),
            'q' => $q,
            'category' => $category,
            'severity' => $severity,
            'page' => $page,
            'pages' => $pages,
            'limit' => $limit,
            'total' => $total,
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET','POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $r = new ModerationRule();

        if ($request->isMethod('POST')) {
            $r->setTitle(trim((string)$request->request->get('title', '')))
              ->setCategory((string)$request->request->get('category', 'general'))
              ->setSeverity((string)$request->request->get('severity', 'warn'))
              ->setDescription(trim((string)$request->request->get('description', '')))
              ->setRecommendedAction(trim((string)$request->request->get('recommendedAction', '')) ?: null)
              ->setIsActive((bool)$request->request->get('isActive', true));

            $em->persist($r);
            $em->flush();

            $this->addFlash('success', 'Règle créée ✅');
            return $this->redirectToRoute('moderateur_rules_index');
        }

        return $this->render('moderateur/rules_form.html.twig', [
            'r' => $r,
            'mode' => 'new',
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', requirements: ['id'=>'\d+'], methods: ['GET','POST'])]
    public function edit(int $id, Request $request, EntityManagerInterface $em): Response
    {
        $r = $em->getRepository(ModerationRule::class)->find($id);
        if (!$r) throw $this->createNotFoundException('Règle introuvable.');

        if ($request->isMethod('POST')) {
            $r->setTitle(trim((string)$request->request->get('title', $r->getTitle())))
              ->setCategory((string)$request->request->get('category', $r->getCategory()))
              ->setSeverity((string)$request->request->get('severity', $r->getSeverity()))
              ->setDescription(trim((string)$request->request->get('description', $r->getDescription())))
              ->setRecommendedAction(trim((string)$request->request->get('recommendedAction', $r->getRecommendedAction() ?? '')) ?: null)
              ->setIsActive((bool)$request->request->get('isActive', $r->isActive()))
              ->setUpdatedAt(new \DateTimeImmutable());

            $em->flush();

            $this->addFlash('success', 'Règle mise à jour ✅');
            return $this->redirectToRoute('moderateur_rules_index');
        }

        return $this->render('moderateur/rules_form.html.twig', [
            'r' => $r,
            'mode' => 'edit',
        ]);
    }

    #[Route('/{id}/toggle', name: 'toggle', requirements: ['id'=>'\d+'], methods: ['POST'])]
    public function toggle(int $id, Request $request, ModerationRuleRepository $repo): JsonResponse
    {
        $payload = json_decode((string)$request->getContent(), true) ?: [];
        $active = (bool)($payload['active'] ?? false);

        if (!$this->isCsrfTokenValid('rule_toggle_'.$id, (string)($payload['_token'] ?? ''))) {
            return $this->json(['ok'=>false,'message'=>'CSRF invalide'], 403);
        }

        $repo->toggleActive($id, $active);
        return $this->json(['ok'=>true]);
    }
}