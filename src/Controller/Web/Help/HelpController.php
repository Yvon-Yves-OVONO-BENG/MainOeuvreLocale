<?php

declare(strict_types=1);

namespace App\Controller\Web\Help;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HelpController extends AbstractController
{
    #[Route('/help', name: 'help_index', methods: ['GET'])]
    public function __invoke(): Response
    {
        return $this->redirectToRoute('support_center');
    }
}
