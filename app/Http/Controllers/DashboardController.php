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
use App\Models\ExternalSupplyRecipe;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use ArielMejiaDev\LarapexCharts\LarapexChart;

class DashboardController extends Controller
{
    /**
     * Larapex addData() MUST receive array.
     * This helper converts anything (string/collection/number/null) into numeric array.
     */
    private function asNumericArray($data): array
    {
        if ($data instanceof \Illuminate\Support\Collection) {
            $data = $data->values()->all();
        }

        // If someone passed JSON string, decode it
        if (is_string($data)) {
            $decoded = json_decode($data, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $data = $decoded;
            } else {
                // If comma-separated "10,20,30"
                $trim = trim($data);
                if ($trim === '') {
                    $data = [];
                } elseif (str_contains($trim, ',')) {
                    $data = array_map('trim', explode(',', $trim));
                } else {
                    $data = [$trim];
                }
            }
        }

        // If single number
        if (!is_array($data)) {
            $data = [$data];
        }

        return array_map(static function ($v) {
            if ($v === null || $v === '') return 0;
            return (float) $v;
        }, $data);
    }

    public function index()
    {
        Carbon::setLocale('it');

        $user = Auth::user();

        // 1) Determine visible users
        if (is_null($user->created_by)) {
            $children = User::where('created_by', $user->id)->pluck('id');
            $visibleUserIds = $children->isEmpty()
                ? collect([$user->id])
                : $children->push($user->id);
        } else {
            $visibleUserIds = collect([$user->id, $user->created_by]);
        }

        // 2) Core metrics
        $totalUsers     = User::whereIn('id', $visibleUserIds)->count();
        $totalRecipes   = Recipe::whereIn('user_id', $visibleUserIds)->count();
        $totalShowcases = Showcase::whereIn('user_id', $visibleUserIds)->count();

        $year = Carbon::now()->year;

        $totalSaleThisYear = Showcase::whereIn('user_id', $visibleUserIds)
            ->whereYear('showcase_date', $year)
            ->sum('total_revenue');

        $totalWasteThisYear = ShowcaseRecipe::whereIn('user_id', $visibleUserIds)
            ->whereHas('showcase', fn ($q) => $q->whereYear('showcase_date', $year))
            ->sum('waste');

        $totalProfitThisYear = Showcase::whereIn('user_id', $visibleUserIds)
            ->whereYear('showcase_date', $year)
            ->sum('real_margin');

        // 3) Earnings metrics
        $sales      = Showcase::whereIn('user_id', $visibleUserIds)->sum('total_revenue');
        $plus       = Showcase::whereIn('user_id', $visibleUserIds)->sum('plus');
        $realMargin = Showcase::whereIn('user_id', $visibleUserIds)->sum('real_margin');

        // 4) Vendite Mensili (anno corrente)  ✅ FIX: ensure arrays
        $monthlyData = Showcase::selectRaw("MONTH(showcase_date) AS month, SUM(total_revenue) AS total")
            ->whereIn('user_id', $visibleUserIds)
            ->whereYear('showcase_date', $year)
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        $labels = $monthlyData->pluck('month')
            ->map(fn ($m) => Carbon::createFromDate(null, $m, 1)->locale('it')->isoFormat('MMM'))
            ->values()
            ->all();

        $values = $this->asNumericArray($monthlyData->pluck('total'));

        // prevent weird empty state (optional)
        if (empty($labels)) $labels = ['—'];
        if (empty($values)) $values = [0];

        $chart = (new LarapexChart)->barChart()
            ->setTitle('Vendite Mensili')
            ->setSubtitle('Anno Corrente')
            ->addData('Ricavi', $values)          // ✅ always array
            ->setXAxis($labels);

        // 5) Costi e Ricavi (mese corrente)
        $start = Carbon::now()->startOfMonth();
        $end   = Carbon::now()->endOfMonth();

        $monthlyCost = Cost::whereIn('user_id', $visibleUserIds)
            ->whereBetween('due_date', [$start, $end])
            ->sum('amount');

        $monthlyIncome = Income::whereIn('user_id', $visibleUserIds)
            ->whereBetween('date', [$start, $end])
            ->sum('amount');

        $comparisonChart = (new LarapexChart)->barChart()
            ->setTitle('Costi e Ricavi')
            ->setSubtitle(Carbon::now()->locale('it')->isoFormat('MMMM YYYY'))
            ->addData('Costi', $this->asNumericArray([$monthlyCost]))
            ->addData('Ricavi', $this->asNumericArray([$monthlyIncome]))
            ->setXAxis([Carbon::now()->locale('it')->isoFormat('MMMM')]);

        // 6) Confronto Costi Annuali
        $thisYear = Carbon::now()->year;
        $lastYear = Carbon::now()->subYear()->year;

        $yearlyCostThis = Cost::whereIn('user_id', $visibleUserIds)
            ->whereYear('due_date', $thisYear)->sum('amount');

        $yearlyCostLast = Cost::whereIn('user_id', $visibleUserIds)
            ->whereYear('due_date', $lastYear)->sum('amount');

        $yearlyCostChart = (new LarapexChart)->barChart()
            ->setTitle('Costi Annuali')
            ->setSubtitle("$lastYear vs $thisYear")
            ->addData('Costi', $this->asNumericArray([$yearlyCostLast, $yearlyCostThis]))
            ->setXAxis([(string)$lastYear, (string)$thisYear]);

        // 7) Confronto Ricavi Annuali
        $yearlyIncomeThis = Income::whereIn('user_id', $visibleUserIds)
            ->whereYear('date', $thisYear)->sum('amount');

        $yearlyIncomeLast = Income::whereIn('user_id', $visibleUserIds)
            ->whereYear('date', $lastYear)->sum('amount');

        $yearlyIncomeChart = (new LarapexChart)->barChart()
            ->setTitle('Ricavi Annuali')
            ->setSubtitle("$lastYear vs $thisYear")
            ->addData('Ricavi', $this->asNumericArray([$yearlyIncomeLast, $yearlyIncomeThis]))
            ->setXAxis([(string)$lastYear, (string)$thisYear]);

        // 8) Top 5 Prodotti Venduti & Sprechi
        $topSold = ShowcaseRecipe::with(['recipe:id,recipe_name'])
            ->whereIn('user_id', $visibleUserIds)
            ->selectRaw('recipe_id, SUM(sold) as sold')
            ->groupBy('recipe_id')
            ->orderByDesc('sold')
            ->take(5)
            ->get();

        $topWasted = ShowcaseRecipe::with(['recipe:id,recipe_name'])
            ->whereIn('user_id', $visibleUserIds)
            ->selectRaw('recipe_id, SUM(waste) as waste')
            ->groupBy('recipe_id')
            ->orderByDesc('waste')
            ->take(5)
            ->get();

        $soldValues = $this->asNumericArray($topSold->pluck('sold'));
        $soldLabels = $topSold->map(fn ($item) => $item->recipe->recipe_name ?? 'Sconosciuto')->values()->all();

        if (empty($soldLabels)) $soldLabels = ['—'];
        if (empty($soldValues)) $soldValues = [0];

        $soldPieChart = (new LarapexChart)->donutChart()
            ->setTitle('Distribuzione Vendite')
            ->addData($soldValues)
            ->setLabels($soldLabels);

        $wastedLabels = $topWasted->map(fn ($item) => $item->recipe->recipe_name ?? 'Sconosciuto')->values()->all();
        $wastedValues = $this->asNumericArray($topWasted->pluck('waste'));

        if (empty($wastedLabels)) $wastedLabels = ['—'];
        if (empty($wastedValues)) $wastedValues = [0];

        $wastedPieChart = (new LarapexChart)->donutChart()
            ->setTitle('Distribuzione Sprechi')
            ->addData($wastedValues)
            ->setLabels($wastedLabels);

        // 9) Incidenza Costi vs Ricavi (month)
        $totalCost   = (float) $monthlyCost;
        $totalRev    = (float) $monthlyIncome;
        $nettoAmount = $totalRev - $totalCost;

        $incidenceChart = (new LarapexChart)->pieChart()
            ->setTitle('Incidenza Costi vs Ricavi')
            ->addData($this->asNumericArray([$totalCost, $totalRev, $nettoAmount]))
            ->setLabels(['Costi', 'Ricavi', 'Netto']);

        // 10) Produzione per Pasticcere
        $prodByChef = ProductionDetail::selectRaw('pastry_chef_id, SUM(quantity) AS qty')
            ->whereIn('user_id', $visibleUserIds)
            ->groupBy('pastry_chef_id')
            ->with('chef:id,name')
            ->orderByDesc('qty')
            ->get();

        $chefLabels = $prodByChef->pluck('chef.name')->map(fn ($n) => $n ?: 'Sconosciuto')->values()->all();
        $chefValues = $this->asNumericArray($prodByChef->pluck('qty'));

        if (empty($chefLabels)) $chefLabels = ['—'];
        if (empty($chefValues)) $chefValues = [0];

        $chefChart = (new LarapexChart)->barChart()
            ->setTitle('Produzione per Pasticcere')
            ->addData('Unità', $chefValues)
            ->setXAxis($chefLabels);

        // 11) Andamento Produzione vs Spreco (annuale)
        $prodTrend = ProductionDetail::join('productions', 'productions.id', '=', 'production_details.production_id')
            ->whereIn('production_details.user_id', $visibleUserIds)
            ->whereYear('productions.production_date', $year)
            ->selectRaw('MONTH(productions.production_date) AS m, SUM(production_details.quantity) AS produced')
            ->groupBy('m')->orderBy('m')->get();

        $wasteTrend = ShowcaseRecipe::join('showcases', 'showcases.id', '=', 'showcase_recipes.showcase_id')
            ->whereIn('showcase_recipes.user_id', $visibleUserIds)
            ->whereYear('showcases.showcase_date', $year)
            ->selectRaw('MONTH(showcases.showcase_date) AS m, SUM(showcase_recipes.waste) AS waste')
            ->groupBy('m')->orderBy('m')->get();

        $labelsTrend = $prodTrend->pluck('m')
            ->map(fn ($m) => Carbon::createFromDate(null, $m, 1)->locale('it')->isoFormat('MMM'))
            ->values()
            ->all();

        $prodVals  = $this->asNumericArray($prodTrend->pluck('produced'));
        $wasteVals = $this->asNumericArray($wasteTrend->pluck('waste'));

        if (empty($labelsTrend)) $labelsTrend = ['—'];
        if (empty($prodVals)) $prodVals = [0];
        if (empty($wasteVals)) $wasteVals = [0];

        $prodWasteChart = (new LarapexChart)->areaChart()
            ->setTitle('Produzione vs Spreco')
            ->addData('Prodotto', $prodVals)
            ->addData('Spreco', $wasteVals)
            ->setXAxis($labelsTrend);

        // 12) Ripartizione dei costi per categoria (for JS)
        $costByCategory = Cost::join('cost_categories', 'costs.category_id', '=', 'cost_categories.id')
            ->whereIn('costs.user_id', $visibleUserIds)
            ->groupBy('cost_categories.id', 'cost_categories.name')
            ->select('cost_categories.name as category', DB::raw('SUM(costs.amount) as total'))
            ->get();

        $categoryLabels = $costByCategory->pluck('category')->values()->all();
        $categoryValues = $this->asNumericArray($costByCategory->pluck('total'));
        $categoryTotal  = array_sum($categoryValues);

        // Returns vs Restocks
        $totalSupplied = ExternalSupplyRecipe::whereIn('user_id', $visibleUserIds)->sum('qty');

        $totalReturned = ReturnedGoodRecipe::join('returned_goods', 'returned_goods.id', '=', 'returned_good_recipes.returned_good_id')
            ->whereIn('returned_goods.user_id', $visibleUserIds)
            ->sum('qty');

        $restocked = max(0, (int)$totalSupplied - (int)$totalReturned);

        $returnRateChart = (new LarapexChart)
            ->donutChart()
            ->setTitle('Resi vs Rifornimenti')
            ->addData([(int)$totalReturned, (int)$restocked])
            ->setLabels(['Resi', 'Riforniti'])
            ->setHeight(300);

        // 13) Full datasets for JS filtering (make relationships null-safe)
        $fullMonthlyData = $monthlyData->map(fn ($row) => [
            'date'  => Carbon::createFromDate(null, $row->month, 1)->format('Y-m-d'),
            'total' => (float) $row->total,
        ])->toArray();

        $fullSoldData = ShowcaseRecipe::with(['recipe', 'showcase'])
            ->whereIn('user_id', $visibleUserIds)
            ->get()
            ->map(fn ($r) => [
                'recipe_name' => $r->recipe->recipe_name ?? 'Sconosciuto',
                'sold'        => (float) ($r->sold ?? 0),
                'date'        => optional($r->showcase?->showcase_date)->format('Y-m-d') ?? Carbon::now()->format('Y-m-d'),
            ])->toArray();

        $fullWastedData = ShowcaseRecipe::with(['recipe', 'showcase'])
            ->whereIn('user_id', $visibleUserIds)
            ->get()
            ->map(fn ($r) => [
                'recipe_name' => $r->recipe->recipe_name ?? 'Sconosciuto',
                'waste'       => (float) ($r->waste ?? 0),
                'date'        => optional($r->showcase?->showcase_date)->format('Y-m-d') ?? Carbon::now()->format('Y-m-d'),
            ])->toArray();

        $fullCostData = Cost::with('category')
            ->whereIn('user_id', $visibleUserIds)
            ->get()
            ->map(fn ($c) => [
                'date'     => optional($c->due_date)->format('Y-m-d') ?? Carbon::now()->format('Y-m-d'),
                'amount'   => (float) $c->amount,
                'category' => optional($c->category)->name ?? 'Senza categoria',
            ])->toArray();

        $fullIncomeData = Income::leftJoin('income_categories as ic', 'incomes.income_category_id', '=', 'ic.id')
            ->whereIn('incomes.user_id', $visibleUserIds)
            ->select([
                'incomes.date',
                'incomes.amount',
                DB::raw('COALESCE(ic.name, "Senza categoria") as category'),
            ])
            ->get()
            ->map(fn ($i) => [
                'date'     => Carbon::parse($i->date)->format('Y-m-d'),
                'amount'   => (float) $i->amount,
                'category' => $i->category,
            ])->toArray();

        // Category averages (positives only)
        $categoryAvgRaw = Recipe::whereIn('recipes.user_id', $visibleUserIds)
            ->leftJoin('recipe_categories as rc', 'recipes.recipe_category_id', '=', 'rc.id')
            ->groupBy('rc.id', 'rc.name')
            ->selectRaw('
                COALESCE(rc.name, "Senza categoria") AS name,
                COUNT(*) AS total_cnt,
                SUM(CASE WHEN recipes.potential_margin_pct >= 0 THEN 1 ELSE 0 END) AS pos_cnt,
                ROUND(AVG(CASE WHEN recipes.potential_margin_pct >= 0 THEN recipes.potential_margin_pct END), 2) AS avg_margin_pos
            ')
            ->get();

        $categoryAvgTop = $categoryAvgRaw->sortByDesc('pos_cnt')->take(8)->values();

        $globalAvgMarginPos = Recipe::whereIn('user_id', $visibleUserIds)
            ->avg(DB::raw('CASE WHEN potential_margin_pct >= 0 THEN potential_margin_pct END'));
        $globalAvgMarginPos = round($globalAvgMarginPos ?? 0, 2);

        // Super metrics
        $isSuper = method_exists($user, 'hasRole') ? $user->hasRole('super') : false;

        $adminsCount = $isSuper
            ? User::whereHas('roles', fn ($q) => $q->where('name', 'admin')->where('guard_name', 'web'))->count()
            : 0;

        $allUsersCount = $isSuper ? User::count() : 0;

        // Full chef data for JS filtering
        $fullChefData = ProductionDetail::join('productions', 'productions.id', '=', 'production_details.production_id')
            ->leftJoin('users as u', 'u.id', '=', 'production_details.pastry_chef_id')
            ->whereIn('production_details.user_id', $visibleUserIds)
            ->select([
                DB::raw('DATE(productions.production_date) as date'),
                DB::raw('COALESCE(u.name, "Sconosciuto") as chef_name'),
                'production_details.quantity as qty',
            ])
            ->orderBy('productions.production_date')
            ->get()
            ->map(fn ($r) => [
                'date'      => Carbon::parse($r->date)->format('Y-m-d'),
                'chef_name' => $r->chef_name,
                'qty'       => (float) $r->qty,
            ])->toArray();

        return view('dashboard', compact(
            'sales', 'plus', 'realMargin',
            'chart', 'comparisonChart', 'yearlyCostChart', 'yearlyIncomeChart',
            'topSold', 'topWasted',
            'soldPieChart', 'wastedPieChart', 'incidenceChart',
            'totalUsers', 'totalRecipes', 'totalShowcases',
            'totalSaleThisYear', 'totalWasteThisYear', 'totalProfitThisYear',
            'year', 'totalSupplied', 'totalReturned',
            'chefChart', 'prodWasteChart', 'returnRateChart',
            'fullMonthlyData', 'fullSoldData', 'fullWastedData',
            'fullCostData', 'fullIncomeData', 'fullChefData',
            'categoryAvgTop', 'globalAvgMarginPos',
            'categoryLabels', 'categoryValues', 'categoryTotal',
            'isSuper', 'adminsCount', 'allUsersCount'
        ));
    }
}