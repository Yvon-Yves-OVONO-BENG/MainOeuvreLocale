<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;

class MediaUploader
{
    public function __construct(
        private string $targetDir,
        private readonly UploadOptimizer $uploadOptimizer,
    ) {}

    public function upload(UploadedFile $file, string $prefix = 'media'): string
    {
        $baseName = $prefix . '-' . bin2hex(random_bytes(8));

        return $this->uploadOptimizer->store($file, $this->targetDir, $baseName);
    }
}
