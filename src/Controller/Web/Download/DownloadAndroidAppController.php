<?php

namespace App\Controller\Web\Download;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

final class DownloadAndroidAppController extends AbstractController
{
    #[Route('/telechargements/android', name: 'download_android_app', methods: ['GET'])]
    public function __invoke(): Response
    {
        $projectDir = (string) $this->getParameter('kernel.project_dir');
        $apkPath = $projectDir.'/public/downloads/main-doeuvre-locale.apk';

        if (!is_file($apkPath) || !is_readable($apkPath)) {
            $this->addFlash(
                'warning',
                "La version Android est en cours de publication. Revenez prochainement."
            );

            return $this->redirectToRoute('accueil', ['_fragment' => 'download-app']);
        }

        $response = new BinaryFileResponse($apkPath);
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            'Main-d-Oeuvre-Locale.apk'
        );
        $response->headers->set('Content-Type', 'application/vnd.android.package-archive');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->setPrivate();

        return $response;
    }
}
