<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use App\Http\Controllers\Controller;
use App\Mail\NewUserCredentialsMail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class UserController extends Controller
{
    public function profile()
    {
        $user = Auth::user()->load('roles');
        return view('frontend.profile', compact('user'));
    }

    public function updateProfile(Request $request)
    {
        $data = $request->validate([
            'name'   => 'required|string|max:255',
            'email'  => 'required|email|unique:users,email,' . Auth::id(),
            'vat'    => 'nullable|string|max:255',
            'address'=> 'nullable|string|max:255',
            'photo'  => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($request->hasFile('photo')) {
            // Guardar en storage/app/public/photos
            $path = $request->file('photo')->store('photos', 'public'); // <-- unificado
            $data['photo'] = basename($path); // solo el nombre del archivo en la BD
        }

        Auth::user()->update($data);

        return redirect()->route('profile')->with('success', 'Perfil actualizado con éxito!');
    }


    public function index()
    {
        $query = User::with('roles');

        if (!Auth::user()->hasRole('super')) {
            $query->where('created_by', Auth::id());
        }

        $users = $query->get();

        return view('frontend.user-management.users.index', compact('users'));
    }

    public function create()
    {
        $currentUser = Auth::user();
    
        if ($currentUser->hasRole('super')) {
            $roles = Role::where('name', '!=', 'super')->get();
        } else {
            $roles = Role::whereNotIn('name', ['super','admin'])->get();
        }
    
        $user   = new User();
        $isEdit = false;
    
        return view('frontend.user-management.users.create', compact('roles','user','isEdit'));
    }

    public function toggleStatus(User $user)
    {
        if (!Auth::user()->hasRole('super')) {
            abort(403, 'Acceso no autorizado.');
        }
    
        $user->status = !$user->status;
        $user->save();
    
        $relatedUsers = User::where('created_by', $user->id)->get();
        foreach ($relatedUsers as $relatedUser) {
            $relatedUser->status = $user->status;
            $relatedUser->save();
        }
    
        return redirect()->back()
            ->with('success', 'Estado del usuario actualizado.');
    }

    public function edit(User $user)
    {
        $currentUser = Auth::user();

        if ($currentUser->hasRole('super')) {
            $roles = Role::all();
        } else {
            $roles = Role::whereNotIn('name', ['super', 'admin'])->get();
        }

        $isEdit = true;

        return view('frontend.user-management.users.create', compact('roles', 'user', 'isEdit'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'            => 'required|string|max:255',
            'email'           => 'required|email|unique:users,email',
            'password'        => 'required|string|min:6',
            'role'            => 'required|exists:roles,id',
            'vat'             => 'nullable|string|max:255',
            'address'         => 'nullable|string|max:255',
            'photo'           => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'expiry_enabled'  => 'nullable|in:on',
            'expiry_date'     => 'required_if:expiry_enabled,on|date|after_or_equal:today',
        ]);

        // store()
        if ($request->hasFile('photo')) {
            // guarda en storage/app/public/photos y devuelve "photos/xxxxx.jpg"
            $path = $request->file('photo')->store('photos', 'public');
            $data['photo'] = basename($path); // guardar solo el nombre en la BD
        }

        $plainPassword = $data['password']; // mantener antes de hacer hash

        $roleModel = Role::findOrFail($data['role']);

        $user = User::create([
            'name'        => $data['name'],
            'email'       => $data['email'],
            'password'    => Hash::make($plainPassword),
            'vat'         => $data['vat'] ?? null,
            'address'     => $data['address'] ?? null,
            'photo'       => $data['photo'] ?? null,
            'created_by'  => $roleModel->name === 'admin' ? null : auth()->id(),
            'expiry_date' => $request->has('expiry_enabled') ? $data['expiry_date'] : null,
        ]);

        $user->syncRoles($roleModel);

        // --- Enviar correo con credenciales
        try {
            Mail::to($user->email)
                ->send(new NewUserCredentialsMail($user, $plainPassword, route('login')));
        } catch (\Throwable $e) {
            report($e);
            // Opcional: mostrar un aviso pero no bloquear la creación
            // session()->flash('warning', 'Usuario creado, pero el envío del email ha fallado.');
        }

        return redirect()->route('users.index')
            ->with('success', '¡Usuario creado con éxito! Las credenciales han sido enviadas por correo electrónico.');
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name'            => 'required|string|max:255',
            'email'           => 'required|email|unique:users,email,' . $user->id,
            'password'        => 'nullable|string|min:6',
            'role'            => 'required|exists:roles,id',
            'vat'             => 'nullable|string|max:255',
            'address'         => 'nullable|string|max:255',
            'photo'           => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'expiry_enabled'  => 'nullable|in:on',
            'expiry_date'     => 'required_if:expiry_enabled,on|date|after_or_equal:today',
        ]);

        if ($request->hasFile('photo')) {
            $path = $request->file('photo')->store('photos', 'public');
            $data['photo'] = basename($path);
        }

        $sendNewPassword = false;
        $plainPassword   = null;

        $user->name     = $data['name'];
        $user->email    = $data['email'];
        $user->vat      = $data['vat'] ?? null;
        $user->address  = $data['address'] ?? null;
        $user->photo    = $data['photo'] ?? $user->photo;

        if (!empty($data['password'])) {
            $sendNewPassword = true;
            $plainPassword   = $data['password'];
            $user->password  = Hash::make($plainPassword);
        }

        $user->expiry_date = $request->has('expiry_enabled') ? $data['expiry_date'] : null;
        $user->save();

        $roleModel = Role::findOrFail($data['role']);
        $user->syncRoles($roleModel);

        if ($sendNewPassword) {
            try {
                Mail::to($user->email)
                    ->send(new NewUserCredentialsMail($user, $plainPassword, route('login')));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return redirect()->route('users.index')
            ->with('success', 'Usuario actualizado con éxito!');
    }

    public function show(User $user)
    {
        return view('frontend.user-management.users.show', compact('user'));
    }

    public function destroy(User $user)
    {
        $user->delete();

        return redirect()
            ->route('users.index')
            ->with('success', 'Usuario eliminado.');
    }
}
