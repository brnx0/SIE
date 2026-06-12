<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;
use Mpdf\Mpdf;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UsersController extends Controller
{
    private function getRoles(): array
    {
        $roles = User::ROLES;
        asort($roles);
        return $roles;
    }

    public function index(Request $request): Response
    {
        $perPage = in_array((int) $request->input('per_page'), [10, 25, 50])
            ? (int) $request->input('per_page')
            : 10;

        return Inertia::render('users/Index', [
            'users'   => $this->baseQuery($request)->paginate($perPage)->withQueryString(),
            'filters' => [
                'search'   => $request->string('search')->toString(),
                'role'     => $request->string('role')->toString(),
                'per_page' => $perPage,
            ],
            'roles'   => $this->getRoles(),
        ]);
    }

    public function export(Request $request): StreamedResponse|HttpResponse
    {
        $users = $this->baseQuery($request)->get();

        if ($request->input('format') === 'pdf') {
            return $this->exportPdf($users, $request);
        }

        return $this->exportCsv($users);
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
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'max:255', 'unique:users,email'],
            'role'     => ['required', Rule::in(array_keys($this->getRoles()))],
            'phone'    => ['nullable', 'string', 'max:30'],
            'active'   => ['boolean'],
            'fun_id'   => ['nullable', 'integer', 'exists:edu_funcionario,fun_id'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $data['password'] = Hash::make($data['password']);
        $data['active']   = $data['active'] ?? true;

        $user = User::create($data);

        return to_route('users.edit', $user)->with('success', 'Usuário cadastrado com sucesso.');
    }

    public function edit(User $user): Response
    {
        $user->loadMissing('funcionario:fun_id,fun_nome,fun_cpf');

        return Inertia::render('users/Edit', [
            'user'               => $user,
            'roles'              => $this->getRoles(),
            'initialFuncionario' => $user->funcionario
                ? $user->funcionario->only(['fun_id', 'fun_nome', 'fun_cpf'])
                : null,
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'role'     => ['required', Rule::in(array_keys($this->getRoles()))],
            'phone'    => ['nullable', 'string', 'max:30'],
            'active'   => ['boolean'],
            'fun_id'   => ['nullable', 'integer', 'exists:edu_funcionario,fun_id'],
            'password' => ['nullable', 'confirmed', Password::defaults()],
        ]);

        if (! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $user->update($data);

        return to_route('users.edit', $user)->with('success', 'Usuário atualizado com sucesso.');
    }

    public function destroy(User $user): RedirectResponse
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'Você não pode excluir sua própria conta.');
        }

        return $this->safeDelete($user)
            ?? to_route('users.index')->with('success', 'Usuário removido com sucesso.');
    }

    private function baseQuery(Request $request)
    {
        $search = $request->string('search')->toString();
        $role   = $request->string('role')->toString();

        return User::query()
            ->when($search, function ($q) use ($search) {
                $q->where(function ($q) use ($search) {
                    $q->where('name', 'ilike', "%{$search}%")
                      ->orWhere('email', 'ilike', "%{$search}%");
                });
            })
            ->when($role, fn ($q) => $q->where('role', $role))
            ->orderBy('name');
    }

    private function exportCsv($users): StreamedResponse
    {
        $filename = 'usuarios_' . now()->format('Ymd_His') . '.csv';
        $roles    = $this->getRoles();

        return response()->streamDownload(function () use ($users, $roles) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($out, ['Nome', 'E-mail', 'Perfil', 'Status'], ';');
            foreach ($users as $u) {
                fputcsv($out, [
                    $u->name,
                    $u->email,
                    $roles[$u->role] ?? $u->role,
                    $u->active ? 'Ativo' : 'Inativo',
                ], ';');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function exportPdf($users, Request $request): HttpResponse
    {
        $collection = collect($users);
        $roles      = $this->getRoles();
        $filename   = 'usuarios_' . now()->format('Ymd_His') . '.pdf';

        $html = view('exports.users_pdf', [
            'users'         => $collection,
            'roles'         => $roles,
            'total'         => $collection->count(),
            'totalAtivos'   => $collection->where('active', true)->count(),
            'totalInativos' => $collection->where('active', false)->count(),
            'search'        => $request->input('search', ''),
            'roleLabel'     => $request->input('role') ? ($roles[$request->input('role')] ?? '') : '',
        ])->render();

        $mpdf = new Mpdf(['orientation' => 'L', 'format' => 'A4', 'margin_top' => 0, 'margin_bottom' => 0, 'margin_left' => 0, 'margin_right' => 0, 'tempDir' => sys_get_temp_dir()]);
        $mpdf->WriteHTML($html);

        return response($mpdf->Output($filename, 'S'), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
