<?php

namespace App\Http\Controllers\Funcionario;

use App\Http\Controllers\Controller;
use App\Http\Requests\Funcionario\StoreFuncionarioRequest;
use App\Http\Requests\Funcionario\UpdateFuncionarioRequest;
use App\Models\Funcionario\Funcionario;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class FuncionarioController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Funcionario::query()
            ->with('municipioNascimento:mun_id,mun_nome,mun_uf')
            ->orderBy('fun_nome');

        if ($search = $request->string('search')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('fun_nome', 'ilike', "%{$search}%")
                    ->orWhere('fun_cpf', 'like', "%{$search}%");
            });
        }

        return Inertia::render('funcionarios/Index', [
            'funcionarios' => $query->paginate(15)->withQueryString(),
            'filters' => ['search' => $search],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('funcionarios/Create');
    }

    public function store(StoreFuncionarioRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $data = $request->safe()->except(['fun_foto']);

            if ($request->hasFile('fun_foto')) {
                $data['fun_foto'] = $request->file('fun_foto')->store('funcionarios', 'public');
            }

            Funcionario::create($data);
        });

        return to_route('funcionarios.index')->with('success', 'Funcionário cadastrado com sucesso.');
    }

    public function edit(Funcionario $funcionario): Response
    {
        $funcionario->load([
            'municipioNascimento:mun_id,mun_nome,mun_uf',
            'municipioCertidao:mun_id,mun_nome,mun_uf',
        ]);

        return Inertia::render('funcionarios/Edit', [
            'funcionario' => $funcionario,
        ]);
    }

    public function update(UpdateFuncionarioRequest $request, Funcionario $funcionario): RedirectResponse
    {
        DB::transaction(function () use ($request, $funcionario) {
            $data = $request->safe()->except(['fun_foto']);

            if ($request->hasFile('fun_foto')) {
                if ($funcionario->fun_foto) {
                    Storage::disk('public')->delete($funcionario->fun_foto);
                }
                $data['fun_foto'] = $request->file('fun_foto')->store('funcionarios', 'public');
            }

            $funcionario->update($data);
        });

        return to_route('funcionarios.index')->with('success', 'Funcionário atualizado com sucesso.');
    }

    public function destroy(Funcionario $funcionario): RedirectResponse
    {
        if ($funcionario->fun_foto) {
            Storage::disk('public')->delete($funcionario->fun_foto);
        }
        $funcionario->delete();

        return to_route('funcionarios.index')->with('success', 'Funcionário removido com sucesso.');
    }
}
