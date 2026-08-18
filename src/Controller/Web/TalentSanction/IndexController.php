<?php

namespace App\Controller\Web\TalentSanction;

use App\Entity\User;
use App\Service\TalentSanctionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/talent')]
final class IndexController extends AbstractController
{
    #[Route('/sanctions', name: 'talent_sanctions_index', methods: ['GET'])]
    public function __invoke(TalentSanctionService $talentSanctionService): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $this->render(
            'talent/sanction/sanction.html.twig',
            $talentSanctionService->getIndexData($user)
        );
    }
}