<?php

    namespace App\Http\Controllers;

    use Carbon\Carbon;
    use App\Models\Cost;
    use App\Models\User;
    use App\Models\Income;
    use App\Models\Recipe;
    use App\Models\Showcase;
    use App\Models\ShowcaseRecipe;
    use App\Models\ProductionDetail;
    use App\Models\ReturnedGoodRecipe;
    use Illuminate\Support\Facades\DB;
    use App\Http\Controllers\Controller;
    use App\Models\ExternalSupplyRecipe;
    use Illuminate\Support\Facades\Auth;
    use ArielMejiaDev\LarapexCharts\LarapexChart;

    class DashboardController extends Controller
    {
        public function index()
        {
            Carbon::setLocale('it');

            $user = Auth::user();

            // 1) Determinar los usuarios visibles
            if (is_null($user->created_by)) {
                $children = User::where('created_by', $user->id)->pluck('id');
                $visibleUserIds = $children->isEmpty()
                    ? collect([$user->id])
                    : $children->push($user->id);
            } else {
                $visibleUserIds = collect([$user->id, $user->created_by]);
            }

            // 2) Métricas principales
            $totalUsers          = User::whereIn('id', $visibleUserIds)->count();
            $totalRecipes        = Recipe::whereIn('user_id', $visibleUserIds)->count();
            $totalShowcases      = Showcase::whereIn('user_id', $visibleUserIds)->count();

            $year                = Carbon::now()->year;
            $totalSaleThisYear   = Showcase::whereIn('user_id', $visibleUserIds)
                ->whereYear('showcase_date', $year)
                ->sum('total_revenue');

            $totalWasteThisYear  = ShowcaseRecipe::whereIn('user_id', $visibleUserIds)
                ->whereHas('showcase', fn($q) => $q->whereYear('showcase_date', $year))
                ->sum('waste');

            $totalProfitThisYear = Showcase::whereIn('user_id', $visibleUserIds)
                ->whereYear('showcase_date', $year)
                ->sum('real_margin');

            // 2) Métricas de ganancias
            $sales      = Showcase::whereIn('user_id', $visibleUserIds)->sum('total_revenue');
            $plus       = Showcase::whereIn('user_id', $visibleUserIds)->sum('plus');
            $realMargin = Showcase::whereIn('user_id', $visibleUserIds)->sum('real_margin');

            // 3) Ventas mensuales (año actual)
            $monthlyData = Showcase::selectRaw("MONTH(showcase_date) AS month, SUM(total_revenue) AS total")
                ->whereIn('user_id', $visibleUserIds)
                ->whereYear('showcase_date', Carbon::now()->year)
                ->groupBy('month')
                ->orderBy('month')
                ->get();

            $labels = $monthlyData->pluck('month')
                ->map(fn($m) =>
                    Carbon::createFromDate(null, $m, 1)
                        ->locale('it')
                        ->isoFormat('MMM')
                )->toArray();
            $values = $monthlyData->pluck('total')->toArray();

            $chart = (new LarapexChart)->barChart()
                ->setTitle('Ventas mensuales')
                ->setSubtitle('Año actual')
                ->addData('Ingresos', $values)
                ->setXAxis($labels);

            // 4) Costes e ingresos (mes actual)
            $start = Carbon::now()->startOfMonth();
            $end   = Carbon::now()->endOfMonth();
            $monthlyCost   = Cost::whereIn('user_id', $visibleUserIds)
                ->whereBetween('due_date', [$start, $end])->sum('amount');
            $monthlyIncome = Income::whereIn('user_id', $visibleUserIds)
                ->whereBetween('date', [$start, $end])->sum('amount');

            $comparisonChart = (new LarapexChart)->barChart()
                ->setTitle('Costes e ingresos')
                ->setSubtitle(Carbon::now()->locale('it')->isoFormat('MMMM YYYY'))
                ->addData('Costes', [$monthlyCost])
                ->addData('Ingresos', [$monthlyIncome])
                ->setXAxis([Carbon::now()->locale('it')->isoFormat('MMMM')]);

            // 5) Comparación de costes anuales
            $thisYear       = Carbon::now()->year;
            $lastYear       = Carbon::now()->subYear()->year;
            $yearlyCostThis = Cost::whereIn('user_id', $visibleUserIds)
                ->whereYear('due_date', $thisYear)->sum('amount');
            $yearlyCostLast = Cost::whereIn('user_id', $visibleUserIds)
                ->whereYear('due_date', $lastYear)->sum('amount');

            $yearlyCostChart = (new LarapexChart)->barChart()
                ->setTitle('Costes anuales')
                ->setSubtitle("$lastYear vs $thisYear")
                ->addData('Costes', [$yearlyCostLast, $yearlyCostThis])
                ->setXAxis([$lastYear, $thisYear]);

            // 6) Comparación de ingresos anuales
            $yearlyIncomeThis = Income::whereIn('user_id', $visibleUserIds)
                ->whereYear('date', $thisYear)->sum('amount');
            $yearlyIncomeLast = Income::whereIn('user_id', $visibleUserIds)
                ->whereYear('date', $lastYear)->sum('amount');

            $yearlyIncomeChart = (new LarapexChart)->barChart()
                ->setTitle('Ingresos anuales')
                ->setSubtitle("$lastYear vs $thisYear")
                ->addData('Ingresos', [$yearlyIncomeLast, $yearlyIncomeThis])
                ->setXAxis([$lastYear, $thisYear]);

            // 7) Top 5 productos vendidos y desperdicios
            $topSold = ShowcaseRecipe::with(['recipe' => function ($query) {
                    $query->select('id', 'recipe_name');
                }])
                ->whereIn('user_id', $visibleUserIds)
                ->selectRaw('recipe_id, SUM(sold) as sold')
                ->groupBy('recipe_id')
                ->orderByDesc('sold')
                ->take(5)
                ->get();

            $topWasted = ShowcaseRecipe::with(['recipe' => function ($query) {
                    $query->select('id', 'recipe_name');
                }])
                ->whereIn('user_id', $visibleUserIds)
                ->selectRaw('recipe_id, SUM(waste) as waste')
                ->groupBy('recipe_id')
                ->orderByDesc('waste')
                ->take(5)
                ->get();

            $soldValues = $topSold->pluck('sold')->map(fn($v) => (int) $v)->toArray();
            $soldLabels = $topSold->map(fn($item) => $item->recipe->recipe_name ?? 'Desconocido')->toArray();

            $soldPieChart = (new LarapexChart)->donutChart()
                ->setTitle('Distribución de ventas')
                ->addData($soldValues)
                ->setLabels($soldLabels);

            $wastedLabels = $topWasted->map(fn($item) => $item->recipe->recipe_name ?? 'Desconocido')->toArray();
            $wastedValues = $topWasted->pluck('waste')->toArray();

            $wastedPieChart = (new LarapexChart)->donutChart()
                ->setTitle('Distribución de desperdicios')
                ->addData($wastedValues)
                ->setLabels($wastedLabels);

            // 8) Incidencia costes vs ingresos
            $totalCost   = $monthlyCost;
            $totalRev    = $monthlyIncome;
            $nettoAmount = $totalRev - $totalCost;

            $incidenceChart = (new LarapexChart)->pieChart()
                ->setTitle('Impacto de costes vs ingresos')
                ->addData([$totalCost, $totalRev, $nettoAmount])
                ->setLabels(['Costes', 'Ingresos', 'Neto']);

            // 9) Producción por pastelero
            $prodByChef = ProductionDetail::selectRaw('pastry_chef_id, SUM(quantity) AS qty')
                ->whereIn('user_id', $visibleUserIds)
                ->groupBy('pastry_chef_id')
                ->with('chef:id,name')
                ->orderByDesc('qty')
                ->get();

            $chefLabels = $prodByChef->pluck('chef.name')->toArray();
            $chefValues = $prodByChef->pluck('qty')->toArray();

            $chefChart = (new LarapexChart)->barChart()
                ->setTitle('Producción por pastelero')
                ->addData('Unidades', $chefValues)
                ->setXAxis($chefLabels);

            // 10) Tendencia de producción vs. desperdicio (anual)
            $prodTrend  = ProductionDetail::selectRaw('MONTH(production_date) AS m, SUM(quantity) AS produced')
                ->join('productions','productions.id','production_details.production_id')
                ->whereIn('production_details.user_id',$visibleUserIds)
                ->whereYear('productions.production_date',$year)
                ->groupBy('m')->orderBy('m')->get();

            $wasteTrend = ShowcaseRecipe::selectRaw('MONTH(showcase_date) AS m, SUM(waste) AS waste')
                ->join('showcases','showcases.id','showcase_recipes.showcase_id')
                ->whereIn('showcase_recipes.user_id',$visibleUserIds)
                ->whereYear('showcases.showcase_date',$year)
                ->groupBy('m')->orderBy('m')->get();

            $labelsTrend = $prodTrend->pluck('m')
                ->map(fn($m) =>
                    Carbon::createFromDate(null, $m, 1)
                        ->locale('it')
                        ->isoFormat('MMM')
                )->toArray();

            $prodVals  = $prodTrend->pluck('produced')->toArray();
            $wasteVals = $wasteTrend->pluck('waste')->toArray();

            $prodWasteChart = (new LarapexChart)->areaChart()
                ->setTitle('producción vs. desperdicio')
                ->addData('Producido', $prodVals)
                ->addData('Desperdicio',   $wasteVals)
                ->setXAxis($labelsTrend);

            // 11) Reparto de los costes por categoría
            $costByCategory = Cost::join('cost_categories', 'costs.category_id', '=', 'cost_categories.id')
                ->whereIn('costs.user_id', $visibleUserIds)
                ->groupBy('cost_categories.id', 'cost_categories.name')
                ->select(
                    'cost_categories.name as category',
                    DB::raw('SUM(costs.amount) as total')
                )
                ->get();

            $categoryLabels = $costByCategory->pluck('category')->toArray();
            $categoryValues = $costByCategory->pluck('total')->map(fn($v) => (float) $v)->toArray();

        // 11) Reparto de los costes por categoría
    $costByCategory = Cost::join('cost_categories', 'costs.category_id', '=', 'cost_categories.id')
        ->whereIn('costs.user_id', $visibleUserIds)
        ->groupBy('cost_categories.id', 'cost_categories.name')
        ->select('cost_categories.name as category', DB::raw('SUM(costs.amount) as total'))
        ->get();

    $categoryLabels = $costByCategory->pluck('category')->toArray();
    $categoryValues = $costByCategory->pluck('total')->map(fn($v) => (float) $v)->toArray();
    $categoryTotal  = array_sum($categoryValues); // opcional, si también quieres mostrarlo como texto


// totales (mantén estos como ya los tienes)
$totalSupplied = ExternalSupplyRecipe::whereIn('user_id', $visibleUserIds)->sum('qty');
$totalReturned = ReturnedGoodRecipe::join('returned_goods','returned_goods.id','=','returned_good_recipes.returned_good_id')
    ->whereIn('returned_goods.user_id', $visibleUserIds)
    ->sum('qty');

$restocked = max(0, (int)$totalSupplied - (int)$totalReturned);

// ✅ Construir el gráfico exactamente como los otros (Larapex donut)
$returnRateChart = (new \ArielMejiaDev\LarapexCharts\LarapexChart)
    ->donutChart()
    ->setTitle('Devoluciones vs reabastecimiento')
    ->addData([(int)$totalReturned, (int)$restocked])
    ->setLabels(['Devoluciones', 'Reabastecidos'])
    ->setHeight(300);

            // $returnRateChart = (new LarapexChart)->pieChart()
            //     ->setTitle('Resi vs. Utilizzo')
            //     ->addData([
            //         $totalReturned,
            //         $totalSupplied - $totalReturned
            //     ])
            //     ->setLabels(['Resi', 'Utilizzati']);

            // 13) Conjuntos de datos completos para filtrado JS
            $fullMonthlyData = $monthlyData->map(fn($row) => [
                'date'  => Carbon::createFromDate(null, $row->month, 1)->format('Y-m-d'),
                'total' => $row->total,
            ])->toArray();

            $fullSoldData = ShowcaseRecipe::with(['recipe','showcase'])
                ->whereIn('user_id', $visibleUserIds)
                ->get()
                ->map(fn($r) => [
                    'recipe_name' => $r->recipe->recipe_name,
                    'sold'        => $r->sold,
                    'date'        => $r->showcase->showcase_date->format('Y-m-d'),
                ])->toArray();

            $fullWastedData = ShowcaseRecipe::with(['recipe','showcase'])
                ->whereIn('user_id', $visibleUserIds)
                ->get()
                ->map(fn($r) => [
                    'recipe_name' => $r->recipe->recipe_name,
                    'waste'       => $r->waste,
                    'date'        => $r->showcase->showcase_date->format('Y-m-d'),
                ])->toArray();

            $fullCostData = Cost::with('category')
                ->whereIn('user_id', $visibleUserIds)
                ->get()
                ->map(fn($c) => [
                    'date'     => $c->due_date->format('Y-m-d'),
                    'amount'   => (float) $c->amount,
                    'category' => $c->category->name,
                ])->toArray();

        // DashboardController.php — sustituir SOLO el bloque $fullIncomeData por este

    $fullIncomeData = Income::leftJoin('income_categories as ic', 'incomes.income_category_id', '=', 'ic.id')
        ->whereIn('incomes.user_id', $visibleUserIds)
        ->select([
            'incomes.date',
            'incomes.amount',
            DB::raw('COALESCE(ic.name, "Sin categoría") as category'),
        ])
        ->get()
        ->map(fn($i) => [
            'date'     => Carbon::parse($i->date)->format('Y-m-d'),
            'amount'   => (float) $i->amount,
            'category' => $i->category, // ya normalizado por COALESCE
        ])->toArray();


            // ---------------------------------------------------------------------
            // [NUEVO / CORREGIDO] Promedios por categoría para el dashboard (excluye negativos)
            // Usa `recipe_categories` (alias rc)
            // ---------------------------------------------------------------------
            $categoryAvgRaw = Recipe::whereIn('recipes.user_id', $visibleUserIds)
                ->leftJoin('recipe_categories as rc', 'recipes.recipe_category_id', '=', 'rc.id')
                ->groupBy('rc.id', 'rc.name')
                ->selectRaw('
                    COALESCE(rc.name, "Sin categoría") AS name,
                    COUNT(*) AS total_cnt,
                    SUM(CASE WHEN recipes.potential_margin_pct >= 0 THEN 1 ELSE 0 END) AS pos_cnt,
                    ROUND(AVG(CASE WHEN recipes.potential_margin_pct >= 0
                                THEN recipes.potential_margin_pct END), 2) AS avg_margin_pos
                ')
                ->get();

            // mantenerlo corto: mostrar las 8 categorías con más ítems positivos (o todas si son menos)
            $categoryAvgTop = $categoryAvgRaw
                ->sortByDesc('pos_cnt')
                ->take(8)
                ->values();

            // Promedio global de márgenes positivos en todas las recetas
            $globalAvgMarginPos = Recipe::whereIn('user_id', $visibleUserIds)
                ->avg(DB::raw('CASE WHEN potential_margin_pct >= 0 THEN potential_margin_pct END'));
            $globalAvgMarginPos = round($globalAvgMarginPos ?? 0, 2);
            // ---------------------------------------------------------------------

            // 14) Renderizado


    // añadir justo después de $user = Auth::user();
    // mostrar contadores extra solo al Super
    $isSuper = $user->hasRole('super'); // usando spatie/permission

    // conteos en todo el sistema (todos los tenants/usuarios)
    $adminsCount   = $isSuper
        ? User::whereHas('roles', fn($q) => $q->where('name','admin')->where('guard_name','web'))->count()
        : 0;

    $allUsersCount = $isSuper ? User::count() : 0;



    // === Filas completas de producción por pastelero para filtrado JS por fecha ===
$fullChefData = ProductionDetail::join('productions','productions.id','=','production_details.production_id')
    ->leftJoin('users as u','u.id','=','production_details.pastry_chef_id')
    ->whereIn('production_details.user_id', $visibleUserIds)
    ->select([
        DB::raw('DATE(productions.production_date) as date'),
        DB::raw('COALESCE(u.name, "Desconocido") as chef_name'),
        'production_details.quantity as qty',
    ])
    ->orderBy('productions.production_date')
    ->get()
    ->map(fn($r) => [
        'date'      => Carbon::parse($r->date)->format('Y-m-d'),
        'chef_name' => $r->chef_name,
        'qty'       => (float) $r->qty,
    ])->toArray();

            
            return view('dashboard', compact(
                'sales','plus','realMargin',
                'chart','comparisonChart','yearlyCostChart','yearlyIncomeChart',
                'topSold','topWasted',
                'soldPieChart','wastedPieChart','incidenceChart',
                'totalUsers','totalRecipes','totalShowcases',
                'totalSaleThisYear','totalWasteThisYear','totalProfitThisYear',
                'year','totalSupplied','totalReturned', 
                'chefChart','prodWasteChart','returnRateChart',
                'fullMonthlyData','fullSoldData','fullWastedData',
                'fullCostData','fullIncomeData','fullChefData',
                // [NUEVO] pasar a la vista
                'categoryAvgTop','globalAvgMarginPos','categoryLabels','categoryValues','categoryTotal','isSuper','adminsCount','allUsersCount',

            ));
        }
    }
