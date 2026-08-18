<?php

namespace App\Controller\Web;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class JavascriptController extends AbstractController
{
    #[Route('/assets/js/mol-base-page.js', name: 'mol_base_page_js')]
    public function molBasePageJs(): Response
    {
        $response = $this->render('js/mol-base-page.js.twig');

        $response->headers->set('Content-Type', 'application/javascript; charset=utf-8');

        return $response;
    }
}