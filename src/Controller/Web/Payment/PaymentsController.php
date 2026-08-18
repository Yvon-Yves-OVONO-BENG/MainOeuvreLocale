<?php

namespace App\Controller\Web\Payment;

use App\Entity\User;
use App\Repository\InvoicesRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

class PaymentsController extends AbstractController
{
    #[Route('/payments', name: 'payments_index', methods: ['GET'])]
    public function index(Request $request, InvoicesRepository $invoicesRepo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $me = $this->getUser();
        if (!$me instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $page  = max(1, (int) $request->query->get('page', 1));
        $limit = 10;
        $q     = trim((string) $request->query->get('q', ''));

        $result = $invoicesRepo->findPaginatedWithPaymentByUser($me, $page, $limit, $q);

        $total = (int) $result['total'];
        $pages = max(1, (int) ceil($total / $limit));

        return $this->render('payments/payments.html.twig', [
            'invoices' => $result['items'],
            'total'    => $total,
            'page'     => $page,
            'pages'    => $pages,
            'limit'    => $limit,
            'q'        => $q,
        ]);
    }

    // #[Route('/invoices/{slug}/download', name: 'invoice_download', methods: ['GET'])]
    // public function download(string $slug, InvoicesRepository $invoicesRepo): Response
    // {
    //     $this->denyAccessUnlessGranted('ROLE_USER');

    //     $me = $this->getUser();
    //     if (!$me instanceof User) {
    //         throw $this->createAccessDeniedException();
    //     }

    //     $invoice = $invoicesRepo->findOneBySlugForUser($slug, $me);
    //     if (!$invoice) {
    //         throw $this->createNotFoundException("Facture introuvable.");
    //     }

    //     // ✅ À ADAPTER : où tu stockes réellement les PDF
    //     // Exemple : public/uploads/invoices/{slug}.pdf
    //     $baseDir = (string) $this->getParameter('invoices_dir');
    //     $file    = rtrim($baseDir, '/\\') . DIRECTORY_SEPARATOR . $invoice->getSlug() . '.pdf';

    //     if (!is_file($file)) {
    //         throw $this->createNotFoundException("Fichier de facture introuvable.");
    //     }

    //     $downloadName = ($invoice->getInvoiceNumber() ?: ('INV-' . $invoice->getId())) . '.pdf';

    //     $response = new BinaryFileResponse($file);
    //     $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $downloadName);

    //     return $response;
    // }
}