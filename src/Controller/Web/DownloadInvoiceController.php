<?php

namespace App\Controller\Web;
use App\Repository\InvoicesRepository;
use App\Service\ImpressionFactureService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER', message: 'Accès refusé. Connectez-vous')]
class DownloadInvoiceController extends AbstractController
{
    #[Route('/invoices-download/{slug}', name: 'invoice_download')]
    public function downloadInvoice(InvoicesRepository $invoicesRepository, ImpressionFactureService $impressionFactureService, string $slug): Response
    {
        $facture = $invoicesRepository->findOneBy(['slug' => $slug]);
        if (!$facture) {
            throw $this->createNotFoundException('Facture introuvable');
        }

        $pdf = $impressionFactureService->impressionFacture($facture);

        return new Response($pdf->Output(utf8_decode("Facture ".utf8_decode($facture->getInvoiceNumber()." de ".$facture->getUser()->getPersonalProfile()->getFullName())), "I"), 200, ['content-type' => 'application/pdf']);
        
    }
    
}

