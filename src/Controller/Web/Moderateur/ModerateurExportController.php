<?php

namespace App\Controller\Web\Moderateur;

use App\Repository\UserRepository;
use App\Repository\ReportRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted(new Expression('is_granted("ROLE_MODERATEUR") or is_granted("ROLE_ADMIN") or is_granted("ROLE_SUPER_ADMIN")'))]
#[Route('/moderateur/export', name: 'moderateur_export_')]
class ModerateurExportController extends AbstractController
{
    #[Route('/reports', name: 'reports', methods: ['GET'])]
    public function reports(ReportRepository $repo): StreamedResponse
    {
        $filename = 'export_reports_' . date('Ymd_His') . '.csv';
        $rows = $repo->exportRows(2000);

        return $this->csv($filename, ['id','status','reason','createdAt'], $rows);
    }

    #[Route('/users', name: 'users', methods: ['GET'])]
    public function users(UserRepository $repo): StreamedResponse
    {
        $filename = 'export_users_' . date('Ymd_His') . '.csv';
        $rows = $repo->exportRows(5000);

        return $this->csv($filename, ['id','email','phone','roles','createdAt'], $rows);
    }

    #[Route('/companies', name: 'companies', methods: ['GET'])]
    public function companies(UserRepository $repo): StreamedResponse
    {
        $filename = 'export_companies_' . date('Ymd_His') . '.csv';
        $rows = $repo->exportRows(5000);

        return $this->csv($filename, ['id','name','email','city','createdAt'], $rows);
    }

    private function csv(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        $response = new StreamedResponse(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM (Excel)
            fputcsv($out, $headers, ';');

            foreach ($rows as $r) {
                fputcsv($out, $r, ';');
            }
            fclose($out);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="'.$filename.'"');

        return $response;
    }
}