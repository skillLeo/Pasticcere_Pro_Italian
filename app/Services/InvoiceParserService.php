<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class InvoiceParserService
{
    private ?string $key;

    public function __construct()
    {
        $this->key = config('services.openai.key') ?? null;
    }

    public function parse(string $rawText): array
    {
        if (empty(trim($rawText))) {
            return $this->emptyResult();
        }

        if (empty($this->key)) {
            throw new \RuntimeException(
                'OpenAI API key not configured. Run: php artisan config:clear'
            );
        }

        return $this->callOpenAI($rawText);
    }

    private function callOpenAI(string $rawText): array
    {
        $today = now()->format('Y-m-d');

        $system = 'You are a precision invoice data extraction engine. '
                . 'Output ONLY a valid JSON object — no markdown, no explanation, nothing else.';

        $user = <<<PROMPT
You must extract invoice data and return ONLY a JSON object.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
STEP 1 — CRITICAL: NUMBER FORMAT RULES
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Invoices may use European or international number formats. You MUST detect the format:

European format  → comma is decimal separator, dot is thousands separator
  Examples: "12,50"=12.50  "1.234,56"=1234.56  "8,50€"=8.50  "18.75"=18.75
International    → dot is decimal separator
  Examples: "12.50"=12.50  "1,234.56"=1234.56

RULES:
- If a number has BOTH dot and comma → the LAST one is the decimal separator
  "1.234,56" → 1234.56   "1,234.56" → 1234.56
- If a number has ONLY a comma → it IS the decimal separator → remove it, replace with dot
  "12,50" → 12.50     "8,50" → 8.50     "3,25" → 3.25
- If a number has ONLY a dot → it IS the decimal separator
  "12.50" → 12.50     "18.75" → 18.75
- NEVER output a comma inside a JSON number. All output numbers must use DOT as decimal.
- NEVER multiply or distort values. "12,50" must become 12.50, never 1250.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
STEP 2 — DETECT PRICE COLUMN TYPE
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Look at the column header for prices:
  - If header says "PRECIO/KG" or "PRICE/KG" or "€/KG" → price is already per kg → use directly
  - If header says "PRECIO" or "PRICE" or "IMPORTE" or "TOTAL" → price is for the total quantity listed → YOU MUST DIVIDE

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
STEP 3 — EXTRACT QUANTITY FROM DESCRIPTION
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Item descriptions often include quantity like: "HARINA 00 25KG", "ACEITE OLIVA 10L", "HUEVOS FRESCOS 360UN"

Extract:
  "HARINA 00 25KG"       → name="HARINA 00",      qty=25,   unit="kg"
  "ACEITE OLIVA 10L"     → name="ACEITE OLIVA",    qty=10,   unit="L"
  "TOMATE PERA 5KG"      → name="TOMATE PERA",     qty=5,    unit="kg"
  "AZUCAR BLANCO 10KG"   → name="AZUCAR BLANCO",   qty=10,   unit="kg"
  "SAL MARINA 5KG"       → name="SAL MARINA",      qty=5,    unit="kg"
  "LECHE ENTERA 20L"     → name="LECHE ENTERA",    qty=20,   unit="L"
  "MANTEQUILLA 2KG"      → name="MANTEQUILLA",     qty=2,    unit="kg"
  "QUESO MOZZARELLA 3KG" → name="QUESO MOZZARELLA",qty=3,   unit="kg"
  "HUEVOS FRESCOS 360UN" → name="HUEVOS FRESCOS",  qty=360,  unit="piece"

Rules:
  - Strip the quantity+unit from the ingredient name
  - The clean name must NOT contain numbers or units
  - For liquids (L, litre): treat 1L = 1kg for price/kg purposes
  - For pieces/units (UN, PZ, PCS): include but note in original_unit

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
STEP 4 — CALCULATE price_per_kg
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
If the price column is for total quantity (PRECIO / PRICE / IMPORTE):
  price_per_kg = parsed_price / quantity

Example with European decimals:
  "TOMATE PERA 5KG" → 12,50€
  → parsed_price = 12.50  (comma was decimal separator)
  → quantity = 5 kg
  → price_per_kg = 12.50 / 5 = 2.50   ✓

  "HARINA 00 25KG" → 18,75€
  → parsed_price = 18.75
  → quantity = 25 kg
  → price_per_kg = 18.75 / 25 = 0.75  ✓

  "AZUCAR BLANCO 10KG" → 8,50€
  → parsed_price = 8.50
  → quantity = 10 kg
  → price_per_kg = 8.50 / 10 = 0.85   ✓

If the price column is already per kg:
  price_per_kg = parsed_price (no division)

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
STEP 5 — EXTRACT HEADER FIELDS
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
  supplier_name  → the seller/company name (top of invoice)
  invoice_code   → invoice number/reference (e.g. FAC-2025-001234, or "" if missing)
  date           → invoice date in YYYY-MM-DD format (today={$today} if missing)

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
EXCLUSIONS — skip completely:
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
  - SUBTOTAL, IVA, VAT, TAX, TOTAL, DESCUENTO, SCONTO, DISCOUNT
  - Shipping, delivery, packaging, labels
  - Any line with price = 0

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
OUTPUT — return ONLY this JSON, nothing else:
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
{
  "supplier_name": "...",
  "invoice_code": "...",
  "date": "YYYY-MM-DD",
  "price_column_type": "total_for_qty OR per_kg",
  "items": [
    {
      "name": "clean ingredient name without quantity",
      "price_per_kg": 0.0000,
      "original_unit": "kg|L|piece|...",
      "original_qty": 0,
      "original_price": 0.00,
      "original_price_raw": "exactly as written on invoice e.g. 12,50"
    }
  ]
}

INVOICE TEXT:
---
{$rawText}
---
PROMPT;

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->key,
            'Content-Type'  => 'application/json',
        ])->timeout(90)->post('https://api.openai.com/v1/chat/completions', [
            'model'           => 'gpt-4o',
            'temperature'     => 0,
            'max_tokens'      => 4096,
            'response_format' => ['type' => 'json_object'],
            'messages'        => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user',   'content' => $user],
            ],
        ]);

        if ($response->failed()) {
            Log::error('OpenAI error', ['status' => $response->status(), 'body' => $response->body()]);
            throw new \RuntimeException("OpenAI API {$response->status()}: {$response->body()}");
        }

        $raw = $response->json('choices.0.message.content', '{}');
        $raw = trim(preg_replace('/^```(?:json)?\s*|\s*```$/m', '', $raw));

        $parsed = json_decode($raw, true);

        if (json_last_error() !== JSON_ERROR_NONE || !isset($parsed['items'])) {
            Log::error('InvoiceParser bad JSON', ['raw' => $raw]);
            throw new \RuntimeException('AI returned invalid JSON. Please try again.');
        }

        $items = [];
        foreach ($parsed['items'] as $item) {
            $name    = trim($item['name'] ?? '');
            // Sanitise price: force string→float, handle any stray comma
            $priceKg = $this->parseEuropeanNumber((string)($item['price_per_kg'] ?? '0'));

            if ($name === '' || $priceKg <= 0) {
                continue;
            }

            $origUnit  = trim($item['original_unit']  ?? 'kg');
            $origQty   = (float)($item['original_qty']   ?? 1);
            $origPrice = $this->parseEuropeanNumber((string)($item['original_price'] ?? $priceKg));
            $origRaw   = trim($item['original_price_raw'] ?? '');

            $items[] = [
                'name'             => $name,
                'price_per_kg'     => round($priceKg, 4),
                'original_unit'    => $origUnit,
                'original_qty'     => $origQty,
                'original_price'   => $origPrice,
                'original_price_raw' => $origRaw,
                'notes'            => $origQty > 1
                    ? "{$origRaw} ÷ {$origQty}{$origUnit} = €".number_format($priceKg,4)."/kg"
                    : '',
            ];
        }

        return [
            'supplier_name'     => trim($parsed['supplier_name'] ?? 'Unknown Supplier'),
            'invoice_code'      => trim($parsed['invoice_code']  ?? ''),
            'date'              => $parsed['date'] ?? $today,
            'price_column_type' => $parsed['price_column_type'] ?? 'total_for_qty',
            'items'             => $items,
        ];
    }

    /**
     * Convert a European-format number string to float.
     * "12,50" → 12.50   "1.234,56" → 1234.56   "18.75" → 18.75
     */
    private function parseEuropeanNumber(string $value): float
    {
        $v = trim($value);
        // Remove currency symbols and spaces
        $v = preg_replace('/[€$£\s]/u', '', $v);

        // Both dot and comma present
        if (str_contains($v, '.') && str_contains($v, ',')) {
            $lastDot   = strrpos($v, '.');
            $lastComma = strrpos($v, ',');
            if ($lastComma > $lastDot) {
                // European: 1.234,56 → remove dots (thousands), replace comma with dot
                $v = str_replace('.', '', $v);
                $v = str_replace(',', '.', $v);
            } else {
                // International: 1,234.56 → remove commas (thousands)
                $v = str_replace(',', '', $v);
            }
        } elseif (str_contains($v, ',')) {
            // Only comma → it's the decimal separator
            $v = str_replace(',', '.', $v);
        }
        // Only dot → already correct decimal format

        return (float)$v;
    }

    private function emptyResult(): array
    {
        return [
            'supplier_name' => '',
            'invoice_code'  => '',
            'date'          => now()->format('Y-m-d'),
            'items'         => [],
        ];
    }
}