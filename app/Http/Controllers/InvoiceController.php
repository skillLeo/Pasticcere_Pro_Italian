<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\InvoiceBatch;
use App\Models\InvoiceItem;
use App\Models\Ingredient;
use App\Models\Cost;
use App\Models\CostCategory;
use App\Services\GoogleVisionService;
use App\Services\InvoiceParserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InvoiceController extends Controller
{
    public function __construct(
        protected GoogleVisionService $visionService,
        protected InvoiceParserService $parserService
    ) {
    }

    public function upload(Request $request)
    {
        $request->validate([
            'invoice_type' => 'required|in:ingredient,cost',

            // bulk
            'invoice_files' => 'nullable|array|min:1|max:10',
            'invoice_files.*' => 'file|mimes:jpg,jpeg,png,pdf|max:10240',

            // single fallback
            'invoice_file' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:10240',
        ]);

        $files = $request->file('invoice_files', []);
        if (empty($files) && $request->hasFile('invoice_file')) {
            $files = [$request->file('invoice_file')];
        }

        if (empty($files)) {
            return response()->json(['success' => false, 'message' => 'No files uploaded.'], 422);
        }

        try {
            DB::beginTransaction();

            $batch = \App\Models\InvoiceBatch::create([
                'user_id' => auth()->id(),
                'invoice_type' => $request->invoice_type,
                'status' => 'processing',
            ]);

            $batchTotal = 0;
            $invoiceIds = [];

            foreach ($files as $file) {
                $fileName = time() . '_' . uniqid() . '_' . str_replace(' ', '_', $file->getClientOriginalName());
                $filePath = $file->storeAs('invoices', $fileName, 'public');
                $fullPath = public_path('storage/' . $filePath);

                if (!file_exists($fullPath)) {
                    throw new \Exception("File upload failed: {$fullPath}");
                }

                $ext = strtolower($file->getClientOriginalExtension());
                $fileType = in_array($ext, ['jpg', 'jpeg', 'png']) ? 'image' : 'pdf';

                // ✅ IMPORTANT: batch_id will now save because it's fillable
                $invoice = \App\Models\Invoice::create([
                    'user_id' => auth()->id(),
                    'batch_id' => $batch->id,          // ✅ MUST SAVE
                    'file_path' => $filePath,
                    'file_type' => $fileType,
                    'invoice_type' => $request->invoice_type,
                    'status' => 'processing',
                ]);

                $invoiceIds[] = $invoice->id;

                $text = $this->visionService->extractText($fullPath);

                $invoice->update([
                    'raw_text' => $text,
                ]);

                if ($request->invoice_type === 'ingredient') {
                    $items = $this->parserService->parseIngredientInvoice($text);

                    if (empty($items)) {
                        throw new \Exception("No ingredients found in: " . $file->getClientOriginalName());
                    }

                    $totalAmount = array_sum(array_column($items, 'price'));
                    $batchTotal += $totalAmount;

                    foreach ($items as $item) {
                        \App\Models\InvoiceItem::create([
                            'invoice_id' => $invoice->id,
                            'ingredient_name' => $item['ingredient_name'],
                            'normalized_name' => $item['normalized_name'],
                            'price' => $item['price'],
                            'quantity' => $item['quantity'],
                            'unit' => $item['unit'],
                            'divider' => $item['divider'],
                            'price_per_kg' => $item['price_per_kg'],
                            'existing_ingredient_id' => $item['existing_ingredient_id'],
                            'similarity_score' => $item['similarity_score'],
                            'is_new' => $item['is_new'],
                        ]);
                    }

                    $invoice->update([
                        'total_amount' => $totalAmount,
                        'status' => 'completed',
                        'parsed' => true,
                    ]);
                } else {
                    // if cost invoice type later: extract total and save invoice->total_amount
                    $invoice->update([
                        'status' => 'completed',
                        'parsed' => true,
                    ]);
                }
            }

            $batch->update([
                'total_amount' => $batchTotal ?: null,
                'status' => 'completed',
            ]);

            DB::commit();

            // ✅ Redirect logic
            if (count($files) === 1) {
                return response()->json([
                    'success' => true,
                    'redirect_url' => route('invoices.preview', $invoiceIds[0]),
                ]);
            }

            return response()->json([
                'success' => true,
                'redirect_url' => route('invoices.batch.preview', $batch->id),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Batch Upload Error: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }


    public function preview($id)
    {
        $invoice = Invoice::with('items.existingIngredient')->findOrFail($id);
        return view('invoices.preview', compact('invoice'));
    }

    public function batchPreview($batchId)
    {
        $batch = InvoiceBatch::with(['invoices.items.existingIngredient'])->findOrFail($batchId);
        return view('invoices.batch_preview', compact('batch'));
    }

    public function updateItem(Request $request, $itemId)
    {
        $request->validate([
            'divider' => 'required|numeric|min:0.01',
            'ingredient_name' => 'nullable|string',
        ]);

        $item = InvoiceItem::findOrFail($itemId);

        $divider = (float) $request->divider;
        $pricePerKg = $divider > 0 ? ((float) $item->price / $divider) : (float) $item->price;

        $item->update([
            'divider' => $divider,
            'price_per_kg' => $pricePerKg,
            'ingredient_name' => $request->ingredient_name ?? $item->ingredient_name,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Item updated successfully',
            'price_per_kg' => number_format($pricePerKg, 2),
        ]);
    }

    public function saveToDatabase($id)
    {
        // your existing single save logic (keep it)
        // ✅ unchanged
        // ...
    }

    public function saveBatchToDatabase($batchId)
    {
        try {
            DB::beginTransaction();

            $batch = InvoiceBatch::with('invoices.items')->findOrFail($batchId);

            $ingredientCategory = CostCategory::firstOrCreate(['name' => 'Ingredients'], ['name' => 'Ingredients']);

            $grandTotal = 0;
            $createdTotal = 0;
            $updatedTotal = 0;

            foreach ($batch->invoices as $invoice) {
                if ($invoice->items->isEmpty())
                    continue;

                $invoiceTotal = 0;
                $createdCount = 0;
                $updatedCount = 0;

                foreach ($invoice->items as $item) {
                    if ($item->existing_ingredient_id) {
                        Ingredient::where('id', $item->existing_ingredient_id)->update([
                            'price_per_kg' => $item->price_per_kg,
                            'user_id' => auth()->id(),
                        ]);
                        $updatedCount++;
                    } else {
                        Ingredient::create([
                            'ingredient_name' => $item->normalized_name ?: $item->ingredient_name,
                            'price_per_kg' => $item->price_per_kg,
                            'user_id' => auth()->id(),
                        ]);
                        $createdCount++;
                    }

                    $invoiceTotal += (float) $item->price;
                }

                $supplier = $this->parserService->extractSupplier($invoice->raw_text) ?? 'Invoice Upload';
                $date = $this->parserService->extractDate($invoice->raw_text) ?? now()->format('Y-m-d');

                // ✅ create one Cost per invoice (recommended)
                Cost::create([
                    'supplier' => $supplier,
                    'amount' => $invoiceTotal,
                    'due_date' => $date,
                    'category_id' => $ingredientCategory->id,
                    'cost_identifier' => 'INV-' . $invoice->id,
                ]);

                $grandTotal += $invoiceTotal;
                $createdTotal += $createdCount;
                $updatedTotal += $updatedCount;

                $invoice->update(['parsed' => true, 'status' => 'completed']);
                $invoice->items()->delete();
            }

            $batch->update([
                'status' => 'completed',
                'total_amount' => $grandTotal ?: null,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Batch saved! Created {$createdTotal} ingredients, Updated {$updatedTotal}. Total €" . number_format($grandTotal, 2),
                'redirect_url' => route('ingredients.index'),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Save Batch Error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to save batch: ' . $e->getMessage(),
            ], 500);
        }
    }
}
