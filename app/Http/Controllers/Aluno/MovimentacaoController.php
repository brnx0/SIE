<?php

namespace App\Http\Controllers\Aluno;

use App\Http\Controllers\Controller;
use App\Models\Aluno\AlunoMovimentacao;
use App\Models\Aluno\TipoMovimentacao;
use App\Models\Escola\Escola;
use App\Models\Parametro\AnoLetivo;
use App\Services\Aluno\AplicaMovimentacao;
use App\Services\Aluno\DesfazerMovimentacao;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;
use Mpdf\Mpdf;

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
        $request->validate([
            'tmv_cod' => ['required', 'integer', 'exists:edu_tipo_movimentacao,tmv_cod'],
        ], [], ['tmv_cod' => 'tipo de movimentação']);

        $tipo = TipoMovimentacao::where('tmv_fl_ativo', true)
            ->where('tmv_cod', $request->input('tmv_cod'))
            ->first();

        if (! $tipo) {
            return back()->withErrors(['tmv_cod' => 'Tipo de movimentação inválido ou inativo.'])->withInput();
        }

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
            'canceladaPor:id,name',
        ]);

        return Inertia::render('movimentacoes/Show', [
            'movimentacao' => $movimentacao,
        ]);
    }

    public function declaracaoTransferencia(AlunoMovimentacao $movimentacao): HttpResponse
    {
        if (! in_array($movimentacao->mva_tmv_cod, [3, 4], true)) {
            abort(404, 'Declaração de transferência só disponível para movimentações de transferência.');
        }

        $movimentacao->load([
            'aluno',
            'tipo',
            'matriculaOrigem.turma.escola',
            'matriculaOrigem.turma.segmento',
            'matriculaOrigem.turma.serie.promoSerie1',
            'matriculaOrigem.turma.anoLetivo',
        ]);

        $parametros = \App\Models\Parametro\ParametroEntidade::first();
        $turma      = $movimentacao->matriculaOrigem?->turma;

        $html = view('exports.declaracao_transferencia_pdf', [
            'parametros'   => $parametros,
            'movimentacao' => $movimentacao,
            'aluno'        => $movimentacao->aluno,
            'turma'        => $turma,
            'escola'       => $turma?->escola,
            'segmento'     => $turma?->segmento,
            'serie'        => $turma?->serie,
            'serieApto'    => $turma?->serie?->promoSerie1 ?? $turma?->serie,
            'anoLetivo'    => $turma?->anoLetivo,
        ])->render();

        $filename = 'declaracao_transferencia_' . $movimentacao->mva_id . '.pdf';
        $mpdf     = new Mpdf(['format' => 'A4', 'margin_top' => 18, 'margin_bottom' => 18, 'margin_left' => 20, 'margin_right' => 20, 'tempDir' => sys_get_temp_dir()]);
        $mpdf->WriteHTML($html);

        return response($mpdf->Output($filename, 'S'), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"{$filename}\"",
        ]);
    }

    public function desfazer(Request $request, AlunoMovimentacao $movimentacao, DesfazerMovimentacao $service): RedirectResponse
    {
        $data = $request->validate([
            'motivo' => ['nullable', 'string', 'max:500'],
        ], [], ['motivo' => 'motivo']);

        $service->executar($movimentacao, $data['motivo'] ?? null);

        return back()->with('success', 'Movimentação desfeita com sucesso.');
    }
}
