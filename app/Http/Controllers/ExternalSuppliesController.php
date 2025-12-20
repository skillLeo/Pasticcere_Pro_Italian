<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Client;
use App\Models\Recipe;
use App\Models\LaborCost;
use App\Models\ReturnedGood;
use App\Models\ExternalSupply;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ExternalSuppliesController extends Controller
{
    /* ---------------------------- LISTA (INDEX) ---------------------------- */
    public function index()
    {
        $user = Auth::user();

        // grupo de visibilidad de dos niveles (root + hijos, o hijo + root)
        if (is_null($user->created_by)) {
            $visibleUserIds = User::where('created_by', $user->id)
                                  ->pluck('id')
                                  ->push($user->id)
                                  ->unique();
        } else {
            $visibleUserIds = collect([$user->id, $user->created_by])->unique();
        }

        // 🟢 mostrar SOLO registros reales (ocultar MODELOs)
        $supplies = ExternalSupply::with(['client','recipes.recipe','user'])
            ->whereIn('user_id', $visibleUserIds)
            ->where('save_template', false)
            ->latest()
            ->get();

        $returns  = ReturnedGood::with(['client','recipes.supplyLine.recipe','user'])
            ->whereIn('user_id', $visibleUserIds)
            ->get();

        $all = collect();

        foreach ($supplies as $supply) {
            $all->push([
                'type'               => 'supply',
                'client'             => $supply->client->name,
                'date'               => $supply->supply_date->toDateString(),
                'external_supply_id' => $supply->id,
                'lines'              => $supply->recipes,
                'revenue'            => $supply->total_amount,
                'created_by'         => $supply->user->name ?? '—',
            ]);
        }

        foreach ($returns as $return) {
            $returnedLines = $return->recipes->map(fn($r) => (object)[
                'recipe'       => $r->supplyLine->recipe ?? null,
                'qty'          => $r->qty,
                'total_amount' => $r->total_amount,
            ]);

            $all->push([
                'type'               => 'return',
                'client'             => $return->client->name,
                'date'               => $return->return_date->toDateString(),
                'external_supply_id' => $return->external_supply_id,
                'lines'              => $returnedLines,
                'revenue'            => -1 * $return->total_amount,
                'created_by'         => $return->user->name ?? '—',
            ]);
        }

        $grouped = $all
            ->groupBy('client')
            ->map(function ($byClient) {
                return $byClient
                    ->sortByDesc('date')
                    ->groupBy('date');
            });

        return view('frontend.external-supplies.index', ['all' => $grouped]);
    }

    /* ----------------------------- SHOW ---------------------------------- */
    public function show(ExternalSupply $externalSupply)
    {
        $externalSupply->load(['client', 'recipes.recipe', 'user']);
        return view('frontend.external-supplies.show', compact('externalSupply'));
    }

    /* ---------------------------- CREATE --------------------------------- */
    public function create()
    {
        $user = Auth::user();

        if (is_null($user->created_by)) {
            $groupUserIds = User::where('created_by', $user->id)
                                ->pluck('id')
                                ->push($user->id)
                                ->unique();
        } else {
            $groupUserIds = collect([$user->id, $user->created_by])->unique();
        }

        $groupRootId = $user->created_by ?? $user->id;
        $laborCost   = LaborCost::where('user_id', $groupRootId)->first();

        // clientes en el grupo (o globales)
        $clients = Client::with('user')
            ->where(function($q) use ($groupUserIds) {
                $q->whereIn('user_id', $groupUserIds)
                  ->orWhereNull('user_id');
            })
            ->latest()
            ->get();

        // recetas con modo de coste laboral externo dentro del grupo
        $recipes   = Recipe::where('labor_cost_mode', 'external')
                        ->whereIn('user_id', $groupUserIds)
                        ->get();

        $templates = ExternalSupply::where('save_template', true)
                        ->whereIn('user_id', $groupUserIds)
                        ->pluck('supply_name', 'id');

        return view('frontend.external-supplies.create', compact(
            'laborCost', 'clients', 'recipes', 'templates'
        ));
    }

    /* ----------------------------- STORE --------------------------------- */
    public function store(Request $request)
    {
        $messages = [
            'supply_name.required_if'          => 'El nombre del suministro es obligatorio cuando se guarda como MODELO.',
            'client_id.required'               => 'Debes seleccionar un cliente.',
            'client_id.exists'                 => 'El cliente seleccionado no existe.',
            'supply_date.required'             => 'La fecha del suministro es obligatoria.',
            'supply_date.date'                 => 'Introduce una fecha válida.',
            'recipes.required'                 => 'Debes añadir al menos una receta.',
            'recipes.*.id.required'            => 'Selecciona una receta para cada fila.',
            'recipes.*.id.exists'              => 'La receta seleccionada no existe.',
            'recipes.*.price.required'         => 'El precio es obligatorio y debe ser un número.',
            'recipes.*.qty.required'           => 'La cantidad es obligatoria y debe ser un número entero.',
            'recipes.*.total_amount.required'  => 'El importe total es obligatorio y debe ser un número.',
        ];

        $data = $request->validate([
            'supply_name'            => 'required_if:template_action,template,both|max:255',
            'template_action'        => 'nullable|in:none,template,both',
            'client_id'              => 'required|exists:clients,id',
            'supply_date'            => 'required|date',
            'recipes'                => 'required|array|min:1',
            'recipes.*.id'           => 'required|exists:recipes,id',
            'recipes.*.price'        => 'required|numeric|min:0',
            'recipes.*.qty'          => 'required|integer|min:0',
            'recipes.*.total_amount' => 'required|numeric|min:0',
        ], $messages);

        $userId      = Auth::id();
        $saveAsTpl   = in_array(($data['template_action'] ?? 'none'), ['template', 'both']);
        $totalAmount = array_sum(array_column($data['recipes'], 'total_amount'));

        return DB::transaction(function () use ($data, $userId, $totalAmount, $saveAsTpl) {

            // 🟢 1) SIEMPRE crear el REGISTRO (save_template = false)
            $record = ExternalSupply::create([
                'client_id'     => $data['client_id'],
                'supply_name'   => $data['supply_name'] ?? null,
                'supply_date'   => $data['supply_date'],
                'total_amount'  => $totalAmount,
                'save_template' => false,
                'user_id'       => $userId,
            ]);

            foreach ($data['recipes'] as $row) {
                $record->recipes()->create([
                    'recipe_id'    => $row['id'],
                    'category'     => $row['category'] ?? '',
                    'price'        => $row['price'],
                    'qty'          => $row['qty'],
                    'total_amount' => $row['total_amount'],
                    'user_id'      => $userId,
                ]);
            }

            // 🟡 2) Opcionalmente crear una MODELO (fila separada, save_template = true)
            if ($saveAsTpl) {
                $tpl = ExternalSupply::create([
                    'client_id'     => $data['client_id'],
                    'supply_name'   => $data['supply_name'],
                    'supply_date'   => $data['supply_date'],
                    'total_amount'  => $totalAmount, // mantener totales precalculados
                    'save_template' => true,
                    'user_id'       => $userId,
                ]);

                foreach ($data['recipes'] as $row) {
                    // mantener qty/precio en la MODELO; totales precalculados como precio*qty
                    $tpl->recipes()->create([
                        'recipe_id'    => $row['id'],
                        'category'     => $row['category'] ?? '',
                        'price'        => $row['price'],
                        'qty'          => $row['qty'],
                        'total_amount' => $row['total_amount'],
                        'user_id'      => $userId,
                    ]);
                }
            }

            return redirect()
                ->route('external-supplies.create')
                ->with('success', $saveAsTpl
                    ? 'Suministro guardado + MODELO actualizada.'
                    : '¡Suministro externo guardado correctamente!');
        });
    }

    /* --------------------------- LOAD TEMPLATE ---------------------------- */
    public function getTemplate($id)
    {
        $user = Auth::user();

        // permitir cargar cualquier MODELO propiedad del usuario o de su grupo
        if (is_null($user->created_by)) {
            $groupUserIds = User::where('created_by', $user->id)
                                ->pluck('id')
                                ->push($user->id)
                                ->unique();
        } else {
            $groupUserIds = collect([$user->id, $user->created_by])->unique();
        }

        $supply = ExternalSupply::with('recipes')
            ->where('id', $id)
            ->where('save_template', true)
            ->whereIn('user_id', $groupUserIds)
            ->firstOrFail();

        $rows = $supply->recipes->map(fn($r) => [
            'recipe_id'    => $r->recipe_id,
            'price'        => $r->price,
            'qty'          => $r->qty,
            'total_amount' => $r->total_amount,
        ]);

        return response()->json([
            'client_id'       => $supply->client_id,
            'supply_name'     => $supply->supply_name,
            'supply_date'     => $supply->supply_date->format('Y-m-d'),
            'template_action' => 'template',
            'rows'            => $rows,
        ]);
    }

    /* ----------------------------- EDIT ---------------------------------- */
    public function edit(ExternalSupply $externalSupply)
    {
        $externalSupply->load('recipes.recipe');

        $user = Auth::user();

        if (is_null($user->created_by)) {
            $visibleUserIds = User::where('created_by', $user->id)
                                  ->pluck('id')
                                  ->push($user->id)
                                  ->unique();
        } else {
            $visibleUserIds = collect([$user->id, $user->created_by])->unique();
        }

        $clients = Client::with('user')
            ->where(function($q) use ($visibleUserIds) {
                $q->whereIn('user_id', $visibleUserIds)
                  ->orWhereNull('user_id');
            })
            ->latest()
            ->get();

        $recipes   = Recipe::where('labor_cost_mode', 'external')
                        ->whereIn('user_id', $visibleUserIds)
                        ->get();

        $templates = ExternalSupply::where('save_template', true)
                        ->whereIn('user_id', $visibleUserIds)
                        ->pluck('supply_name', 'id');

        return view('frontend.external-supplies.create', compact(
            'externalSupply', 'clients', 'recipes', 'templates'
        ));
    }

    /* ---------------------------- UPDATE --------------------------------- */
    public function update(Request $request, ExternalSupply $externalSupply)
    {
        $messages = [
            'supply_name.required_if'          => 'El nombre del suministro es obligatorio cuando se guarda como MODELO.',
            'client_id.required'               => 'Debes seleccionar un cliente.',
            'client_id.exists'                 => 'El cliente seleccionado no existe.',
            'supply_date.required'             => 'La fecha del suministro es obligatoria.',
            'supply_date.date'                 => 'Introduce una fecha válida.',
            'recipes.required'                 => 'Debes añadir al menos una receta.',
            'recipes.*.id.required'            => 'Selecciona una receta para cada fila.',
            'recipes.*.id.exists'              => 'La receta seleccionada no existe.',
            'recipes.*.price.required'         => 'El precio es obligatorio y debe ser un número.',
            'recipes.*.qty.required'           => 'La cantidad es obligatoria y debe ser un número entero.',
            'recipes.*.total_amount.required'  => 'El importe total es obligatorio y debe ser un número.',
        ];

        $data = $request->validate([
            'supply_name'            => 'required_if:template_action,template,both|max:255',
            'template_action'        => 'nullable|in:none,template,both',
            'client_id'              => 'required|exists:clients,id',
            'supply_date'            => 'required|date',
            'recipes'                => 'required|array|min:1',
            'recipes.*.id'           => 'required|exists:recipes,id',
            'recipes.*.price'        => 'required|numeric|min:0',
            'recipes.*.qty'          => 'required|integer|min:0',
            'recipes.*.total_amount' => 'required|numeric|min:0',
        ], $messages);

        $userId      = Auth::id();
        $saveAsTpl   = in_array(($data['template_action'] ?? 'none'), ['template', 'both']);
        $totalAmount  = array_sum(array_column($data['recipes'], 'total_amount'));

        return DB::transaction(function () use ($externalSupply, $data, $saveAsTpl, $userId, $totalAmount) {
            // 🟢 1) Actualizar el REGISTRO (NO cambiar save_template)
            $externalSupply->update([
                'client_id'     => $data['client_id'],
                'supply_name'   => $data['supply_name'] ?? $externalSupply->supply_name,
                'supply_date'   => $data['supply_date'],
                'total_amount'  => $totalAmount,
                'save_template' => $externalSupply->save_template, // mantener tal cual (registros siguen en false)
                'user_id'       => $userId,
            ]);

            $externalSupply->recipes()->delete();
            foreach ($data['recipes'] as $row) {
                $externalSupply->recipes()->create([
                    'recipe_id'    => $row['id'],
                    'category'     => $row['category'] ?? '',
                    'price'        => $row['price'],
                    'qty'          => $row['qty'],
                    'total_amount' => $row['total_amount'],
                    'user_id'      => $userId,
                ]);
            }

            // 🟡 2) Opcionalmente HACER UPSERT de una MODELO (por nombre para este usuario)
            if ($saveAsTpl) {
                $template = ExternalSupply::where('user_id', $userId)
                    ->where('save_template', true)
                    ->where('supply_name', $data['supply_name'])
                    ->first();

                if (!$template) {
                    $template = ExternalSupply::create([
                        'client_id'     => $data['client_id'],
                        'supply_name'   => $data['supply_name'],
                        'supply_date'   => $data['supply_date'],
                        'total_amount'  => $totalAmount,
                        'save_template' => true,
                        'user_id'       => $userId,
                    ]);
                } else {
                    $template->update([
                        'client_id'     => $data['client_id'],
                        'supply_date'   => $data['supply_date'],
                        'total_amount'  => $totalAmount,
                        'save_template' => true,
                        'user_id'       => $userId,
                    ]);
                    $template->recipes()->delete();
                }

                foreach ($data['recipes'] as $row) {
                    $template->recipes()->create([
                        'recipe_id'    => $row['id'],
                        'category'     => $row['category'] ?? '',
                        'price'        => $row['price'],
                        'qty'          => $row['qty'],
                        'total_amount' => $row['total_amount'],
                        'user_id'      => $userId,
                    ]);
                }
            }

            return redirect()
                ->route('external-supplies.index')
                ->with('success', $saveAsTpl
                    ? 'Suministro actualizado y MODELO sincronizada.'
                    : '¡Suministro externo actualizado correctamente!');
        });
    }

    /* ---------------------------- DESTROY -------------------------------- */
    public function destroy(ExternalSupply $externalSupply)
    {
        $externalSupply->recipes()->delete();
        $externalSupply->delete();

        return redirect()
            ->route('external-supplies.index')
            ->with('success', '¡Suministro externo eliminado correctamente!');
    }
}
