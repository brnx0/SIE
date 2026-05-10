<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAlunoRequest;
use App\Http\Requests\UpdateAlunoRequest;
use App\Models\Aluno;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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
                    ->orWhere('aln_cpf', 'like', "%{$search}%");
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
        DB::transaction(function () use ($request) {
            $data = $request->safe()->except(['saude', 'aln_foto']);

            if ($request->hasFile('aln_foto')) {
                $data['aln_foto'] = $request->file('aln_foto')->store('alunos', 'public');
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
        $aluno->delete();

        return to_route('alunos.index')->with('success', 'Aluno removido com sucesso.');
    }
}
