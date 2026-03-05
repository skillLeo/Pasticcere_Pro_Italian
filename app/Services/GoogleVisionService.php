<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleVisionService
{
    private string $apiKey;

    public function __construct()
    {
        $this->apiKey = config('services.google_vision.key');
    }

    /**
     * Extract all text from an image or PDF file.
     *
     * @param  string $filePath  Absolute path to uploaded file
     * @param  string $mimeType  e.g. image/jpeg | image/png | application/pdf
     * @return string            Raw OCR text
     */
    public function extractText(string $filePath, string $mimeType): string
    {
        $base64 = base64_encode(file_get_contents($filePath));

        return $mimeType === 'application/pdf'
            ? $this->extractFromPdf($base64)
            : $this->extractFromImage($base64, $mimeType);
    }

    private function extractFromImage(string $base64, string $mimeType): string
    {
        $payload = [
            'requests' => [[
                'image'        => ['content' => $base64],
                'features'     => [['type' => 'DOCUMENT_TEXT_DETECTION']],
                'imageContext' => [
                    'languageHints' => ['it', 'en', 'de', 'fr', 'es', 'ar'],
                ],
            ]],
        ];

        $response = Http::timeout(60)
            ->post("https://vision.googleapis.com/v1/images:annotate?key={$this->apiKey}", $payload);

        if ($response->failed()) {
            Log::error('Vision image error', ['status' => $response->status(), 'body' => $response->body()]);
            throw new \RuntimeException('Google Vision API failed (status ' . $response->status() . '): ' . $response->body());
        }

        $json = $response->json();

        if (!empty($json['responses'][0]['error'])) {
            $err = $json['responses'][0]['error'];
            throw new \RuntimeException("Vision API error [{$err['code']}]: {$err['message']}");
        }

        return $json['responses'][0]['fullTextAnnotation']['text'] ?? '';
    }

    private function extractFromPdf(string $base64Pdf): string
    {
        // files:annotate supports PDF natively (synchronous, up to 5 pages)
        $payload = [
            'requests' => [[
                'inputConfig' => [
                    'content'  => $base64Pdf,
                    'mimeType' => 'application/pdf',
                ],
                'features' => [['type' => 'DOCUMENT_TEXT_DETECTION']],
                'pages'    => [1, 2, 3, 4, 5],
            ]],
        ];

        $response = Http::timeout(90)
            ->post("https://vision.googleapis.com/v1/files:annotate?key={$this->apiKey}", $payload);

        if ($response->failed()) {
            Log::error('Vision PDF error', ['status' => $response->status(), 'body' => $response->body()]);
            throw new \RuntimeException('Google Vision PDF API failed: ' . $response->body());
        }

        $json      = $response->json();
        $pageResps = $json['responses'][0]['responses'] ?? [];
        $fullText  = '';

        foreach ($pageResps as $pageResp) {
            $fullText .= ($pageResp['fullTextAnnotation']['text'] ?? '') . "\n\n";
        }

        return trim($fullText);
    }
}