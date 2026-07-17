<?php

namespace App\Http\Controllers\Aluno;

use App\Http\Controllers\Controller;
use App\Models\Aluno\Aluno;
use App\Services\Aluno\HistoricoEscolar;
use Illuminate\Http\Response as HttpResponse;
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

    /** Gera o PDF do histórico/certificado. A emissora é a escola do último ano do aluno. */
    public function pdf(Aluno $aluno, HistoricoEscolar $service): HttpResponse
    {
        $this->autorizar();

        $dados = $service->gerar((int) $aluno->aln_id);

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
