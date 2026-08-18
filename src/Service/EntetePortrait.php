<?php

namespace App\Service;

use Fpdf\Fpdf;

class EntetePortrait
{
    public function entetePortrait(Fpdf $pdf): Fpdf
    {
        // Logo
        $pdf->Image('../public/build/assets/images/brand/logo.png', 15, 10, 30);
        $pdf->Image('../public/build/assets/images/brand/logoBackground.png', 35, 120, 150);

        // Nom entreprise
        $pdf->SetFont('Helvetica', 'B', 16);
        $pdf->SetXY(50, 12);
        $pdf->Cell(0, 6, utf8_decode("MAIN D'OEUVRE LOCALE"), 0, 1);

        // Slogan / activité
        $pdf->SetFont('Helvetica', '', 10);
        $pdf->SetX(50);
        $pdf->Cell(0, 5, utf8_decode("Plateforme de mise en relation professionnelle"), 0, 1);

        // Ligne séparation élégante
        $pdf->SetDrawColor(200, 200, 200);
        $pdf->Line(15, 47, 195, 47);

        // Bloc informations société (droite)
        $pdf->SetFont('Helvetica', '', 8);
        // $pdf->SetXY(120, 10);

		$pdf->Ln();
		$pdf->SetX(15);
        $pdf->Cell(75, 4, utf8_decode("Email : contact@maindoeuvrelocale.com"), 0, 1, 'L');
		$pdf->SetX(15);
        $pdf->Cell(75, 4, utf8_decode("Yaoundé – Cameroun"), 0, 1, 'L');
		$pdf->SetX(15);
        $pdf->Cell(75, 4, utf8_decode("www.maindoeuvrelocale.com"), 0, 1, 'L');

		$pdf->Ln(15);
        // Titre document
        $pdf->SetFont('Helvetica', 'B', 14);
        $pdf->SetXY(15, 48);
        $pdf->Cell(0, 8, utf8_decode("FACTURE"), 0, 1, 'C');

        // Sous-ligne
        $pdf->SetDrawColor(220, 220, 220);
        $pdf->Line(80, 56, 130, 56);

        return $pdf;
    }
}