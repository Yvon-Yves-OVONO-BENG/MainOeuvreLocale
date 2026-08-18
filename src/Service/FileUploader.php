<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;

class FileUploader
{
    private string $profileDir;
    private string $cniDir;

    public function __construct(
        string $profileDir,
        string $cniDir,
        private readonly UploadOptimizer $uploadOptimizer,
    )
    {
        $this->profileDir = $profileDir;
        $this->cniDir = $cniDir;
    }

    public function upload(UploadedFile $file, string $prefix, string $type = 'profile'): string
    {
        
        $baseName = sprintf(
            '%s-%s',
            preg_replace('/[^a-zA-Z0-9_-]/', '-', $prefix),
            bin2hex(random_bytes(8))
        );
    
        $target = $type === 'cni'
            ? $this->cniDir
            : $this->profileDir;
    
        return $this->uploadOptimizer->store($file, $target, $baseName);
    }
}
