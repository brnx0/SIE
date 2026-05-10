<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class UsersController extends Controller{
    private function getRoles(){
        $roles = User::ROLES;
        asort($roles);
        return $roles;
    }
    public function index(Request $request): Response
    {
        $query = User::query()->orderBy('name');

        if ($search = $request->string('search')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                  ->orWhere('email', 'ilike', "%{$search}%");
            });
        }

        if ($role = $request->string('role')->toString()) {
            $query->where('role', $role);
        }

        return Inertia::render('users/Index', [
            'users' => $query->paginate(10)->withQueryString(),
            'filters' => [
                'search' => $search,
                'role' => $role,
            ],
            'roles' => $this->getRoles(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('users/Create', [
            'roles' => $this->getRoles(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', Rule::in(array_keys($this->getRoles()))],
            'phone' => ['nullable', 'string', 'max:30'],
            'active' => ['boolean'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $data['password'] = Hash::make($data['password']);
        $data['active'] = $data['active'] ?? true;

        User::create($data);

        return to_route('users.index')->with('success', 'Usuário cadastrado com sucesso.');
    }

    public function edit(User $user): Response
    {
        return Inertia::render('users/Edit', [
            'user' => $user,
            'roles' => $this->getRoles(),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'role' => ['required', Rule::in(array_keys($this->getRoles()))],
            'phone' => ['nullable', 'string', 'max:30'],
            'active' => ['boolean'],
            'password' => ['nullable', 'confirmed', Password::defaults()],
        ]);

        if (! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $user->update($data);

        return to_route('users.index')->with('success', 'Usuário atualizado com sucesso.');
    }

    public function destroy(User $user): RedirectResponse
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'Você não pode excluir sua própria conta.');
        }

        $user->delete();

        return to_route('users.index')->with('success', 'Usuário removido com sucesso.');
    }
}
