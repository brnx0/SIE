<?php

namespace App\Http\Controllers\Aluno;

use App\Http\Controllers\Controller;
use App\Models\Aluno\Aluno;
use App\Models\Turma\Turma;
use App\Services\Aluno\HistoricoEscolar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\DB;
use Mpdf\Mpdf;

class HistoricoEscolarController extends Controller
{
    /** Perfis autorizados a emitir o histórico. */
    private const PERFIS = ['admin', 'secretaria_escola', 'diretor'];

    private function autorizar(): void
    {
        abort_unless(
            request()->user()?->hasAnyRole(self::PERFIS),
            403,
            'Perfil sem permissão para emitir histórico.'
        );
    }

    /** Tela do relatório (Relatórios → Secretaria): busca aluno e emite o PDF. */
    public function form(): \Inertia\Response
    {
        $this->autorizar();

        return \Inertia\Inertia::render('relatorios/HistoricoEscolar/Form');
    }

    /** Escolas em que o aluno teve ano APROVADO — opções de escola emissora. */
    public function escolas(Aluno $aluno): JsonResponse
    {
        $this->autorizar();

        $escolas = DB::table('edu_turma_aluno as tma')
            ->join('edu_turma as t', 't.tur_id', '=', 'tma.tma_tur_id')
            ->join('edu_escola as e', 'e.esc_id', '=', 't.tur_esc_id')
            ->where('tma.tma_aln_id', $aluno->aln_id)
            ->where('tma.tma_modalidade', Turma::MODALIDADE_REGULAR)
            ->whereIn('tma.tma_tas_cod_saida', HistoricoEscolar::APROVADO)
            ->whereNull('tma.tma_deleted_at')
            ->distinct()
            ->orderBy('e.esc_nome')
            ->get(['e.esc_id', 'e.esc_nome']);

        return response()->json($escolas);
    }

    /** Gera o PDF do histórico/certificado emitido por uma escola. */
    public function pdf(Request $request, Aluno $aluno, HistoricoEscolar $service): HttpResponse
    {
        $this->autorizar();

        $data = $request->validate([
            'esc_id' => ['required', 'integer', 'exists:edu_escola,esc_id'],
        ]);

        $dados = $service->gerar((int) $aluno->aln_id, (int) $data['esc_id']);

        $html = view('exports.historico_escolar_pdf', ['h' => $dados])->render();

        $mpdf = new Mpdf([
            'orientation'   => 'L',
            'format'        => 'A4',
            'margin_top'    => 8,
            'margin_bottom' => 8,
            'margin_left'   => 8,
            'margin_right'  => 8,
            'tempDir'       => sys_get_temp_dir(),
        ]);
        $mpdf->WriteHTML($html);

        $filename = 'historico_' . preg_replace('/\s+/', '_', mb_strtolower($aluno->aln_nome)) . '.pdf';

        return response($mpdf->Output($filename, 'S'), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"{$filename}\"",
        ]);
    }
}
