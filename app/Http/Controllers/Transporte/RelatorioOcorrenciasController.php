<?php

namespace App\Http\Controllers\Transporte;

use App\Http\Controllers\Controller;
use App\Models\Transporte\Ocorrencia;
use App\Models\Transporte\Rota;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Mpdf\Mpdf;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RelatorioOcorrenciasController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('transporte/relatorios/Ocorrencias', array_merge($this->montar($request), [
            'tipos' => Ocorrencia::TIPOS,
            'rotas' => Rota::orderBy('rot_nome')->get(['rot_id', 'rot_nome']),
        ]));
    }

    public function export(Request $request): StreamedResponse|HttpResponse
    {
        $dados = $this->montar($request);

        return $request->input('format') === 'csv'
            ? $this->csv($dados['ocorrencias'])
            : $this->pdf($dados);
    }

    private function montar(Request $request): array
    {
        $dtInicio = $request->date('dt_inicio');
        $dtFim    = $request->date('dt_fim');
        $rotId    = $request->integer('rot_id');
        $tipo     = $request->input('tipo');

        $ocorrencias = Ocorrencia::query()
            ->with([
                'diario:tdb_id,tdb_dt_viagem,tdb_rot_id,tdb_vei_id,tdb_fun_id_motorista',
                'diario.rota:rot_id,rot_nome',
                'diario.veiculo:vei_id,vei_placa',
                'diario.motorista:fun_id,fun_nome',
            ])
            ->when($tipo, fn ($q, $v) => $q->where('toc_tipo', $v))
            ->whereHas('diario', function ($q) use ($dtInicio, $dtFim, $rotId) {
                $q->when($dtInicio, fn ($q, $v) => $q->whereDate('tdb_dt_viagem', '>=', $v))
                  ->when($dtFim, fn ($q, $v) => $q->whereDate('tdb_dt_viagem', '<=', $v))
                  ->when($rotId, fn ($q, $v) => $q->where('tdb_rot_id', $v));
            })
            ->get()
            ->sortByDesc(fn ($o) => (string) ($o->diario?->tdb_dt_viagem ?? '') . str_pad((string) $o->toc_id, 10, '0', STR_PAD_LEFT))
            ->values()
            ->map(fn ($o) => [
                'toc_id'         => $o->toc_id,
                'tdb_id'         => $o->toc_tdb_id,
                'toc_tipo'       => $o->toc_tipo,
                'toc_tipo_label' => Ocorrencia::TIPOS[$o->toc_tipo] ?? $o->toc_tipo,
                'toc_hr'         => $o->toc_hr ? substr($o->toc_hr, 0, 5) : null,
                'toc_descricao'  => $o->toc_descricao,
                'data'           => $this->iso($o->diario?->tdb_dt_viagem),
                'rota'           => $o->diario?->rota?->rot_nome,
                'veiculo'        => $o->diario?->veiculo?->vei_placa,
                'motorista'      => $o->diario?->motorista?->fun_nome,
            ]);

        $porTipo = collect(Ocorrencia::TIPOS)
            ->map(fn ($label, $key) => ['tipo' => $key, 'label' => $label, 'total' => $ocorrencias->where('toc_tipo', $key)->count()])
            ->filter(fn ($r) => $r['total'] > 0)
            ->values();

        return [
            'ocorrencias' => $ocorrencias,
            'resumo'      => ['total' => $ocorrencias->count(), 'por_tipo' => $porTipo],
            'filtros'     => [
                'dt_inicio' => $dtInicio?->format('Y-m-d'),
                'dt_fim'    => $dtFim?->format('Y-m-d'),
                'rot_id'    => $rotId ?: null,
                'tipo'      => $tipo ?: null,
            ],
        ];
    }

    private function csv($ocorrencias): StreamedResponse
    {
        $filename = 'transporte_ocorrencias_' . now()->format('Ymd_His') . '.csv';
        $fmt      = fn ($d) => $d ? Carbon::parse($d)->format('d/m/Y') : '';

        return response()->streamDownload(function () use ($ocorrencias, $fmt) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($out, ['Data', 'Hora', 'Tipo', 'Rota', 'Veículo', 'Motorista', 'Descrição'], ';');
            foreach ($ocorrencias as $o) {
                fputcsv($out, [
                    $fmt($o['data']),
                    $o['toc_hr'] ?? '',
                    $o['toc_tipo_label'],
                    $o['rota'] ?? '',
                    $o['veiculo'] ?? '',
                    $o['motorista'] ?? '',
                    $o['toc_descricao'],
                ], ';');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function pdf(array $dados): HttpResponse
    {
        $filename = 'transporte_ocorrencias_' . now()->format('Ymd_His') . '.pdf';

        $html = view('exports.transporte_ocorrencias_pdf', $dados)->render();

        $mpdf = new Mpdf(['orientation' => 'L', 'format' => 'A4', 'margin_top' => 0, 'margin_bottom' => 0, 'margin_left' => 0, 'margin_right' => 0, 'tempDir' => sys_get_temp_dir()]);
        $mpdf->WriteHTML($html);

        return response($mpdf->Output($filename, 'S'), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    private function iso($d): ?string
    {
        return $d ? ($d instanceof \DateTimeInterface ? $d->format('Y-m-d') : (string) Carbon::parse($d)->format('Y-m-d')) : null;
    }
}
