<?php

namespace App\Http\Controllers\Aluno;

use App\Http\Controllers\Controller;
use App\Http\Requests\Aluno\StoreAlunoRequest;
use App\Http\Requests\Aluno\UpdateAlunoRequest;
use App\Models\Aluno\Aluno;
use App\Models\Parametro\ParametroEntidade;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AlunoController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Aluno::query()
            ->with('municipioNascimento:mun_id,mun_nome,mun_uf')
            ->orderBy('aln_nome');

        if ($search = $request->string('search')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('aln_nome', 'ilike', "%{$search}%")
                    ->orWhere('aln_cpf', 'like', "%{$search}%")
                    ->orWhere('aln_nr_matricula', 'like', "%{$search}%");
            });
        }

        return Inertia::render('alunos/Index', [
            'alunos' => $query->paginate(15)->withQueryString(),
            'filters' => ['search' => $search],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('alunos/Create');
    }

    public function store(StoreAlunoRequest $request): RedirectResponse
    {
        $this->checkHomonimo($request);

        DB::transaction(function () use ($request) {
            $data = $request->safe()->except(['saude', 'aln_foto']);

            if ($request->hasFile('aln_foto')) {
                $data['aln_foto'] = $request->file('aln_foto')->store('alunos', 'public');
            }

            // Geração automática de matrícula (parametrizada).
            $params = ParametroEntidade::firstOrFail();
            if ($params->par_fl_gerar_matricula_auto && empty($data['aln_nr_matricula'])) {
                // Advisory lock — Postgres não permite FOR UPDATE com agregação.
                // Chave arbitrária estável p/ serializar geração de matrícula.
                DB::statement('SELECT pg_advisory_xact_lock(?)', [727301]);

                $maxMatricula = (int) DB::table('edu_aluno')->max('aln_nr_matricula');
                $data['aln_nr_matricula'] = $maxMatricula + 1;
            }

            $aluno = Aluno::create($data);
            $aluno->saude()->create($request->input('saude', []));
        });

        return to_route('alunos.index')->with('success', 'Aluno cadastrado com sucesso.');
    }

    public function edit(Aluno $aluno): Response
    {
        $aluno->load(['municipioNascimento:mun_id,mun_nome,mun_uf', 'saude']);

        return Inertia::render('alunos/Edit', [
            'aluno' => $aluno,
        ]);
    }

    public function update(UpdateAlunoRequest $request, Aluno $aluno): RedirectResponse
    {
        $this->checkHomonimo($request, $aluno->aln_id);

        DB::transaction(function () use ($request, $aluno) {
            $data = $request->safe()->except(['saude', 'aln_foto']);

            if ($request->hasFile('aln_foto')) {
                if ($aluno->aln_foto) {
                    Storage::disk('public')->delete($aluno->aln_foto);
                }
                $data['aln_foto'] = $request->file('aln_foto')->store('alunos', 'public');
            }

            $aluno->update($data);

            $saudeData = $request->input('saude', []);
            if ($aluno->saude) {
                $aluno->saude->update($saudeData);
            } else {
                $aluno->saude()->create($saudeData);
            }
        });

        return to_route('alunos.index')->with('success', 'Aluno atualizado com sucesso.');
    }

    public function destroy(Aluno $aluno): RedirectResponse
    {
        if ($aluno->aln_foto) {
            Storage::disk('public')->delete($aluno->aln_foto);
        }

        return $this->safeDelete($aluno)
            ?? to_route('alunos.index')->with('success', 'Aluno removido com sucesso.');
    }

    /**
     * Verifica possíveis homônimos (mesmo nome + data de nascimento).
     * Lança ValidationException com chave 'homonimo' (JSON com matches) p/ bloquear submit
     * e exibir dialog. Bypass via campo 'confirm_homonimo'.
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

        $nome = $request->input('aln_nome');
        $dtNasc = $request->input('aln_dt_nascimento');

        if (! $nome || ! $dtNasc) {
            return;
        }

        $matches = Aluno::query()
            ->whereRaw('unaccent(upper(aln_nome)) = unaccent(upper(?))', [$nome])
            ->whereDate('aln_dt_nascimento', $dtNasc)
            ->when($excludeId, fn ($q) => $q->where('aln_id', '!=', $excludeId))
            ->orderBy('aln_id')
            ->limit(20)
            ->get(['aln_id', 'aln_nome', 'aln_dt_nascimento', 'aln_cpf', 'aln_nr_matricula']);

        if ($matches->isEmpty()) {
            return;
        }

        $payload = $matches->map(fn ($a) => [
            'aln_id' => $a->aln_id,
            'aln_nome' => $a->aln_nome,
            'aln_dt_nascimento' => $a->aln_dt_nascimento?->format('Y-m-d'),
            'aln_cpf' => $a->aln_cpf,
            'aln_nr_matricula' => $a->aln_nr_matricula,
        ])->all();

        throw ValidationException::withMessages([
            'homonimo' => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]);
    }
}
