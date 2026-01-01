<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PdfToImageService
{
    public function convertPdfToImages(string $pdfPath, int $maxPages = 1, int $dpi = 300): array
    {
        if (!file_exists($pdfPath)) {
            throw new \Exception("PDF not found at: {$pdfPath}");
        }

        $tmpDir = storage_path('app/tmp/pdf_ocr');
        if (!File::exists($tmpDir)) {
            File::makeDirectory($tmpDir, 0755, true);
        }

        $base = $tmpDir . '/' . Str::uuid()->toString();
        $images = [];

        // Try Imagick first (if installed)
        if (extension_loaded('imagick')) {
            try {
                $pageCount = $this->getPdfPageCountImagick($pdfPath);
                $limit = min($pageCount, max(1, $maxPages));

                for ($i = 0; $i < $limit; $i++) {
                    $im = new \Imagick();
                    $im->setResolution($dpi, $dpi);
                    $im->readImage($pdfPath . '[' . $i . ']');
                    $im->setImageFormat('png');
                    $im->setImageCompressionQuality(100);

                    $out = "{$base}_page_" . ($i + 1) . ".png";
                    $im->writeImage($out);
                    $im->clear();
                    $im->destroy();

                    $images[] = $out;
                }

                return $images;
            } catch (\Throwable $e) {
                Log::warning('Imagick conversion failed, fallback to pdftoppm: ' . $e->getMessage());
            }
        }

        // Fallback: pdftoppm (Poppler)
        $pdftoppm = $this->resolvePdftoppmBinary();
        if (!$pdftoppm) {
            throw new \Exception("PDF conversion failed. Install Poppler (pdftoppm) OR enable Imagick + Ghostscript.");
        }

        $first = 1;
        $last  = max(1, $maxPages);

        // Ensure PATH for exec context as well
        $pathPrefix = 'PATH=/usr/local/bin:/opt/homebrew/bin:/usr/bin:/bin:$PATH ';

        $cmd = sprintf(
            '%s%s -png -r %d -f %d -l %d %s %s 2>&1',
            $pathPrefix,
            escapeshellcmd($pdftoppm),
            $dpi,
            $first,
            $last,
            escapeshellarg($pdfPath),
            escapeshellarg($base)
        );

        exec($cmd, $outLines, $exitCode);

        if ($exitCode !== 0) {
            throw new \Exception("pdftoppm failed:\n" . implode("\n", $outLines));
        }

        for ($p = 1; $p <= $last; $p++) {
            $candidate = "{$base}-{$p}.png";
            if (file_exists($candidate)) {
                $images[] = $candidate;
            }
        }

        if (empty($images)) {
            throw new \Exception("pdftoppm produced no images.");
        }

        return $images;
    }

    public function cleanup(array $paths): void
    {
        foreach ($paths as $p) {
            if ($p && file_exists($p)) {
                @unlink($p);
            }
        }
    }

    private function resolvePdftoppmBinary(): ?string
    {
        $candidates = [
            '/opt/homebrew/bin/pdftoppm', // Apple Silicon
            '/usr/local/bin/pdftoppm',    // Intel mac
            '/usr/bin/pdftoppm',
        ];

        foreach ($candidates as $p) {
            if (file_exists($p) && is_executable($p)) {
                return $p;
            }
        }

        // last try: command -v (depends on PATH)
        $out = @shell_exec('command -v pdftoppm 2>/dev/null');
        $out = trim((string) $out);
        return $out !== '' ? $out : null;
    }

    private function getPdfPageCountImagick(string $pdfPath): int
    {
        $im = new \Imagick();
        $im->pingImage($pdfPath);
        $count = $im->getNumberImages();
        $im->clear();
        $im->destroy();
        return max(1, (int) $count);
    }
}
