<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Income;
use App\Models\IncomeCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class IncomeController extends Controller
{
    /**
     * Compute the "group root" and return all user IDs that belong to the same group:
     * - If current user is a root account (created_by = null): returns [root, ...children]
     * - If current user is a sub account: returns [root, sub]
     *
     * Returned value is a Collection of unique integers (values()).
     */
    protected function visibleUserIds()
    {
        $u = Auth::user();
        $groupRootId = $u->created_by ?? $u->id;

        // all children of the root (may be empty), plus the root itself
        $children = User::where('created_by', $groupRootId)->pluck('id');

        return collect([$groupRootId])
            ->merge($children)
            ->unique()
            ->values();
    }

    /**
     * Category IDs the current user is allowed to use: global (user_id IS NULL)
     * plus the tenant owner (ownerIdFor will return root owner id).
     */
    protected function allowedCategoryIdsForCurrentUser(): array
    {
        $u = Auth::user();
        $ownerId = IncomeCategory::ownerIdFor($u);

        return IncomeCategory::whereNull('user_id')
            ->orWhere('user_id', $ownerId)
            ->pluck('id')
            ->map(fn($id) => (int) $id)
            ->all();
    }

    /** Categories to show in the dropdown */
    protected function categoriesForCurrentUser()
    {
        $u = Auth::user();
        $ownerId = IncomeCategory::ownerIdFor($u);

        return IncomeCategory::whereNull('user_id')
            ->orWhere('user_id', $ownerId)
            ->orderByRaw('user_id IS NULL DESC') // globals first
            ->orderBy('name')
            ->get();
    }

    /**
     * Index - returns incomes visible to the group (collection, not paginated)
     * -> suitable for server-rendered table consumed by DataTables (client-side).
     */
    public function index()
    {
        $visible = $this->visibleUserIds();

        $incomes = Income::with(['user', 'category'])
            ->whereIn('user_id', $visible)
            ->orderBy('date', 'desc')
            ->get(); // get() intentionally (DataTables client-side)

        $categories = $this->categoriesForCurrentUser();

        return view('frontend.incomes.index', compact('incomes', 'categories'));
    }

    public function show(Income $income)
    {
        $income->load('category');
        // show should be accessible only if visible in group
        if (! $this->visibleUserIds()->contains($income->user_id)) {
            abort(Response::HTTP_FORBIDDEN);
        }
        return view('frontend.incomes.show', compact('income'));
    }

    public function store(Request $request)
    {
        $allowedIds = $this->allowedCategoryIdsForCurrentUser();

        $data = $request->validate([
            'identifier'         => 'nullable|string|max:255',
            'amount'             => 'required|numeric|min:0',
            'date'               => 'required|date',
            'income_category_id' => 'nullable|integer|exists:income_categories,id',
        ]);

        $catId = isset($data['income_category_id']) ? (int) $data['income_category_id'] : null;
        if (!is_null($catId) && !in_array($catId, $allowedIds, true)) {
            abort(Response::HTTP_FORBIDDEN, 'Categoria non valida.');
        }

        $data['user_id'] = Auth::id();
        Income::create($data);

        return redirect()->route('incomes.index')->with('success', 'Entrata registrata!');
    }

    /**
     * Edit: show the same index view but include $income to populate the form for editing.
     * Note: we return the SAME $incomes collection shape as index() (not paginated).
     */
    public function edit(Income $income)
    {
        // Only allow editing if the income belongs to the visible group
        if (! $this->visibleUserIds()->contains($income->user_id)) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $visible = $this->visibleUserIds();

        $incomes = Income::with(['user', 'category'])
            ->whereIn('user_id', $visible)
            ->orderBy('date', 'desc')
            ->get(); // same as index so your blade (and DataTable) receives a collection

        $categories = $this->categoriesForCurrentUser();

        return view('frontend.incomes.index', compact('incomes', 'categories', 'income'));
    }

    public function update(Request $request, Income $income)
    {
        if (! $this->visibleUserIds()->contains($income->user_id)) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $allowedIds = $this->allowedCategoryIdsForCurrentUser();

        $data = $request->validate([
            'identifier'         => 'nullable|string|max:255',
            'amount'             => 'required|numeric|min:0',
            'date'               => 'required|date',
            'income_category_id' => 'nullable|integer|exists:income_categories,id',
        ]);

        $catId = isset($data['income_category_id']) ? (int) $data['income_category_id'] : null;
        if (!is_null($catId) && !in_array($catId, $allowedIds, true)) {
            abort(Response::HTTP_FORBIDDEN, 'Categoria non valida.');
        }

        $income->update($data);

        return redirect()->route('incomes.index')->with('success', 'Entrata aggiornata!');
    }

    public function destroy(Income $income)
    {
        // Only allow deletion if the record is visible to the group
        if (! $this->visibleUserIds()->contains($income->user_id)) {
            abort(Response::HTTP_FORBIDDEN);
        }

        // Optionally: stricter policy — allow delete only for the record owner (uncomment to enable)
        // if ($income->user_id !== Auth::id()) { abort(Response::HTTP_FORBIDDEN); }

        $income->delete();
        return back()->with('success', 'Entrata rimossa.');
    }
}
