<?php

namespace App\Http\Controllers\Funcionario;

use App\Http\Controllers\Controller;
use App\Http\Requests\Funcionario\StoreFuncionarioRequest;
use App\Http\Requests\Funcionario\UpdateFuncionarioRequest;
use App\Models\Funcionario\Funcionario;
use App\Models\Parametro\ParametroEntidade;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
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
        $this->checkHomonimo($request);

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
            'admissoes.cargo:crg_id,crg_nome',
            'admissoes.lotacoes.escola:esc_id,esc_nome',
            'admissoes.lotacoes.cargo:crg_id,crg_nome',
        ]);

        return Inertia::render('funcionarios/Edit', [
            'funcionario' => $funcionario,
        ]);
    }

    public function update(UpdateFuncionarioRequest $request, Funcionario $funcionario): RedirectResponse
    {
        $this->checkHomonimo($request, $funcionario->fun_id);

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

    /**
     * Verifica possíveis homônimos (mesmo nome + data de nascimento).
     * Lança ValidationException com chave 'homonimo' (JSON com matches).
     * Bypass via campo 'confirm_homonimo'.
     */
    protected function checkHomonimo(Request $request, ?int $excludeId = null): void
    {
        $params = ParametroEntidade::firstOrFail();

        if (! $params->par_fl_alertar_homonimos) {
            return;
        }

        if ($request->boolean('confirm_homonimo')) {
            return;
        }

        $nome = $request->input('fun_nome');
        $dtNasc = $request->input('fun_dt_nascimento');

        if (! $nome || ! $dtNasc) {
            return;
        }

        $matches = Funcionario::query()
            ->whereRaw('unaccent(upper(fun_nome)) = unaccent(upper(?))', [$nome])
            ->whereDate('fun_dt_nascimento', $dtNasc)
            ->when($excludeId, fn ($q) => $q->where('fun_id', '!=', $excludeId))
            ->orderBy('fun_id')
            ->limit(20)
            ->get(['fun_id', 'fun_nome', 'fun_dt_nascimento', 'fun_cpf']);

        if ($matches->isEmpty()) {
            return;
        }

        $payload = $matches->map(fn ($f) => [
            'fun_id'             => $f->fun_id,
            'fun_nome'           => $f->fun_nome,
            'fun_dt_nascimento'  => $f->fun_dt_nascimento?->format('Y-m-d'),
            'fun_cpf'            => $f->fun_cpf,
        ])->all();

        throw ValidationException::withMessages([
            'homonimo' => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]);
    }

    public function destroy(Funcionario $funcionario): RedirectResponse
    {
        if ($funcionario->fun_foto) {
            Storage::disk('public')->delete($funcionario->fun_foto);
        }

        return $this->safeDelete($funcionario)
            ?? to_route('funcionarios.index')->with('success', 'Funcionário removido com sucesso.');
    }
}
