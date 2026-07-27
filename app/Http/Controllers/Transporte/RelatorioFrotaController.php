<?php

namespace App\Http\Controllers\Transporte;

use App\Http\Controllers\Controller;
use App\Models\Transporte\Veiculo;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Mpdf\Mpdf;

class RelatorioFrotaController extends Controller
{
    /** Prioridade de exibição/ordenação por gravidade do status. */
    private const ORDEM = ['vencido' => 0, 'a_vencer' => 1, 'sem_data' => 2, 'em_dia' => 3];

    public function index(Request $request): Response
    {
        return Inertia::render('transporte/relatorios/Frota', $this->montar($request));
    }

    public function export(Request $request): HttpResponse
    {
        $dados = $this->montar($request);
        $filename = 'transporte_situacao_frota_' . now()->format('Ymd_His') . '.pdf';

        $html = view('exports.transporte_situacao_frota_pdf', $dados)->render();

        $mpdf = new Mpdf(['orientation' => 'L', 'format' => 'A4', 'margin_top' => 0, 'margin_bottom' => 0, 'margin_left' => 0, 'margin_right' => 0, 'tempDir' => sys_get_temp_dir()]);
        $mpdf->WriteHTML($html);

        return response($mpdf->Output($filename, 'S'), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    private function montar(Request $request): array
    {
        $dias = (int) ($request->integer('dias') ?: 30);
        $dias = max(1, min($dias, 365));
        $incluirInativos = $request->boolean('incluir_inativos');

        $hoje   = Carbon::today();
        $limite = $hoje->copy()->addDays($dias);

        $status = function ($d) use ($hoje, $limite) {
            if (! $d) return 'sem_data';
            $dt = Carbon::parse($d);
            if ($dt->lt($hoje))   return 'vencido';
            if ($dt->lte($limite)) return 'a_vencer';
            return 'em_dia';
        };

        $veiculos = Veiculo::query()
            ->when(! $incluirInativos, fn ($q) => $q->where('vei_fl_ativo', true))
            ->orderBy('vei_placa')
            ->get()
            ->map(function ($v) use ($status) {
                $docs = [
                    'licenciamento' => ['data' => $this->iso($v->vei_dt_licenciamento), 'status' => $status($v->vei_dt_licenciamento)],
                    'seguro'        => ['data' => $this->iso($v->vei_dt_seguro),        'status' => $status($v->vei_dt_seguro)],
                    'vistoria'      => ['data' => $this->iso($v->vei_dt_vistoria),      'status' => $status($v->vei_dt_vistoria)],
                ];
                $pior = collect($docs)->pluck('status')->sortBy(fn ($s) => self::ORDEM[$s])->first();

                return [
                    'vei_id'       => $v->vei_id,
                    'vei_placa'    => $v->vei_placa,
                    'vei_tipo'     => Veiculo::TIPOS[$v->vei_tipo] ?? $v->vei_tipo,
                    'vei_fl_ativo' => (bool) $v->vei_fl_ativo,
                    'docs'         => $docs,
                    'situacao'     => $pior,
                ];
            })
            ->sortBy(fn ($v) => self::ORDEM[$v['situacao']])
            ->values();

        return [
            'veiculos' => $veiculos,
            'resumo'   => [
                'total'    => $veiculos->count(),
                'vencido'  => $veiculos->where('situacao', 'vencido')->count(),
                'a_vencer' => $veiculos->where('situacao', 'a_vencer')->count(),
                'em_dia'   => $veiculos->where('situacao', 'em_dia')->count(),
                'sem_data' => $veiculos->where('situacao', 'sem_data')->count(),
            ],
            'filtros'  => ['dias' => $dias, 'incluir_inativos' => $incluirInativos],
        ];
    }

    private function iso($d): ?string
    {
        return $d ? ($d instanceof \DateTimeInterface ? $d->format('Y-m-d') : (string) Carbon::parse($d)->format('Y-m-d')) : null;
    }
}
