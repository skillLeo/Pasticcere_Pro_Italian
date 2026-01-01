<?php

namespace App\Services;

use App\Models\Ingredient;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class InvoiceParserService
{
    /** @var \Illuminate\Support\Collection|null */
    private $ingredientsCache = null;

    public function parseIngredientInvoice(string $text): array
    {
        Log::info('=== PARSING START ===');

        $text = str_replace(["\r\n", "\r"], "\n", $text);
        Log::info("Raw OCR text (len=" . strlen($text) . ")");

        $lines = array_values(array_filter(array_map([$this, 'cleanLine'], explode("\n", $text))));

        // 1) Try robust line parser (most invoices are single-line items)
        $items = [];
        foreach ($lines as $line) {
            if ($this->isHeaderLine($line) || $this->isEndMarker($line)) {
                continue;
            }

            $parsed = $this->parseItemLine($line);
            if ($parsed) {
                $items[] = $parsed;
                Log::info("✓ Item: {$parsed['ingredient_name']} | {$parsed['quantity']}{$parsed['unit']} | €{$parsed['price']}");
            }
        }

        // 2) If nothing found, try multi-line fallback
        if (empty($items)) {
            Log::info('No single-line items found, trying multi-line fallback...');
            $items = $this->parseMultiLineFormat($lines);
        }

        Log::info('=== FOUND ' . count($items) . ' ITEMS ===');
        return $items;
    }

    /**
     * Parse common invoice item lines like:
     * - "TOMATE PERA 5KG 12,50€"
     * - "HARINA 00 25 KG €18.75"
     * - "ACEITE OLIVA 10L 42,00€"
     * - even "TOMATEPERA5KG12,50€" (OCR merged)
     */
    private function parseItemLine(string $line): ?array
    {
        $original = $line;

        // Must contain a unit+qty somewhere, otherwise ignore
        if (!preg_match('/\d+(?:[.,]\d+)?\s*(kg|kgs|g|gr|l|lt|ltr|ml|un|uds|ud|u|pcs|pz|pieza|piezas|unidades)\b/iu', $line)) {
            return null;
        }

        // 1) Extract PRICE from the END of line (with € before or after)
        // Matches: "12,50€" or "€12.50" or "12.50"
        if (!preg_match('/(?:€\s*)?(\d{1,3}(?:[.,]\d{3})*(?:[.,]\d{2})|\d+(?:[.,]\d+)?)\s*€?\s*$/u', $line, $pm)) {
            return null;
        }

        $priceRaw = $pm[1];
        $price = $this->toNumber($priceRaw);

        // Safety: skip crazy values that are likely invoice numbers/years
        if ($price <= 0 || $price >= 100000) {
            return null;
        }

        // Remove price part from the end
        $lineWithoutPrice = preg_replace('/(?:€\s*)?'.preg_quote($pm[1], '/').'\s*€?\s*$/u', '', $line);
        $lineWithoutPrice = trim($lineWithoutPrice);

        // 2) Extract QTY+UNIT from the END of remaining string (space optional)
        if (!preg_match('/(\d+(?:[.,]\d+)?)\s*(kg|kgs|g|gr|l|lt|ltr|ml|un|uds|ud|u|pcs|pz|pieza|piezas|unidades)\s*$/iu', $lineWithoutPrice, $qm)) {
            // Sometimes OCR merges: "...5KG12,50€" so qty+unit are right before price.
            // Try extracting qty+unit from original line too.
            if (!preg_match('/(\d+(?:[.,]\d+)?)\s*(kg|kgs|g|gr|l|lt|ltr|ml|un|uds|ud|u|pcs|pz|pieza|piezas|unidades)\s*(?:€\s*)?'.preg_quote($pm[1], '/').'\s*€?\s*$/iu', $original, $qm2)) {
                return null;
            }
            $qty = $this->toNumber($qm2[1]);
            $unit = $this->normalizeUnit($qm2[2]);
            // Name = remove that tail from original
            $name = preg_replace('/(\d+(?:[.,]\d+)?)\s*'.preg_quote($qm2[2], '/').'\s*(?:€\s*)?'.preg_quote($pm[1], '/').'\s*€?\s*$/iu', '', $original);
        } else {
            $qty = $this->toNumber($qm[1]);
            $unit = $this->normalizeUnit($qm[2]);
            // Name = remove qty+unit from remaining
            $name = preg_replace('/(\d+(?:[.,]\d+)?)\s*'.preg_quote($qm[2], '/').'\s*$/iu', '', $lineWithoutPrice);
        }

        $name = trim($this->cleanupName($name));
        if ($name === '' || $qty <= 0) {
            return null;
        }

        return $this->buildItem($name, $qty, $unit, $price);
    }

    /**
     * Multi-line fallback: detects a name line, then searches next lines for qty/unit and price
     */
    private function parseMultiLineFormat(array $lines): array
    {
        $items = [];

        // Start after headers if possible
        $startIndex = 0;
        foreach ($lines as $idx => $line) {
            if (stripos($line, 'DESCRIP') !== false || stripos($line, 'DESCRIPTION') !== false) {
                $startIndex = $idx + 1;
                break;
            }
        }

        for ($i = $startIndex; $i < count($lines); $i++) {
            $line = $lines[$i];

            if ($this->isHeaderLine($line) || $this->isEndMarker($line)) continue;

            // treat as name if it has letters and doesn't look like only numbers/currency
            if (!preg_match('/\p{L}{2,}/u', $line)) continue;
            if (preg_match('/^[\d\s€.,-]+$/u', $line)) continue;

            $name = $line;
            $qty = null;
            $unit = null;
            $price = null;

            for ($j = 1; $j <= 6 && ($i + $j) < count($lines); $j++) {
                $next = $lines[$i + $j];

                if ($this->isHeaderLine($next) || $this->isEndMarker($next)) break;

                // Stop if next line looks like a new name AND we already found something
                if ($qty && $price && preg_match('/\p{L}{2,}/u', $next) && !preg_match('/\d/u', $next)) {
                    break;
                }

                if (!$qty && preg_match('/(\d+(?:[.,]\d+)?)\s*(kg|kgs|g|gr|l|lt|ltr|ml|un|uds|ud|u|pcs|pz|unidades)\b/iu', $next, $m)) {
                    $qty = $this->toNumber($m[1]);
                    $unit = $this->normalizeUnit($m[2]);
                }

                if (!$price && preg_match('/(?:€\s*)?(\d+(?:[.,]\d+)?)\s*€?/u', $next, $m)) {
                    $candidate = $this->toNumber($m[1]);
                    if ($candidate > 0 && $candidate < 100000) $price = $candidate;
                }
            }

            if ($qty && $unit && $price) {
                $items[] = $this->buildItem($name, $qty, $unit, $price);
            }
        }

        return $items;
    }

    private function cleanLine(string $line): string
    {
        $line = trim($line);
        // normalize weird spaces
        $line = preg_replace('/[^\S\r\n]+/u', ' ', $line);
        // unify currency
        $line = str_replace(['EUR', 'EURO'], '€', $line);
        return trim($line);
    }

    private function cleanupName(string $name): string
    {
        // remove trailing separators
        $name = preg_replace('/[-–—:|]+$/u', '', $name);
        // collapse spaces
        $name = preg_replace('/\s+/u', ' ', $name);
        return trim($name);
    }

    private function normalizeUnit(string $unit): string
    {
        $u = Str::upper($unit);

        return match (true) {
            in_array($u, ['KG', 'KGS']) => 'KG',
            in_array($u, ['G', 'GR']) => 'G',
            in_array($u, ['L', 'LT', 'LTR']) => 'L',
            $u === 'ML' => 'ML',
            in_array($u, ['UN', 'U', 'UD', 'UDS', 'UNIDADES', 'PIEZA', 'PIEZAS', 'PCS', 'PZ']) => 'UN',
            default => $u,
        };
    }

    private function toNumber(string $value): float
    {
        $v = trim($value);

        // If it has both "." and "," -> assume "." thousands and "," decimals (e.g. 1.234,56)
        if (str_contains($v, '.') && str_contains($v, ',')) {
            $v = str_replace('.', '', $v);
            $v = str_replace(',', '.', $v);
        } else {
            // Only comma -> decimal
            $v = str_replace(',', '.', $v);
        }

        // remove anything non numeric/dot/minus
        $v = preg_replace('/[^0-9.\-]/', '', $v);

        return (float) $v;
    }

    private function buildItem(string $name, $qty, string $unit, $price): array
    {
        $quantity = (float) $qty;
        $price = (float) $price;

        $normalizedName = $this->normalizeIngredientName($name);
        $existing = $this->findSimilarIngredient($normalizedName);

        // Default divider = quantity (treat invoice price as total for the pack)
        // User can change divider to 1 in preview if price is already per kg/unit.
        $divider = $quantity > 0 ? $quantity : 1;
        $pricePerKg = $divider > 0 ? round($price / $divider, 2) : $price;

        return [
            'ingredient_name' => trim($name),
            'normalized_name' => $normalizedName,
            'quantity' => $quantity,
            'unit' => $unit,
            'price' => $price,
            'divider' => $divider,
            'price_per_kg' => $pricePerKg,
            'existing_ingredient_id' => $existing?->id,
            'similarity_score' => $existing ? $this->calculateSimilarity($normalizedName, $existing->ingredient_name) : 0,
            'is_new' => !$existing,
        ];
    }

    private function isHeaderLine(string $line): bool
    {
        $headers = [
            'DESCRIPTION', 'QUANTITY', 'PRICE', 'INVOICE', 'DATE',
            'SUPPLIER', 'CUSTOMER', 'ADDRESS', 'PHONE', 'IBAN',
            'DESCRIPCIÓN', 'CANTIDAD', 'PRECIO', 'FACTURA', 'FECHA'
        ];

        foreach ($headers as $h) {
            if (stripos($line, $h) !== false) return true;
        }
        return false;
    }

    private function isEndMarker(string $line): bool
    {
        $markers = ['SUMMARY', 'SUBTOTAL', 'TOTAL', 'PAYMENT', 'IVA', 'VAT'];
        foreach ($markers as $marker) {
            if (stripos($line, $marker) !== false) return true;
        }
        return false;
    }

    private function normalizeIngredientName(string $name): string
    {
        $name = Str::lower($name);
        $name = preg_replace('/\b\d+\b/u', '', $name);

        $translations = [
            'pear tomatoes' => 'tomato', 'mozzarella cheese' => 'mozzarella',
            'olive oil' => 'oil', 'white sugar' => 'sugar', 'sea salt' => 'salt',
            'whole milk' => 'milk', 'fresh eggs' => 'eggs',
            'tomate' => 'tomato', 'queso' => 'cheese', 'aceite' => 'oil',
            'harina' => 'flour', 'azucar' => 'sugar', 'sal' => 'salt',
            'leche' => 'milk', 'mantequilla' => 'butter', 'huevo' => 'egg',
        ];

        foreach ($translations as $from => $to) {
            $name = str_replace($from, $to, $name);
        }

        $name = preg_replace('/[^a-z0-9\s]/', '', $name);
        $name = preg_replace('/\s+/', ' ', $name);

        return trim($name);
    }

    private function findSimilarIngredient(string $searchName): ?Ingredient
    {
        if ($this->ingredientsCache === null) {
            $this->ingredientsCache = Ingredient::select('id', 'ingredient_name')->get();
        }

        $bestMatch = null;
        $highestScore = 0;

        foreach ($this->ingredientsCache as $ingredient) {
            $score = $this->calculateSimilarity($searchName, Str::lower($ingredient->ingredient_name));
            if ($score > 60 && $score > $highestScore) {
                $bestMatch = $ingredient;
                $highestScore = $score;
            }
        }

        return $bestMatch;
    }

    private function calculateSimilarity(string $str1, string $str2): int
    {
        similar_text($str1, $str2, $percent);
        return (int) $percent;
    }

    public function extractTotal(string $text): ?float
    {
        if (preg_match('/\bTOTAL\b.*?(?:€\s*)?(\d{1,3}(?:[.,]\d{3})*(?:[.,]\d{2})|\d+(?:[.,]\d+)?)(?:\s*€)?/iu', $text, $m)) {
            return $this->toNumber($m[1]);
        }
        return null;
    }

    public function extractSupplier(string $text): ?string
    {
        if (preg_match('/(Proveedores?\s+Alimentarios\s+SA|Food\s+Suppliers?)/iu', $text, $m)) {
            return trim($m[1]);
        }
        return 'Invoice Upload';
    }

    public function extractDate(string $text): ?string
    {
        if (preg_match('/(\d{2})[\/\-](\d{2})[\/\-](\d{4})/u', $text, $m)) {
            return $m[3] . '-' . $m[2] . '-' . $m[1];
        }
        return now()->format('Y-m-d');
    }
}
