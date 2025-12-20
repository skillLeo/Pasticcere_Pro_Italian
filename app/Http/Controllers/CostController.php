<?php

namespace App\Http\Controllers;

use App\Models\Cost;
use App\Models\User;
use App\Models\Income;
use App\Models\OpeningDay;
use App\Models\CostCategory;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CostController extends Controller
{
    /**
     * Muestra el formulario para crear un nuevo costo.
     */
    public function create()
    {
        $user = Auth::user();

        // Usuarios visibles: yo + mis hijos (si soy raíz) O yo + mi creador (si soy hijo)
        if (is_null($user->created_by)) {
            $children = User::where('created_by', $user->id)->pluck('id');
            $visibleUserIds = $children->isEmpty()
                ? collect([$user->id])
                : $children->push($user->id);
        } else {
            $visibleUserIds = collect([$user->id, $user->created_by]);
        }

        // Categorías pertenecientes a los usuarios visibles O globales (user_id NULL)
        $categories = CostCategory::with('user')
            ->where(function ($q) use ($visibleUserIds) {
                $q->whereIn('user_id', $visibleUserIds)
                  ->orWhereNull('user_id');
            })
            ->orderBy('name')
            ->get();

        return view('frontend.costs.create', compact('categories'));
    }

    /**
     * Muestra un único costo.
     */
    public function show(Cost $cost)
    {
        // Solo el dueño puede ver (ajusta si quieres visibilidad padre/hijo para show)
        if ($cost->user_id !== Auth::id()) {
            abort(Response::HTTP_FORBIDDEN);
        }

        return view('frontend.costs.show', compact('cost'));
    }

    /**
     * Guarda un nuevo costo.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'supplier'        => ['required','string','max:255'],
            'cost_identifier' => ['nullable','string','max:255'],
            'amount'          => ['required','numeric','min:0'],
            'due_date'        => ['required','date'],
            'category_id'     => ['required','exists:cost_categories,id'],
            'other_category'  => ['nullable','string','max:255'],
        ]);

        // Asegurar que due_date sea una instancia de Carbon
        $data['due_date'] = $request->date('due_date');
        $data['user_id']  = Auth::id();

        Cost::create($data);

        return redirect()
            ->route('costs.index')
            ->with('success', 'Costo agregado!');
    }

    /**
     * Muestra un listado de costos.
     */
    public function index()
    {
        $user = Auth::user();

        if (is_null($user->created_by)) {
            $children = User::where('created_by', $user->id)->pluck('id');
            $visibleUserIds = $children->isEmpty()
                ? collect([$user->id])
                : $children->push($user->id);
        } else {
            $visibleUserIds = collect([$user->id, $user->created_by]);
        }

        $categories = CostCategory::with('user')
            ->where(function ($q) use ($visibleUserIds) {
                $q->whereIn('user_id', $visibleUserIds)
                  ->orWhereNull('user_id');
            })
            ->orderBy('name')
            ->get();

        $costs = Cost::with(['category','user'])
            ->whereIn('user_id', $visibleUserIds)
            ->orderBy('due_date', 'desc')
            ->get();

        return view('frontend.costs.index', compact('categories','costs'));
    }

    /**
     * Muestra el formulario para editar el costo especificado.
     */
    public function edit(Cost $cost)
    {
        if ($cost->user_id !== Auth::id()) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $user = Auth::user();

        if (is_null($user->created_by)) {
            $visibleUserIds = User::where('created_by', $user->id)
                                  ->pluck('id')
                                  ->push($user->id)
                                  ->unique();
        } else {
            $visibleUserIds = collect([$user->id, $user->created_by])->unique();
        }

        $categories = CostCategory::with('user')
            ->where(function ($q) use ($visibleUserIds) {
                $q->whereIn('user_id', $visibleUserIds)
                  ->orWhereNull('user_id');
            })
            ->orderBy('name')
            ->get();

        // Reutilizar la vista de create para editar
        return view('frontend.costs.create', compact('cost', 'categories'));
    }

    /**
     * Actualiza el costo especificado en el almacenamiento.
     */
    public function update(Request $request, Cost $cost)
    {
        if ($cost->user_id !== Auth::id()) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $data = $request->validate([
            'supplier'        => ['required','string','max:255'],
            'cost_identifier' => ['nullable','string','max:255'],
            'amount'          => ['required','numeric','min:0'],
            'due_date'        => ['required','date'],
            'category_id'     => ['required','exists:cost_categories,id'],
            'other_category'  => ['nullable','string','max:255'],
        ]);

        // Asegurar que due_date sea una instancia de Carbon
        $data['due_date'] = $request->date('due_date');

        $cost->update($data);

        return redirect()
            ->route('costs.index')
            ->with('success', 'Costo actualizado con éxito!');
    }

    /**
     * Elimina el costo especificado del almacenamiento.
     */
    public function destroy(Cost $cost)
    {
        if ($cost->user_id !== Auth::id()) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $cost->delete();

        return redirect()
            ->route('costs.index')
            ->with('success', 'Costo eliminado con éxito!');
    }

    public function dashboard(Request $request)
    {
        $user = Auth::user();

        // Determinar IDs de usuarios visibles (propietario + hijos, o usuario + propietario)
        if (is_null($user->created_by)) {
            $children = User::where('created_by', $user->id)->pluck('id');
            $visibleUserIds = $children->isEmpty()
                ? collect([$user->id])
                : $children->push($user->id);
        } else {
            $visibleUserIds = collect([$user->id, $user->created_by]);
        }

        $year     = (int) $request->query('y', now()->year);
        $month    = (int) $request->query('m', now()->month);
        $lastYear = $year - 1;

        // Categorías (tarjetas de resumen)
        $categories = CostCategory::with('user')
            ->where(function ($q) use ($visibleUserIds) {
                $q->whereIn('user_id', $visibleUserIds)
                  ->orWhereNull('user_id');
            })
            ->orderBy('name')
            ->get();

        // Años disponibles para el selector
        $availableYears = Cost::whereIn('user_id', $visibleUserIds)
            ->selectRaw('YEAR(due_date) as year')
            ->distinct()
            ->orderByDesc('year')
            ->pluck('year');

        // Totales por categoría para el mes seleccionado
        $raw = Cost::whereIn('user_id', $visibleUserIds)
            ->whereYear('due_date', $year)
            ->whereMonth('due_date', $month)
            ->selectRaw('category_id, SUM(amount) as total')
            ->groupBy('category_id')
            ->pluck('total','category_id');

        // Costes por mes (este y el año pasado)
        $costsThisYear = Cost::whereIn('user_id', $visibleUserIds)
            ->whereYear('due_date', $year)
            ->selectRaw('MONTH(due_date) as month, SUM(amount) as total')
            ->groupBy('month')
            ->pluck('total','month');

        $costsLastYear = Cost::whereIn('user_id', $visibleUserIds)
            ->whereYear('due_date', $lastYear)
            ->selectRaw('MONTH(due_date) as month, SUM(amount) as total')
            ->groupBy('month')
            ->pluck('total','month');

        $totalCostYear     = $costsThisYear->sum();
        $totalCostLastYear = $costsLastYear->sum();

        // Ingresos + Neto por mes
        $incomeThisYearMonthly = [];
        $incomeLastYearMonthly = [];
        $netByMonth            = [];

        for ($m = 1; $m <= 12; $m++) {
            $i1 = Income::whereIn('user_id', $visibleUserIds)
                ->whereYear('date', $year)->whereMonth('date', $m)
                ->sum('amount');

            $i2 = Income::whereIn('user_id', $visibleUserIds)
                ->whereYear('date', $lastYear)->whereMonth('date', $m)
                ->sum('amount');

            $incomeThisYearMonthly[$m] = (float) $i1;
            $incomeLastYearMonthly[$m] = (float) $i2;
            $netByMonth[$m]            = (float) $i1 - (float) ($costsThisYear[$m] ?? 0);
        }

        $totalIncomeYear     = array_sum($incomeThisYearMonthly);
        $totalIncomeLastYear = array_sum($incomeLastYearMonthly);
        $netYear             = $totalIncomeYear - $totalCostYear;
        $netLastYear         = $totalIncomeLastYear - $totalCostLastYear;

        $bestNet    = max($netByMonth);
        $worstNet   = min($netByMonth);
        $bestMonth  = array_search($bestNet, $netByMonth, true);
        $worstMonth = array_search($worstNet, $netByMonth, true);

        if (count(array_unique($netByMonth)) === 1) {
            $worstMonth = null;
            $worstNet   = $bestNet;
        }

        $incomeThisMonth    = $incomeThisYearMonthly[$month] ?? 0;
        $incomeLastYearSame = $incomeLastYearMonthly[$month] ?? 0;

        // ===== Días de apertura (editables) + BEP (€/día) =====
        // Días de apertura por usuario (editar y guardar)
        $openingDaysThisYear = OpeningDay::where('user_id', $user->id)
            ->where('year', $year)
            ->pluck('days', 'month');

        $openingDaysLastYear = OpeningDay::where('user_id', $user->id)
            ->where('year', $lastYear)
            ->pluck('days', 'month');

        // Precalcular BEP por mes para render inicial
        $bepThisYear = [];
        $bepLastYear = [];
        for ($m = 1; $m <= 12; $m++) {
            $d1 = (int) ($openingDaysThisYear[$m] ?? 0);
            $d2 = (int) ($openingDaysLastYear[$m] ?? 0);
            $c1 = (float) ($costsThisYear[$m] ?? 0);
            $c2 = (float) ($costsLastYear[$m] ?? 0);
            $bepThisYear[$m] = $d1 > 0 ? $c1 / $d1 : 0.0;
            $bepLastYear[$m] = $d2 > 0 ? $c2 / $d2 : 0.0;
        }

        // Fila de totales: suma de días + BEP global (Costo total / Días de apertura totales)
        $sumDaysThisYear     = array_sum($openingDaysThisYear->toArray());
        $sumDaysLastYear     = array_sum($openingDaysLastYear->toArray());
        $overallBepThisYear  = $sumDaysThisYear > 0 ? ($totalCostYear / $sumDaysThisYear) : 0.0;
        $overallBepLastYear  = $sumDaysLastYear > 0 ? ($totalCostLastYear / $sumDaysLastYear) : 0.0;

        return view('frontend.costs.dashboard', compact(
            'availableYears','year','month','lastYear','categories',
            'raw','incomeThisMonth','incomeLastYearSame',
            'costsThisYear','costsLastYear','netByMonth',
            'incomeThisYearMonthly','incomeLastYearMonthly',
            'totalCostYear','totalIncomeYear','netYear',
            'totalCostLastYear','totalIncomeLastYear','netLastYear',
            'bestMonth','bestNet','worstMonth','worstNet',
            // días de apertura + BEP
            'openingDaysThisYear','openingDaysLastYear',
            'bepThisYear','bepLastYear',
            'sumDaysThisYear','sumDaysLastYear',
            'overallBepThisYear','overallBepLastYear'
        ));
    }

    /**
     * AJAX: guardar un mes de días de apertura.
     */
    public function saveOpeningDays(Request $request)
    {
        $user = Auth::user();

        $data = $request->validate([
            'year'  => ['required','integer','min:2000','max:2100'],
            'month' => ['required','integer','between:1,12'],
            'days'  => ['nullable','integer','between:0,31'],
        ]);

        OpeningDay::updateOrCreate(
            [
                'user_id' => $user->id,
                'year'    => (int) $data['year'],
                'month'   => (int) $data['month'],
            ],
            ['days' => (int) ($data['days'] ?? 0)]
        );

        return response()->json(['ok' => true]);
    }
}
