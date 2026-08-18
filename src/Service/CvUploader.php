<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;

class CvUploader
{
    public function __construct(
        private string $targetDir,
        private readonly UploadOptimizer $uploadOptimizer,
    ) {}

    public function upload(UploadedFile $file, string $talentName, int $userId): string
    {
        // Nettoyage du nom: "Ovono Beng Yvon" -> "ovono-beng-yvon"
        $safeName = $this->slugify($talentName);
        $rand = bin2hex(random_bytes(4)); // court mais unique

        $baseName = sprintf('cv-%s-%d-%s', $safeName, $userId, $rand);

        return $this->uploadOptimizer->store($file, $this->targetDir, $baseName);
    }

    private function slugify(string $text): string
    {
        $text = trim($text);
        $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text;
        $text = preg_replace('~[^\\pL\\d]+~u', '-', $text);
        $text = strtolower($text ?? '');
        $text = preg_replace('~[^-a-z0-9]+~', '', $text);
        $text = preg_replace('~-+~', '-', $text);
        return trim($text, '-') ?: 'talent';
    }
}
