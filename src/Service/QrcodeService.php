<?php

namespace App\Service;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;

class QrcodeService
{
    public function qrcode(string $query): string
    {
        $logoPath = \dirname(__DIR__, 2) . '/public/build/assets/images/brand/logoQrCode.png';

        $result = (new Builder(
            writer: new PngWriter(),
            writerOptions: [],
            validateResult: false,
            data: $query,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: 400,
            margin: 10,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
            logoPath: $logoPath,
            logoResizeToWidth: 200,
            logoPunchoutBackground: true,
        ))->build();

        // ✅ identifiant long (uniqid('', true)) + sans point
        $namePng = str_replace('.', '', uniqid('', true)) . '.png';

        $targetDir = \dirname(__DIR__, 2) . '/public/build/assets/images/qrcode/';
        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0777, true);
        }

        $result->saveToFile($targetDir . $namePng);

        return $namePng;
    }
}