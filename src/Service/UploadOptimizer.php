<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Stocke les téléversements avec une optimisation locale et sans API externe.
 *
 * - Images : ImageMagick/Imagick en priorité, GD en repli, sortie WebP.
 * - PDF : Ghostscript est utilisé lorsqu'il est disponible sur le serveur.
 * - DOC/DOCX et autres formats déjà compressés : stockage sans altération.
 *
 * En cas d'indisponibilité d'un moteur, le fichier original reste utilisable.
 */
final class UploadOptimizer
{
    private const MIN_IMAGE_BYTES = 850_000;
    private const TARGET_IMAGE_BYTES = 800_000;
    private const MAX_IMAGE_DIMENSION = 1440;
    private const MAX_IMAGE_PIXELS = 60_000_000;
    private const PDF_TIMEOUT_SECONDS = 18.0;

    private const EXTENSIONS_BY_MIME = [
        'image/jpeg' => 'jpg',
        'image/pjpeg' => 'jpg',
        'image/png' => 'png',
        'image/x-png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        'image/svg+xml' => 'svg',
        'image/heic' => 'heic',
        'image/heif' => 'heif',
        'application/pdf' => 'pdf',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
    ];

    private const OPTIMIZABLE_IMAGE_MIMES = [
        'image/jpeg',
        'image/pjpeg',
        'image/png',
        'image/x-png',
        'image/webp',
        'image/heic',
        'image/heif',
    ];

    public function store(
        UploadedFile $file,
        string $targetDirectory,
        string $baseName
    ): string {
        if (!$file->isValid()) {
            throw new FileException('Le fichier envoyé est invalide.');
        }

        $this->ensureDirectory($targetDirectory);

        $mimeType = strtolower((string) ($file->getMimeType() ?: $file->getClientMimeType()));
        $safeBaseName = $this->sanitizeBaseName($baseName);
        $originalSize = (int) ($file->getSize() ?: 0);

        if (
            in_array($mimeType, self::OPTIMIZABLE_IMAGE_MIMES, true)
            && $originalSize >= self::MIN_IMAGE_BYTES
        ) {
            $optimizedName = $safeBaseName . '.webp';
            $optimizedPath = rtrim($targetDirectory, DIRECTORY_SEPARATOR)
                . DIRECTORY_SEPARATOR
                . $optimizedName;

            if (
                $this->optimizeImage($file->getPathname(), $optimizedPath)
                && is_file($optimizedPath)
                && filesize($optimizedPath) > 0
                && ($originalSize === 0 || filesize($optimizedPath) < $originalSize)
            ) {
                return $optimizedName;
            }

            if (is_file($optimizedPath)) {
                @unlink($optimizedPath);
            }
        }

        $extension = $this->resolveExtension($file, $mimeType);
        $filename = $safeBaseName . '.' . $extension;
        $storedFile = $file->move($targetDirectory, $filename);

        if ($mimeType === 'application/pdf') {
            $this->optimizePdfInPlace($storedFile->getPathname());
        }

        return $filename;
    }

    private function optimizeImage(string $sourcePath, string $targetPath): bool
    {
        if ($this->optimizeImageWithImagick($sourcePath, $targetPath)) {
            return true;
        }

        return $this->optimizeImageWithGd($sourcePath, $targetPath);
    }

    private function optimizeImageWithImagick(string $sourcePath, string $targetPath): bool
    {
        if (
            !extension_loaded('imagick')
            || !class_exists(\Imagick::class)
            || \Imagick::queryFormats('WEBP') === []
        ) {
            return false;
        }

        try {
            \Imagick::setResourceLimit(\Imagick::RESOURCETYPE_MEMORY, 128 * 1024 * 1024);
            \Imagick::setResourceLimit(\Imagick::RESOURCETYPE_MAP, 256 * 1024 * 1024);

            $probe = new \Imagick();
            $probe->pingImage($sourcePath);
            $width = $probe->getImageWidth();
            $height = $probe->getImageHeight();
            $probe->clear();
            $probe->destroy();

            if (
                $width < 1
                || $height < 1
                || ($width * $height) > self::MAX_IMAGE_PIXELS
            ) {
                return false;
            }

            $image = new \Imagick();
            $image->readImage($sourcePath);
            $image->setIteratorIndex(0);

            if (method_exists($image, 'autoOrientImage')) {
                $image->autoOrientImage();
            }

            $image->setImagePage(0, 0, 0, 0);
            $image->thumbnailImage(
                self::MAX_IMAGE_DIMENSION,
                self::MAX_IMAGE_DIMENSION,
                true,
                true
            );
            $image->stripImage();
            $image->setImageFormat('webp');
            $image->setOption('webp:method', '4');

            for ($scaleAttempt = 0; $scaleAttempt < 2; ++$scaleAttempt) {
                foreach ([78, 68] as $quality) {
                    $image->setImageCompressionQuality($quality);
                    $image->writeImage($targetPath);
                    clearstatcache(true, $targetPath);

                    if (
                        is_file($targetPath)
                        && filesize($targetPath) > 0
                        && filesize($targetPath) <= self::TARGET_IMAGE_BYTES
                    ) {
                        $image->clear();
                        $image->destroy();

                        return $this->isReadableImage($targetPath);
                    }
                }

                $currentWidth = $image->getImageWidth();
                $currentHeight = $image->getImageHeight();

                if (max($currentWidth, $currentHeight) <= 960) {
                    break;
                }

                $image->thumbnailImage(
                    max(1, (int) round($currentWidth * 0.78)),
                    max(1, (int) round($currentHeight * 0.78)),
                    true,
                    true
                );
            }

            $image->clear();
            $image->destroy();

            return $this->isReadableImage($targetPath);
        } catch (\Throwable) {
            if (is_file($targetPath)) {
                @unlink($targetPath);
            }

            return false;
        }
    }

    private function optimizeImageWithGd(string $sourcePath, string $targetPath): bool
    {
        if (!function_exists('getimagesize') || !function_exists('imagewebp')) {
            return false;
        }

        $info = @getimagesize($sourcePath);
        if (!is_array($info) || empty($info[0]) || empty($info[1]) || empty($info['mime'])) {
            return false;
        }

        $width = (int) $info[0];
        $height = (int) $info[1];

        if (($width * $height) > self::MAX_IMAGE_PIXELS) {
            return false;
        }

        $source = match (strtolower((string) $info['mime'])) {
            'image/jpeg' => function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($sourcePath) : false,
            'image/png' => function_exists('imagecreatefrompng') ? @imagecreatefrompng($sourcePath) : false,
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($sourcePath) : false,
            default => false,
        };

        if ($source === false) {
            return false;
        }

        try {
            $ratio = min(1, self::MAX_IMAGE_DIMENSION / max($width, $height));
            $targetWidth = max(1, (int) round($width * $ratio));
            $targetHeight = max(1, (int) round($height * $ratio));

            for ($scaleAttempt = 0; $scaleAttempt < 2; ++$scaleAttempt) {
                $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
                if ($canvas === false) {
                    return false;
                }

                imagealphablending($canvas, false);
                imagesavealpha($canvas, true);
                $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
                imagefill($canvas, 0, 0, $transparent);

                imagecopyresampled(
                    $canvas,
                    $source,
                    0,
                    0,
                    0,
                    0,
                    $targetWidth,
                    $targetHeight,
                    $width,
                    $height
                );

                foreach ([78, 68] as $quality) {
                    imagewebp($canvas, $targetPath, $quality);
                    clearstatcache(true, $targetPath);

                    if (
                        is_file($targetPath)
                        && filesize($targetPath) > 0
                        && filesize($targetPath) <= self::TARGET_IMAGE_BYTES
                    ) {
                        imagedestroy($canvas);

                        return $this->isReadableImage($targetPath);
                    }
                }

                imagedestroy($canvas);

                if (max($targetWidth, $targetHeight) <= 960) {
                    break;
                }

                $targetWidth = max(1, (int) round($targetWidth * 0.78));
                $targetHeight = max(1, (int) round($targetHeight * 0.78));
            }

            return $this->isReadableImage($targetPath);
        } catch (\Throwable) {
            if (is_file($targetPath)) {
                @unlink($targetPath);
            }

            return false;
        } finally {
            imagedestroy($source);
        }
    }

    private function optimizePdfInPlace(string $pdfPath): void
    {
        if (
            !is_file($pdfPath)
            || filesize($pdfPath) < 1_000_000
            || !function_exists('proc_open')
        ) {
            return;
        }

        $ghostscript = $this->findGhostscript();
        if ($ghostscript === null) {
            return;
        }

        $temporaryPath = dirname($pdfPath)
            . DIRECTORY_SEPARATOR
            . '.'
            . pathinfo($pdfPath, PATHINFO_FILENAME)
            . '-optimized-'
            . bin2hex(random_bytes(4))
            . '.pdf';

        $pipes = [];
        $process = @proc_open(
            [
                $ghostscript,
                '-sDEVICE=pdfwrite',
                '-dCompatibilityLevel=1.4',
                '-dPDFSETTINGS=/ebook',
                '-dNOPAUSE',
                '-dQUIET',
                '-dBATCH',
                '-dDetectDuplicateImages=true',
                '-dCompressFonts=true',
                '-sOutputFile=' . $temporaryPath,
                $pdfPath,
            ],
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes
        );

        if (!is_resource($process)) {
            return;
        }

        foreach ($pipes as $pipe) {
            stream_set_blocking($pipe, false);
        }
        fclose($pipes[0]);

        $startedAt = microtime(true);
        do {
            $status = proc_get_status($process);
            if (!$status['running']) {
                break;
            }

            if ((microtime(true) - $startedAt) >= self::PDF_TIMEOUT_SECONDS) {
                proc_terminate($process);
                break;
            }

            usleep(100_000);
        } while (true);

        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);

        try {
            if (
                $this->isPdfFile($temporaryPath)
                && filesize($temporaryPath) < filesize($pdfPath)
            ) {
                $this->replaceFileAtomically($temporaryPath, $pdfPath);
            }
        } finally {
            if (is_file($temporaryPath)) {
                @unlink($temporaryPath);
            }
        }
    }

    private function replaceFileAtomically(string $newPath, string $currentPath): void
    {
        $backupPath = $currentPath . '.original-' . bin2hex(random_bytes(4));

        if (!@rename($currentPath, $backupPath)) {
            return;
        }

        if (@rename($newPath, $currentPath)) {
            @unlink($backupPath);

            return;
        }

        @rename($backupPath, $currentPath);
    }

    private function findGhostscript(): ?string
    {
        foreach (['/usr/bin/gs', '/usr/local/bin/gs'] as $candidate) {
            if (is_file($candidate) && is_executable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function isReadableImage(string $path): bool
    {
        return is_file($path)
            && filesize($path) > 0
            && @getimagesize($path) !== false;
    }

    private function isPdfFile(string $path): bool
    {
        if (!is_file($path) || filesize($path) < 5) {
            return false;
        }

        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            return false;
        }

        $signature = fread($handle, 5);
        fclose($handle);

        return $signature === '%PDF-';
    }

    private function ensureDirectory(string $directory): void
    {
        if (
            !is_dir($directory)
            && !@mkdir($directory, 0775, true)
            && !is_dir($directory)
        ) {
            throw new FileException('Impossible de créer le dossier de téléversement.');
        }

        if (!is_writable($directory)) {
            throw new FileException('Le dossier de téléversement n’est pas accessible en écriture.');
        }
    }

    private function sanitizeBaseName(string $baseName): string
    {
        $baseName = pathinfo(trim($baseName), PATHINFO_FILENAME);
        $baseName = preg_replace('/[^a-zA-Z0-9_-]+/', '-', $baseName) ?: 'fichier';
        $baseName = trim($baseName, '-_');

        return substr($baseName !== '' ? $baseName : 'fichier', 0, 180);
    }

    private function resolveExtension(UploadedFile $file, string $mimeType): string
    {
        if (isset(self::EXTENSIONS_BY_MIME[$mimeType])) {
            return self::EXTENSIONS_BY_MIME[$mimeType];
        }

        $extension = strtolower((string) (
            $file->guessExtension()
            ?: $file->getClientOriginalExtension()
            ?: 'bin'
        ));

        return preg_match('/^[a-z0-9]{1,8}$/', $extension) === 1
            ? $extension
            : 'bin';
    }
}
