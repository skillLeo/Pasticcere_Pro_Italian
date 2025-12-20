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
     * Calcula la “raíz de grupo” y devuelve todos los IDs de usuario que pertenecen
     * al mismo grupo:
     * - Si el usuario actual es una cuenta raíz (created_by = null): devuelve [root, ...hijos]
     * - Si el usuario actual es una subcuenta: devuelve [root, sub]
     *
     * El valor devuelto es una Collection de enteros únicos (values()).
     */
    protected function visibleUserIds()
    {
        $u = Auth::user();
        $groupRootId = $u->created_by ?? $u->id;

        // todos los hijos de la raíz (puede estar vacío) más la propia raíz
        $children = User::where('created_by', $groupRootId)->pluck('id');

        return collect([$groupRootId])
            ->merge($children)
            ->unique()
            ->values();
    }

    /**
     * IDs de categorías que el usuario actual puede usar:
     * globales (user_id IS NULL) más el propietario del tenant
     * (ownerIdFor devolverá el id del propietario raíz).
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

    /** Categorías que se mostrarán en el desplegable */
    protected function categoriesForCurrentUser()
    {
        $u = Auth::user();
        $ownerId = IncomeCategory::ownerIdFor($u);

        return IncomeCategory::whereNull('user_id')
            ->orWhere('user_id', $ownerId)
            ->orderByRaw('user_id IS NULL DESC') // globales primero
            ->orderBy('name')
            ->get();
    }

    /**
     * Index - devuelve los ingresos visibles para el grupo (collection, sin paginar)
     * -> adecuado para tabla renderizada en servidor consumida por DataTables (del lado cliente).
     */
    public function index()
    {
        $visible = $this->visibleUserIds();

        $incomes = Income::with(['user', 'category'])
            ->whereIn('user_id', $visible)
            ->orderBy('date', 'desc')
            ->get(); // get() intencionadamente (DataTables lado cliente)

        $categories = $this->categoriesForCurrentUser();

        return view('frontend.incomes.index', compact('incomes', 'categories'));
    }

    public function show(Income $income)
    {
        $income->load('category');
        // show solo debe ser accesible si es visible en el grupo
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
            abort(Response::HTTP_FORBIDDEN, 'Categoría no válida.');
        }

        $data['user_id'] = Auth::id();
        Income::create($data);

        return redirect()->route('incomes.index')->with('success', 'Entrada registrada.');
    }

    /**
     * Editar: muestra la misma vista index pero incluye $income para rellenar
     * el formulario de edición.
     * Nota: devolvemos la MISMA colección $incomes que en index() (sin paginar).
     */
    public function edit(Income $income)
    {
        // Permitir edición solo si el ingreso pertenece al grupo visible
        if (! $this->visibleUserIds()->contains($income->user_id)) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $visible = $this->visibleUserIds();

        $incomes = Income::with(['user', 'category'])
            ->whereIn('user_id', $visible)
            ->orderBy('date', 'desc')
            ->get(); // igual que en index, para que la blade (y DataTable) reciba una collection

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
            abort(Response::HTTP_FORBIDDEN, 'Categoría no válida.');
        }

        $income->update($data);

        return redirect()->route('incomes.index')->with('success', 'Entrada actualizada.');
    }

    public function destroy(Income $income)
    {
        // Permitir eliminación solo si el registro es visible para el grupo
        if (! $this->visibleUserIds()->contains($income->user_id)) {
            abort(Response::HTTP_FORBIDDEN);
        }

        // Opcional: política más estricta — permitir borrar solo al propietario del registro (descomentar para activar)
        // if ($income->user_id !== Auth::id()) { abort(Response::HTTP_FORBIDDEN); }

        $income->delete();
        return back()->with('success', 'Entrada eliminada.');
    }
}
