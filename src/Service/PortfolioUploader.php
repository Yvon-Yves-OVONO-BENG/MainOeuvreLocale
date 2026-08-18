<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;

class PortfolioUploader
{
    public function __construct(
        private string $targetDir,
        private readonly UploadOptimizer $uploadOptimizer,
    ) {}

    public function upload(UploadedFile $file, string $talentName, int $userId): string
    {
        $safe = $this->slugify($talentName);
        $rand = bin2hex(random_bytes(4));

        $baseName = sprintf('pf-%s-%d-%s', $safe, $userId, $rand);

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
