<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleVisionService
{
    protected string $apiKey;
    protected string $apiUrl = 'https://vision.googleapis.com/v1/images:annotate';

    public function __construct(protected PdfToImageService $pdfToImage)
    {
        $this->apiKey = (string) env('GOOGLE_VISION_API_KEY');
    }

    /**
     * Extract text from image OR PDF
     */
    public function extractText(string $filePath): string
    {
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        // PDF -> convert to PNG(s) -> OCR each -> merge text
        if ($ext === 'pdf') {
            $images = [];
            try {
                // invoices usually 1 page; set 2/3 if you may have multi-page invoices
                $images = $this->pdfToImage->convertPdfToImages($filePath, maxPages: 2, dpi: 300);

                $fullText = '';
                foreach ($images as $img) {
                    $t = $this->extractTextFromImage($img);
                    if ($t) $fullText .= "\n" . $t;
                }

                return trim($fullText);

            } finally {
                if (!empty($images)) {
                    $this->pdfToImage->cleanup($images);
                }
            }
        }

        // Normal image
        return $this->extractTextFromImage($filePath);
    }

    /**
     * OCR for image file path
     */
    private function extractTextFromImage(string $imagePath): string
    {
        try {
            if (!file_exists($imagePath)) {
                throw new \Exception("Image not found: {$imagePath}");
            }

            $fileContent = file_get_contents($imagePath);
            $base64Content = base64_encode($fileContent);

            $response = Http::timeout(90)->post($this->apiUrl . '?key=' . $this->apiKey, [
                'requests' => [
                    [
                        'image' => ['content' => $base64Content],
                        'features' => [
                            ['type' => 'DOCUMENT_TEXT_DETECTION']
                        ],
                        'imageContext' => [
                            'languageHints' => ['es', 'en', 'it', 'fr', 'de']
                        ]
                    ]
                ]
            ]);

            if ($response->failed()) {
                Log::error('Google Vision API Error: ' . $response->body());
                throw new \Exception('Google Vision API request failed: ' . $response->status());
            }

            $result = $response->json();

            if (isset($result['responses'][0]['fullTextAnnotation']['text'])) {
                return $result['responses'][0]['fullTextAnnotation']['text'];
            }

            if (isset($result['responses'][0]['textAnnotations'][0]['description'])) {
                return $result['responses'][0]['textAnnotations'][0]['description'];
            }

            if (isset($result['responses'][0]['error'])) {
                $error = $result['responses'][0]['error'];
                throw new \Exception('OCR Error: ' . ($error['message'] ?? 'Unknown error'));
            }

            return '';

        } catch (\Exception $e) {
            Log::error('Google Vision OCR Error: ' . $e->getMessage());
            throw $e;
        }
    }
}
