<?php

namespace App\Http\Controllers\Matricula;

use App\Http\Controllers\Controller;
use App\Http\Requests\Matricula\StoreMatriculaRequest;
use App\Models\Aluno\Aluno;
use App\Models\Escola\Escola;
use App\Models\Matricula\Matricula;
use App\Models\Parametro\AnoLetivo;
use App\Models\Parametro\ParametroEntidade;
use App\Models\Turma\Turma;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\DB;
use Mpdf\Mpdf;
use Inertia\Inertia;
use Inertia\Response;

class MatriculaController extends Controller
{
    public function index(): Response
    {
        $anosLetivos = AnoLetivo::where('anl_fl_em_exercicio', true)
            ->orderByDesc('anl_ano')
            ->get(['anl_id', 'anl_ano', 'anl_dt_corte']);

        $escolas = Escola::where('esc_fl_ativo', true)
            ->orderBy('esc_nome')
            ->get(['esc_id', 'esc_nome', 'esc_cd_escola']);

        $parametros = ParametroEntidade::first([
            'par_fl_validar_idade_serie',
            'par_fl_gerar_matricula_auto',
            'par_fl_cpf_obrigatorio',
        ]);

        return Inertia::render('matriculas/Index', [
            'anosLetivos' => $anosLetivos,
            'escolas'     => $escolas,
            'parametros'  => $parametros,
        ]);
    }

    public function segundaVia(): Response
    {
        $anosLetivos = AnoLetivo::where('anl_fl_em_exercicio', true)
            ->orderByDesc('anl_ano')
            ->get(['anl_id', 'anl_ano']);

        return Inertia::render('matriculas/SegundaVia', [
            'anosLetivos' => $anosLetivos,
        ]);
    }

    public function buscarComprovante(Request $request): JsonResponse
    {
        $alnId = $request->integer('aln_id');
        $anlId = $request->integer('anl_id');

        if (!$alnId || !$anlId) {
            return response()->json(['message' => 'Informe o aluno e o ano letivo.'], 422);
        }

        $matricula = Matricula::where('tma_aln_id', $alnId)
            ->where('tma_anl_id', $anlId)
            ->where('tma_situacao', Matricula::SITUACAO_ATIVA)
            ->whereNull('tma_deleted_at')
            ->first(['tma_id']);

        if (!$matricula) {
            return response()->json(['message' => 'Aluno não possui matrícula ativa neste ano letivo.'], 404);
        }

        return response()->json(['tma_id' => $matricula->tma_id]);
    }

    public function comprovante(int $tmaId): HttpResponse
    {
        $matricula = Matricula::with([
            'aluno',
            'turma.escola',
            'turma.serie',
            'turma.anoLetivo',
        ])->findOrFail($tmaId);

        $matriculadoPor = \App\Models\User::find($matricula->tma_created_by_id)?->name ?? '—';
        $params = ParametroEntidade::current();

        // Logo: caminho absoluto para mPDF renderizar
        $logoPath = null;
        if ($params->par_logomarca) {
            $path = \Illuminate\Support\Facades\Storage::disk('public')->path($params->par_logomarca);
            if (file_exists($path)) {
                $logoPath = 'data:' . mime_content_type($path) . ';base64,' . base64_encode(file_get_contents($path));
            }
        }

        $html = view('exports.comprovante_matricula_pdf', [
            'matricula'      => $matricula,
            'matriculadoPor' => $matriculadoPor,
            'params'         => $params,
            'logoPath'       => $logoPath,
        ])->render();

        $mpdf = new Mpdf([
            'format'        => 'A4',
            'orientation'   => 'P',
            'margin_top'    => 0,
            'margin_bottom' => 0,
            'margin_left'   => 0,
            'margin_right'  => 0,
            'tempDir'       => sys_get_temp_dir(),
        ]);
        $mpdf->WriteHTML($html);

        $filename = 'comprovante_matricula_' . $tmaId . '.pdf';

        return response($mpdf->Output($filename, 'S'), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }

    public function verificar(Request $request): JsonResponse
    {
        $alunoId  = $request->integer('aln_id');
        $anlId    = $request->integer('anl_id');
        $escIdAtual = $request->integer('esc_id');

        if (!$alunoId || !$anlId) {
            return response()->json(['matriculado' => false]);
        }

        $matricula = Matricula::where('tma_aln_id', $alunoId)
            ->where('tma_anl_id', $anlId)
            ->where('tma_situacao', Matricula::SITUACAO_ATIVA)
            ->join('edu_turma', 'edu_turma.tur_id', '=', 'edu_turma_aluno.tma_tur_id')
            ->select('edu_turma.tur_esc_id')
            ->first();

        if (!$matricula) {
            return response()->json(['matriculado' => false]);
        }

        return response()->json([
            'matriculado'  => true,
            'mesma_escola' => (int) $matricula->tur_esc_id === $escIdAtual,
        ]);
    }

    public function store(StoreMatriculaRequest $request): JsonResponse
    {
        $request->checkDuplicataAluno();

        $turma = Turma::with(['anoLetivo:anl_id,anl_ano', 'segmento:seg_id,seg_nome_reduzido'])->findOrFail($request->integer('tma_tur_id'));

        // Verifica capacidade
        if ($turma->tur_capacidade !== null) {
            $ativos = Matricula::where('tma_tur_id', $turma->tur_id)
                ->where('tma_situacao', Matricula::SITUACAO_ATIVA)
                ->count();
            if ($ativos >= $turma->tur_capacidade) {
                return response()->json(['message' => 'Turma sem vagas disponíveis.'], 422);
            }
        }

        // Deriva indigena e creche automaticamente
        $segNome       = optional($turma->segmento)->seg_nome_reduzido ?? '';
        $flCreche      = stripos($segNome, 'CRECHE') !== false;

        try {
            $matricula = DB::transaction(function () use ($request, $turma, $flCreche) {
                $alunoId = $request->integer('tma_aln_id') ?: null;

                // Cria novo aluno se não informado
                if (!$alunoId) {
                    $alunoId = $this->criarAluno($request, $turma->anoLetivo);
                } else {
                    $this->atualizarAluno($alunoId, $request);
                }

                if ($request->boolean('possui_deficiencia')) {
                    $this->salvarSaude($alunoId, $request);
                }

                // Indígena derivado da raça do aluno (cor_raca = 5)
                $corRaca   = \App\Models\Aluno\Aluno::where('aln_id', $alunoId)->value('aln_cor_raca');
                $flIndigena = (int) $corRaca === 5;

                return Matricula::create([
                    'tma_aln_id'                   => $alunoId,
                    'tma_tur_id'                   => $turma->tur_id,
                    'tma_anl_id'                   => $turma->tur_anl_id,

                    'tma_situacao'                 => Matricula::SITUACAO_ATIVA,
                    'tma_tas_cod_entrada'          => Matricula::TAS_ENTRADA_NOVO,
                    'tma_created_by_id'            => auth()->id(),
                    'tma_dt_matricula'             => $request->input('tma_dt_matricula'),
                    'tma_obs'                      => $request->input('tma_obs'),
                    'tma_fl_trouxe_transferencia'  => $request->boolean('tma_fl_trouxe_transferencia'),
                    'tma_fl_trouxe_rg'             => $request->boolean('tma_fl_trouxe_rg'),
                    'tma_fl_trouxe_reg_nascimento' => $request->boolean('tma_fl_trouxe_reg_nascimento'),
                    'tma_fl_bolsa_familia'         => $request->boolean('tma_fl_bolsa_familia'),
                    'tma_fl_recebe_merenda'        => $request->boolean('tma_fl_recebe_merenda'),
                    'tma_fl_usa_transporte'        => $request->boolean('tma_fl_usa_transporte'),
                    'tma_fl_usa_biblioteca'        => $request->boolean('tma_fl_usa_biblioteca'),
                    'tma_fl_indigena'              => $flIndigena,
                    'tma_fl_creche'                => $flCreche,
                ]);
            });

            return response()->json([
                'message'   => 'Matrícula realizada com sucesso.',
                'tma_id'    => $matricula->tma_id,
            ]);
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            return response()->json(['message' => 'Este aluno já possui matrícula ativa neste ano letivo.'], 422);
        }
    }

    private function criarAluno(StoreMatriculaRequest $request, ?AnoLetivo $anoLetivo): int
    {
        $alunoData = $request->input('aluno', []);

        // Sempre gera número sequencial para aluno novo sem matrícula
        if (empty($alunoData['aln_nr_matricula'])) {
            DB::statement('SELECT pg_advisory_xact_lock(?)', [727301]);
            $max = (int) DB::table('edu_aluno')->max('aln_nr_matricula');
            $alunoData['aln_nr_matricula'] = $max + 1;
        }

        $alunoData['aln_fl_ativo'] = true;
        $aluno = Aluno::create($alunoData);

        $saude = ['als_fl_pcd' => $request->boolean('possui_deficiencia')];
        if ($request->boolean('possui_deficiencia')) {
            $saude = array_merge($saude, $request->input('saude', []));
        }
        $aluno->saude()->create($saude);

        return $aluno->aln_id;
    }

    private function atualizarAluno(int $alunoId, StoreMatriculaRequest $request): void
    {
        if (! $request->has('aluno')) {
            return;
        }

        Aluno::findOrFail($alunoId)->update($request->input('aluno', []));
    }

    private function salvarSaude(int $alunoId, StoreMatriculaRequest $request): void
    {
        $aluno = Aluno::findOrFail($alunoId);
        $saudeData = array_merge(
            $request->input('saude', []),
            ['als_fl_pcd' => true]
        );

        if ($aluno->saude) {
            $aluno->saude->update($saudeData);
        } else {
            $aluno->saude()->create($saudeData);
        }
    }
}
