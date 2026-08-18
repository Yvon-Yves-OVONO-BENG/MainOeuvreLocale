<?php

namespace App\Controller\Web;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class ChangeLocaleController extends AbstractController
{
    #[Route('/change-locale/{locale}', name: 'change_locale', requirements: ['locale' => 'fr|en'])]
    public function changeLocale(string $locale, Request $request): RedirectResponse
    {
        $request->getSession()->set('_locale', $locale);

        $referer = $request->headers->get('referer');

        // Anti-boucle + fallback sûr
        if (!$referer || str_contains($referer, '/change-locale/')) {
            return $this->redirectToRoute('accueil');
        }

        return $this->redirect($referer);
    }
}
