<?php

namespace App\Service;

use Fpdf\Fpdf;
use App\Service\EntetePortrait;
use App\Entity\ElementsPiedDePage\PDF;
use App\Entity\Invoices;

class ImpressionFactureService extends FPDF
{
    public function __construct(
        private EntetePortrait $entetePortrait, 
        )
    { }

    public function impressionFacture(Invoices $facture): PDF
    {
        $pdf = new PDF();
        $pdf->addPage('P');

        $pdf = $this->entetePortrait->entetePortrait($pdf);

        $pdf->SetLeftMargin(10);

        $positionY = 30;
        $pdf->SetXY(15, $positionY);

        if($facture->getQrCode())
        {

            $pdf->Image('../public/build/assets/images/qrcode/'.$facture->getQrCode(), 166, 15, 30);
        }
        // $pdf->Image('../public/images/qrcode/'.$facture->getQrCode(), 165, 67, 34, 34);
        
        $pdf->Ln(30);
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->SetX(15);
        $pdf->Cell(100, 5, 'DETAILS DE LA FACTURE : '.$facture->getInvoiceNumber(), 0, 0, 'L', 0);

        $pdf->SetFont('Arial', 'B', 8);
        $pdf->Cell(80.5, 5, 'Date de la facture : '.date_format($facture->getPayment()->getPaidAt(), 'd/m/Y'), 0, 1, 'R', 0);
        $pdf->SetFont('Arial', 'I', 10);
        $pdf->SetX(15);
        $pdf->Cell(50, 5, 'DETAILS OF ORDER', 0, 0, 'L', 0);

        $pdf->Ln(5);
        $pdf->SetX(15);
        $pdf->SetFont('Arial', '', 10);

        if ($facture->getUser()) 
        {
            $pdf->SetX(15);
            $pdf->Cell(120, 5, utf8_decode("Nom du client :".$facture->getUser()->getPersonalProfile()->getFullName()), 0, 1, 'L', 0);
            $pdf->SetFont('Arial', 'B', 10);

            $pdf->SetX(15);
            $pdf->Cell(0, 5, utf8_decode("Téléphone : ".$facture->getUser()->getPhone() ? $facture->getUser()->getEmail() :"Pas renseigné"), 0, 1, 'L', 0);
        } 
        
        
        $pdf->SetFont('Arial', '', 10);
        $pdf->SetX(15);
        $pdf->Cell(40, 5, utf8_decode("Etat de la facture : "), 0, 0, 'L', 0);
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(0, 5, utf8_decode($facture->getPayment() ? $facture->getPayment()->getSatusPayment()->getStatusPayment() : ""), 0, 1, 'L', 0);

        $pdf->SetX(15);
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(45, 5, utf8_decode("Mode de paiement choisi : "), 0, 0, 'L', 0);
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(0, 5, utf8_decode($facture->getPayment() ? $facture->getPayment()->getProvider()->getProvider() : ""), 0, 1, 'L', 0);

        $pdf->SetX(15);
        $pdf->Cell(75, 5, utf8_decode("Cette facture s'élève à un montant de : "), 0, 0, 'L', 0);
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(0, 5, utf8_decode(number_format($facture->getPayment()->getAmount(), 0, '', ' ')." FCFA"), 0, 1, 'L', 0);

        $positionY = 80;
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->SetX(15);
        
        $pdf->Cell(0, 10, utf8_decode('Eléménts de la facture'), 0, 1, 'L', 0);

        $pdf->SetX(15);
        $pdf->SetFillColor(240,240,240);
        $pdf->Cell(7, 5, utf8_decode('N°'), 1, 0, 'C', true);
        $pdf->Cell(45, 5, utf8_decode('Plan'), 1, 0, 'C', true);
        $pdf->Cell(45, 5, utf8_decode('Durée'), 1, 0, 'C', true);
        $pdf->Cell(45, 5, utf8_decode('Montant (FCFA)'), 1, 0, 'C', true);
        $pdf->Cell(40, 5, utf8_decode('Total (FCFA)'), 1, 1, 'C', true);

        $pdf->SetX(15);
        $pdf->SetFont('Arial', '', 8);

        $pdf->SetX(15);
        $pdf->Cell(7, 5, utf8_decode('1'), 1, 0, 'C');
        $pdf->Cell(45, 5, utf8_decode($facture->getPayment()->getSubscription()->getPlan()->getPlan()), 1, 0, 'C');
        $pdf->Cell(45, 5, utf8_decode($facture->getPayment()->getSubscription()->getPlan()->getDurationDays()), 1, 0, 'C');
        $pdf->Cell(45, 5, utf8_decode(number_format($facture->getPayment()->getAmount(), 0, '', ' ')." FCFA"), 1, 0, 'C');
        $pdf->Cell(40, 5, utf8_decode(number_format($facture->getPayment()->getAmount(), 0, '', ' ')." FCFA"), 1, 1, 'C');

            
        $pdf->SetX(15);
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(142, 5, utf8_decode('Montant HT'), 0, 0, 'R');
        $pdf->Cell(40, 5, utf8_decode(number_format($facture->getPayment()->getAmount(), 0, '', ' ')), 1, 1, 'C');

        $pdf->SetX(15);
        $pdf->Cell(142, 5, utf8_decode('TVA'), 0, 0, 'R');
        $pdf->Cell(40, 5, utf8_decode("0%"), 1, 1, 'C');

        $pdf->SetX(15);
        $pdf->Cell(142, 5, utf8_decode('Montant TTC'), 0, 0, 'R');
        $pdf->Cell(40, 5, utf8_decode(number_format($facture->getPayment()->getAmount(), 0, '', ' ')), 1, 1, 'C');

        $pdf->SetFont('Arial', 'B', 12);
        $pdf->SetX(15);
        $pdf->SetFillColor(202, 219, 255);
        $pdf->Cell(142, 5, utf8_decode('NET A PAYER'), 0, 0, 'R');
        $pdf->Cell(40, 5, utf8_decode(number_format($facture->getPayment()->getAmount(), 0, '', ' ')." FCFA"), 1, 1, 'C', true);

        
        

        
        
        $pdf->AliasNbPages();
        return $pdf;
    }
}

