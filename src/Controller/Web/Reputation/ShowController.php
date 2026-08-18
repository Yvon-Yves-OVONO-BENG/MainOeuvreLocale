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

final class ShowController extends AbstractController
{
    #[Route('/reputation/user/{slug}', name: 'reputation_show', methods: ['GET'], requirements: ['slug' => '[A-Za-z0-9._-]+'])]
    public function __invoke(string $slug, Request $request, ReputationService $reputationService): Response
    {
        /** @var User|null $me */
        $me = $this->getUser();

        $editMode = $me ? $request->query->getBoolean('edit', false) : false;

        try {
            $data = $reputationService->getShowPageData($slug, $me, $editMode);
        } catch (BadRequestHttpException $exception) {
            $this->addFlash('warning', $exception->getMessage());

            return $this->redirectToRoute('reputation_particuliers');
        } catch (NotFoundHttpException $exception) {
            throw $this->createNotFoundException($exception->getMessage());
        }

        $formView = null;

        if ($me) {
            $form = $this->createForm(RateUserType::class, $data['formData']);
            $formView = $form->createView();
        }

        return $this->render('reputation/user_show.html.twig', array_merge($data, [
            'form' => $formView,
            'canRate' => $me !== null,
        ]));
    }
}