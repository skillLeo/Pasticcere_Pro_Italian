<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Ingredient;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Models\Recipe;               // ← servono anche le ricette
use App\Services\GoogleVisionService;
use App\Services\InvoiceParserService;
use Illuminate\Support\Facades\Storage;
class IngredientController extends Controller
{
    public function index()
    {
        $user = Auth::user();
    
        if (is_null($user->created_by)) {
            $visibleUserIds = User::where('created_by', $user->id)
                                  ->pluck('id')->push($user->id)->unique();
        } else {
            $visibleUserIds = collect([$user->id, $user->created_by])->unique();
        }
    
        $ingredients = Ingredient::with('recipe')
                                 ->whereIn('user_id', $visibleUserIds)
                                 ->get();
    
        // Passed to JS for client-side matching inside the modal
        $ingredientsForJs = $ingredients->map(fn($i) => [
            'id'               => $i->id,
            'name'             => $i->ingredient_name,
            'additional_names' => $i->additional_names ?? [],
        ])->values();
    
        return view('frontend.ingredients.index', compact('ingredients', 'ingredientsForJs'));
    }

    public function create()
    {
        return view('frontend.ingredients.create');
    }

    public function store(Request $request)
    {
        $user = Auth::user();
    
        $data = $request->validate([
            'ingredient_name' => [
                'required','string','max:255',
                Rule::unique('ingredients')->where(fn($q) =>
                    $q->where('user_id', $user->id)
                      ->where('ingredient_name', $request->ingredient_name)
                ),
            ],
            'price_per_kg' => 'required|numeric|min:0',
        ]);
    
        $data['user_id']          = $user->id;
        $data['additional_names'] = $this->parseAliasesRaw($request->input('additional_names_raw', ''));
    
        $ingredient = Ingredient::create($data);
    
        if ($request->expectsJson()) {
            return response()->json($ingredient, 201);
        }
    
        return back()->with('success', 'Ingrediente salvato con successo.');
    }

    public function show(Ingredient $ingredient)
    {
        abort_unless($ingredient->user_id === Auth::id(), 403);
        return view('frontend.ingredients.show', compact('ingredient'));
    }

    public function edit(Ingredient $ingredient)
    {
        abort_unless($ingredient->user_id === Auth::id(), 403);
        return view('frontend.ingredients.create', compact('ingredient'));
    }

      // ... keep your other actions (index/create/show/edit/store/destroy) ...

      public function update(Request $request, Ingredient $ingredient)
      {
          $user = Auth::user();
          abort_unless($ingredient->user_id === $user->id, 403);
      
          $data = $request->validate([
              'ingredient_name' => [
                  'required','string','max:255',
                  Rule::unique('ingredients')
                      ->where(fn($q) => $q->where('user_id', $user->id))
                      ->ignore($ingredient->id),
              ],
              'price_per_kg' => 'required|numeric|min:0',
          ]);
      
          $data['additional_names'] = $this->parseAliasesRaw($request->input('additional_names_raw', ''));
      
          DB::transaction(function () use ($ingredient, $data, $user) {
              $ingredient->update($data);
      
              if ($ingredient->recipe_id) {
                  Recipe::where('id', $ingredient->recipe_id)
                        ->update(['production_cost_per_kg' => $data['price_per_kg']]);
              }
      
              $visited = [];
              $this->cascadeFromIngredient($ingredient, $visited);
          });
      
          return redirect()->route('ingredients.index')
                           ->with('success', 'Ingrediente aggiornato e costi ricalcolati!');
      }
    /**
     * Recalculate all recipes that use $ingredient; if any recipe has a linked ingredient row,
     * update that ingredient's price to the recipe's unit_ing_cost, and recurse upward.
     */
    private function cascadeFromIngredient(Ingredient $ingredient, array &$visited): void
    {
        if (isset($visited[$ingredient->id])) {
            return;
        }
        $visited[$ingredient->id] = true;

        // find recipes that include this ingredient
        $recipes = Recipe::whereHas('ingredients', function ($q) use ($ingredient) {
                $q->where('ingredient_id', $ingredient->id);
            })
            ->with(['ingredients.ingredient']) // need all ingredient prices to recalc
            ->get();

        foreach ($recipes as $recipe) {
            // Recalc unit_ing_cost (same logic as in your recipe list)
            $this->recalcAndPersistRecipeUnitIngCost($recipe);

            // If this recipe is also saved as an ingredient, update that ingredient’s price
            // to MATCH the recipe’s unit_ing_cost (your “Costo ingr.” shown in list)
            $linkedIng = Ingredient::where('recipe_id', $recipe->id)
                                   ->where('user_id', $recipe->user_id)
                                   ->first();

            if ($linkedIng) {
                // Important: set ingredient's price to the recipe's unit-ing-cost
                $linkedIng->update(['price_per_kg' => $recipe->unit_ing_cost]);

                // Recurse: recipes that use this newly-updated ingredient must be recalculated too
                $this->cascadeFromIngredient($linkedIng, $visited);
            }
        }
    }

    /**
     * Compute batch ingredient cost and unit_ing_cost exactly like recipe index and persist.
     * - batchIngCost = sum(quantity_g/1000 * ingredient.price_per_kg)
     * - unitIngCost depends on sell_mode:
     *     piece: batchIngCost / total_pieces (>=1)
     *     kg:    batchIngCost / (recipe_weight>0 ? recipe_weight/1000 : sumQty/1000)
     */
    private function recalcAndPersistRecipeUnitIngCost(Recipe $recipe): void
    {
        $recipe->loadMissing('ingredients.ingredient');

        $batchIngCost = 0.0;
        $sumWeightG   = 0.0;

        foreach ($recipe->ingredients as $line) {
            $qtyG    = (float) $line->quantity_g;
            $priceKg = (float) ($line->ingredient->price_per_kg ?? 0);
            $sumWeightG   += $qtyG;
            $batchIngCost += ($qtyG / 1000.0) * $priceKg;
        }
        $batchIngCost = round($batchIngCost, 2);

        if ($recipe->sell_mode === 'piece') {
            $pcs = $recipe->total_pieces > 0 ? $recipe->total_pieces : 1;
            $unitIngCost = round($batchIngCost / $pcs, 2);
        } else {
            $wLossG = (float) ($recipe->recipe_weight ?? 0);
            if ($wLossG <= 0) { $wLossG = $sumWeightG; }
            $kg = $wLossG > 0 ? ($wLossG / 1000.0) : 1;
            $unitIngCost = round($batchIngCost / $kg, 2);
        }

        // Persist both so list & ingredient sync are reliable
        $recipe->update([
            'unit_ing_cost'   => $unitIngCost,
            // keep this if you use it elsewhere:
            // 'batch_ing_cost'   => $batchIngCost,  // only if you have this column
        ]);
    }

    public function destroy(Ingredient $ingredient)
    {
        abort_unless($ingredient->user_id === Auth::id(), 403);

        // se era legato ad una recipe pivot, stacco
        $ingredient->recipe()->dissociate(); // sets recipe_id = null
        $ingredient->delete();

        return redirect()
            ->route('ingredients.index')
            ->with('success', 'Ingrediente eliminato con successo!');
    }




// ─── Extract invoice via Google Vision + Claude AI ─────────────────────────

public function extractInvoice(Request $request)
{
    $request->validate([
        'invoice' => 'required|file|mimes:jpg,jpeg,png,webp,pdf|max:20480',
    ]);

    $file     = $request->file('invoice');
    $mimeType = $file->getMimeType();
    $path     = $file->store('temp/invoices', 'local');
    $fullPath = Storage::disk('local')->path($path);

    try {
        $rawText  = app(GoogleVisionService::class)->extractText($fullPath, $mimeType);

        if (empty(trim($rawText))) {
            return response()->json([
                'error' => 'Nessun testo rilevato. Assicurati che il documento sia leggibile e non ruotato.',
            ], 422);
        }

        $extracted           = app(InvoiceParserService::class)->parse($rawText);
        $extracted['raw_text'] = $rawText;

        return response()->json($extracted);

    } catch (\Throwable $e) {
        \Log::error('Invoice extraction error', ['msg' => $e->getMessage()]);
        return response()->json(['error' => $e->getMessage()], 500);
    } finally {
        Storage::disk('local')->delete($path);
    }
}

// ─── Process confirmed (user-verified) invoice data ───────────────────────

public function processInvoice(Request $request)
{
    $data = $request->validate([
        'supplier_name'          => 'nullable|string|max:255',
        'invoice_code'           => 'nullable|string|max:100',
        'date'                   => 'nullable|date',
        'items'                  => 'required|array|min:1',
        'items.*.name'           => 'required|string|max:255',
        'items.*.price_per_kg'   => 'required|numeric|min:0.0001',
        'items.*.line_total'     => 'nullable|numeric|min:0',
    ]);

    $user        = Auth::user();
    $created     = 0;
    $updated     = 0;
    $details     = [];
    $grandTotal  = 0;
    $costEntry   = null;

    DB::transaction(function () use ($data, $user, &$created, &$updated, &$details, &$grandTotal, &$costEntry) {

        foreach ($data['items'] as $item) {
            $name      = trim($item['name']);
            $priceKg   = round((float)$item['price_per_kg'], 4);
            $lineTotal = (float)($item['line_total'] ?? 0);
            $grandTotal += $lineTotal;

            $existing = $this->findMatchingIngredient($name, $user->id);

            if ($existing) {
                $existing->update([
                    'price_per_kg'      => $priceKg,
                    'last_invoice_date' => $data['date'] ?? null,
                    'last_invoice_code' => $data['invoice_code'] ?? null,
                ]);
                $updated++;
                $details[] = ['name'=>$name,'action'=>'updated','matched_to'=>$existing->ingredient_name,'price'=>$priceKg];
                $visited = [];
                $this->cascadeFromIngredient($existing, $visited);
            } else {
                Ingredient::create([
                    'ingredient_name'   => $name,
                    'price_per_kg'      => $priceKg,
                    'user_id'           => $user->id,
                    'additional_names'  => [],
                    'last_invoice_date' => $data['date'] ?? null,
                    'last_invoice_code' => $data['invoice_code'] ?? null,
                ]);
                $created++;
                $details[] = ['name'=>$name,'action'=>'created','price'=>$priceKg];
            }
        }

        // ── Auto-create / update Cost entry ──────────────────────────────
        if ($grandTotal > 0) {
            // Find or create the "Ingredienti" cost category for this user
            $category = \App\Models\CostCategory::firstOrCreate(
                ['name' => 'Ingredienti', 'user_id' => $user->id],
                ['name' => 'Ingredienti', 'user_id' => $user->id]
            );

            $invoiceDate = !empty($data['date'])
                ? \Carbon\Carbon::parse($data['date'])
                : now();

            $supplier = !empty($data['supplier_name'])
                ? $data['supplier_name']
                : 'Fornitore Ingredienti';

            \App\Models\Cost::create([
                'supplier'        => $supplier,
                'cost_identifier' => $data['invoice_code'] ?? null,
                'amount'          => round($grandTotal, 2),
                'due_date'        => $invoiceDate,
                'category_id'     => $category->id,
                'user_id'         => $user->id,
            ]);

            $costEntry = [
                'amount'   => round($grandTotal, 2),
                'category' => $category->name,
                'supplier' => $supplier,
            ];
        }
    });

    return response()->json([
        'success'    => true,
        'message'    => "{$created} ingredient".($created!==1?'i creati':'e creato').", {$updated} aggiornati.",
        'summary'    => compact('created','updated'),
        'details'    => $details,
        'cost_entry' => $costEntry,
    ]);
}

// ─── Update aliases for an ingredient ─────────────────────────────────────

public function updateAliases(Request $request, Ingredient $ingredient)
{
    abort_unless($ingredient->user_id === Auth::id(), 403);

    $request->validate(['additional_names' => 'nullable|string|max:2000']);

    $aliases = $this->parseAliasesRaw($request->input('additional_names', ''));
    $ingredient->update(['additional_names' => $aliases]);

    return response()->json(['success' => true, 'additional_names' => $aliases]);
}

// ─── Helpers ───────────────────────────────────────────────────────────────

private function findMatchingIngredient(string $extractedName, int $userId): ?Ingredient
{
    $needle = $this->normalizeIngName($extractedName);

    return Ingredient::where('user_id', $userId)->get()->first(function ($ing) use ($needle) {
        if ($this->normalizeIngName($ing->ingredient_name) === $needle) {
            return true;
        }
        foreach (($ing->additional_names ?? []) as $alias) {
            if ($this->normalizeIngName($alias) === $needle) {
                return true;
            }
        }
        return false;
    });
}

private function normalizeIngName(string $name): string
{
    $name = mb_strtolower(trim($name));
    $name = preg_replace('/\s+/', ' ', $name);
    $name = preg_replace('/[^\p{L}0-9\s]/u', '', $name); // keep letters (any lang) & digits
    return trim($name);
}

private function parseAliasesRaw(string $raw): array
{
    return collect(explode(',', $raw))
        ->map(fn($s) => trim($s))
        ->filter(fn($s) => $s !== '')
        ->values()
        ->toArray();
}




} 
