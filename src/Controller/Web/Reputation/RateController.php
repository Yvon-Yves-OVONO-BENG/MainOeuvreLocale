<?php

namespace App\Controller\Web\Reputation;

use App\Entity\User;
use App\Form\RateUserType;
use App\Service\ReputationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class RateController extends AbstractController
{
    #[Route('/reputation/user/{slug}', name: 'reputation_rate', methods: ['POST'], requirements: ['slug' => '[A-Za-z0-9._-]+'])]
    public function __invoke(string $slug, Request $request, ReputationService $reputationService): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var User $me */
        $me = $this->getUser();

        $editMode = $request->query->getBoolean('edit', false);

        try {
            $data = $reputationService->getShowPageData($slug, $me, $editMode);
        } catch (BadRequestHttpException $exception) {
            $this->addFlash('warning', $exception->getMessage());

            return $this->redirectToRoute('reputation_particuliers');
        } catch (NotFoundHttpException $exception) {
            throw $this->createNotFoundException($exception->getMessage());
        }

        $form = $this->createForm(RateUserType::class, $data['formData']);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $stars = (int) $form->get('stars')->getData();
            $comment = trim((string) $form->get('comment')->getData());

            $result = $reputationService->submitOpinion($slug, $me, $stars, $comment);

            $this->addFlash('success', $result['message']);

            return $this->redirectToRoute('reputation_show', [
                'slug' => $result['targetSlug'],
            ]);
        }

        return $this->render('reputation/user_show.html.twig', array_merge($data, [
            'form' => $form->createView(),
            'canRate' => true,
        ]));
    }
}