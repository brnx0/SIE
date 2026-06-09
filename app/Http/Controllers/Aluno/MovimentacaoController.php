<?php

namespace App\Http\Controllers\Aluno;

use App\Http\Controllers\Controller;
use App\Models\Aluno\AlunoMovimentacao;
use App\Models\Aluno\TipoMovimentacao;
use App\Models\Escola\Escola;
use App\Models\Parametro\AnoLetivo;
use App\Services\Aluno\AplicaMovimentacao;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MovimentacaoController extends Controller
{
    public function index(Request $request): Response
    {
        $perPage = (int) $request->input('per_page', 25);
        $search  = trim((string) $request->input('search', ''));
        $tipoCod = $request->input('tipo');

        $query = AlunoMovimentacao::with([
            'aluno:aln_id,aln_nome,aln_cpf',
            'tipo:tmv_cod,tmv_descricao',
            'matriculaOrigem.turma:tur_id,tur_nome,tur_esc_id',
            'matriculaOrigem.turma.escola:esc_id,esc_nome',
            'matriculaDestino.turma:tur_id,tur_nome,tur_esc_id',
            'matriculaDestino.turma.escola:esc_id,esc_nome',
            'user:id,name',
        ])
            ->when($search !== '', fn ($q) => $q->whereHas('aluno', fn ($a) =>
                $a->where('aln_nome', 'ilike', "%{$search}%")
                  ->orWhere('aln_cpf', 'like', "%{$search}%")
            ))
            ->when($tipoCod, fn ($q) => $q->where('mva_tmv_cod', $tipoCod))
            ->orderByDesc('mva_dt_movimentacao')
            ->orderByDesc('mva_id');

        return Inertia::render('movimentacoes/Index', [
            'movimentacoes' => $query->paginate($perPage)->withQueryString(),
            'filters'       => ['search' => $search, 'tipo' => $tipoCod, 'per_page' => $perPage],
            'tipos'         => TipoMovimentacao::where('tmv_fl_ativo', true)
                ->orderBy('tmv_descricao')
                ->get(['tmv_cod', 'tmv_descricao', 'tmv_tas_cod_entrada', 'tmv_tas_cod_saida']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('movimentacoes/Create', [
            'tipos' => TipoMovimentacao::where('tmv_fl_ativo', true)
                ->orderBy('tmv_descricao')
                ->get(['tmv_cod', 'tmv_descricao', 'tmv_tas_cod_entrada', 'tmv_tas_cod_saida']),
            'anosLetivos' => AnoLetivo::orderByDesc('anl_ano')->get(['anl_id', 'anl_ano']),
            'escolas'     => Escola::where('esc_fl_ativo', true)->orderBy('esc_nome')->get(['esc_id', 'esc_nome']),
        ]);
    }

    public function store(Request $request, AplicaMovimentacao $service): RedirectResponse
    {
        $tipo = TipoMovimentacao::where('tmv_fl_ativo', true)
            ->findOrFail($request->input('tmv_cod'));

        $rules = [
            'tmv_cod'         => ['required', 'integer', 'exists:edu_tipo_movimentacao,tmv_cod'],
            'tma_id_origem'   => ['required', 'integer', 'exists:edu_turma_aluno,tma_id'],
            'dt_movimentacao' => ['required', 'date'],
            'protocolo'       => ['nullable', 'string', 'max:50'],
            'observacao'      => ['nullable', 'string', 'max:1000'],
        ];

        if ($tipo->exigeDestino()) {
            $rules['tur_id_destino'] = ['required', 'integer', 'exists:edu_turma,tur_id'];
        }

        $data = $request->validate($rules, [], [
            'tmv_cod'         => 'tipo de movimentação',
            'tma_id_origem'   => 'matrícula de origem',
            'tur_id_destino'  => 'turma de destino',
            'dt_movimentacao' => 'data da movimentação',
            'protocolo'       => 'protocolo nº',
            'observacao'      => 'observação',
        ]);

        $service->executar($data);

        return to_route('movimentacoes.index')->with('success', 'Movimentação registrada com sucesso.');
    }

    public function show(AlunoMovimentacao $movimentacao): Response
    {
        $movimentacao->load([
            'aluno',
            'tipo',
            'matriculaOrigem.turma.escola',
            'matriculaOrigem.turma.segmento',
            'matriculaOrigem.turma.serie',
            'matriculaDestino.turma.escola',
            'matriculaDestino.turma.segmento',
            'matriculaDestino.turma.serie',
            'user',
        ]);

        return Inertia::render('movimentacoes/Show', [
            'movimentacao' => $movimentacao,
        ]);
    }
}
