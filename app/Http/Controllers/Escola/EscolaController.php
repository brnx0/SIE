<?php

namespace App\Http\Controllers\Escola;

use App\Http\Controllers\Controller;
use App\Http\Requests\Escola\StoreEscolaRequest;
use App\Http\Requests\Escola\UpdateEscolaRequest;
use App\Models\Escola\CensoEscolar;
use App\Models\Escola\Escola;
use App\Models\Parametro\AnoLetivo;
use App\Models\Segmento\Segmento;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Mpdf\Mpdf;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EscolaController extends Controller
{
    private const SITUACAO = [1 => 'Em atividade', 2 => 'Paralisada', 3 => 'Extinta'];

    public function index(Request $request): Response
    {
        $perPage = in_array((int) $request->input('per_page'), [10, 25, 50])
            ? (int) $request->input('per_page')
            : 10;

        return Inertia::render('escolas/Index', [
            'escolas' => $this->baseQuery($request)->paginate($perPage)->withQueryString(),
            'filters' => [
                'search'   => $request->string('search')->toString(),
                'per_page' => $perPage,
            ],
        ]);
    }

    public function export(Request $request): StreamedResponse|HttpResponse
    {
        $escolas = $this->baseQuery($request)->get();

        if ($request->input('format') === 'pdf') {
            return $this->exportPdf($escolas, $request);
        }

        return $this->exportCsv($escolas);
    }

    public function create(): Response
    {
        return Inertia::render('escolas/Create');
    }

    public function store(StoreEscolaRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $data = $request->safe()->except(['esc_logo']);

            if ($request->hasFile('esc_logo')) {
                $data['esc_logo'] = $request->file('esc_logo')->store('escolas', 'public');
            }

            Escola::create($data);
        });

        return to_route('escolas.index')->with('success', 'Escola cadastrada com sucesso.');
    }

    public function edit(Escola $escola): Response
    {
        $escola->load([
            'municipio:mun_id,mun_nome,mun_uf',
            'bairro:bai_id,bai_nome,bai_mun_id',
            'gerencia:ger_id,ger_nome,ger_sigla,ger_uf',
        ]);

        $escolaSegmentos = $escola->segmentos()
            ->with([
                'segmento:seg_id,seg_nome_reduzido,seg_nome_completo',
                'anoLetivoInicio:anl_id,anl_ano,anl_fl_em_exercicio',
                'anoLetivoFim:anl_id,anl_ano,anl_fl_em_exercicio',
                'serieInicio:ser_id,ser_nome',
                'serieFim:ser_id,ser_nome',
            ])
            ->orderBy('anl_id_inicio', 'desc')
            ->get();

        $anoLetivoAtual = AnoLetivo::where('anl_fl_em_exercicio', true)
            ->first(['anl_id', 'anl_ano', 'anl_dt_censo', 'anl_fl_em_exercicio']);

        $censoHistorico = CensoEscolar::where('cen_esc_id', $escola->esc_id)
            ->with('anoLetivo:anl_id,anl_ano,anl_dt_censo')
            ->orderBy('cen_anl_id', 'desc')
            ->get(['cen_id', 'cen_anl_id', 'cen_created_at', 'cen_updated_at']);

        $censoAtual = $anoLetivoAtual
            ? CensoEscolar::where('cen_esc_id', $escola->esc_id)
                ->where('cen_anl_id', $anoLetivoAtual->anl_id)
                ->first(['cen_id', 'cen_anl_id'])
            : null;

        $censoPrevious = CensoEscolar::where('cen_esc_id', $escola->esc_id)
            ->when($anoLetivoAtual, fn ($q) => $q->where('cen_anl_id', '!=', $anoLetivoAtual->anl_id))
            ->orderBy('cen_anl_id', 'desc')
            ->exists();

        return Inertia::render('escolas/Edit', [
            'escola'           => $escola,
            'escolaSegmentos'  => $escolaSegmentos,
            'segmentos'        => Segmento::where('seg_fl_ativo', true)->orderBy('seg_ordem')->get(['seg_id', 'seg_nome_reduzido']),
            'anosLetivos'      => AnoLetivo::orderBy('anl_ano', 'desc')->get(['anl_id', 'anl_ano', 'anl_fl_em_exercicio']),
            'anoLetivoAtual'   => $anoLetivoAtual,
            'censoHistorico'   => $censoHistorico,
            'censoAtual'       => $censoAtual,
            'censoPreviousExists' => $censoPrevious,
        ]);
    }

    public function update(UpdateEscolaRequest $request, Escola $escola): RedirectResponse
    {
        DB::transaction(function () use ($request, $escola) {
            $data = $request->safe()->except(['esc_logo']);

            if ($request->hasFile('esc_logo')) {
                if ($escola->esc_logo) {
                    Storage::disk('public')->delete($escola->esc_logo);
                }
                $data['esc_logo'] = $request->file('esc_logo')->store('escolas', 'public');
            }

            $escola->update($data);
        });

        return to_route('escolas.index')->with('success', 'Escola atualizada com sucesso.');
    }

    public function destroy(Escola $escola): RedirectResponse
    {
        if ($escola->esc_logo) {
            Storage::disk('public')->delete($escola->esc_logo);
        }

        return $this->safeDelete($escola)
            ?? to_route('escolas.index')->with('success', 'Escola removida com sucesso.');
    }

    private function baseQuery(Request $request)
    {
        $search = $request->string('search')->toString();

        return Escola::query()
            ->with(['municipio:mun_id,mun_nome,mun_uf', 'bairro:bai_id,bai_nome', 'gerencia:ger_id,ger_nome,ger_sigla'])
            ->when($search, function ($q) use ($search) {
                $q->where(function ($q) use ($search) {
                    $q->where('esc_nome', 'ilike', "%{$search}%")
                        ->orWhere('esc_apelido', 'ilike', "%{$search}%")
                        ->orWhere('esc_cd_inep', 'like', "%{$search}%")
                        ->orWhere('esc_cnpj', 'like', "%{$search}%");
                });
            })
            ->orderBy('esc_nome');
    }

    private function exportCsv($escolas): StreamedResponse
    {
        $filename = 'escolas_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($escolas) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($out, ['Apelido', 'Nome', 'INEP', 'CNPJ', 'Município', 'UF', 'Situação'], ';');
            foreach ($escolas as $e) {
                $cnpj = $e->esc_cnpj
                    ? preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $e->esc_cnpj)
                    : '';
                fputcsv($out, [
                    $e->esc_apelido ?? $e->esc_nome,
                    $e->esc_nome,
                    $e->esc_cd_inep ?? '',
                    $cnpj,
                    $e->municipio?->mun_nome ?? '',
                    $e->municipio?->mun_uf ?? '',
                    self::SITUACAO[$e->esc_situacao_func] ?? '',
                ], ';');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function exportPdf($escolas, Request $request): HttpResponse
    {
        $collection = collect($escolas);
        $filename   = 'escolas_' . now()->format('Ymd_His') . '.pdf';

        $html = view('exports.escolas_pdf', [
            'escolas'           => $collection,
            'situacaoLabels'    => self::SITUACAO,
            'total'             => $collection->count(),
            'totalAtividade'    => $collection->where('esc_situacao_func', 1)->count(),
            'totalParalisadas'  => $collection->where('esc_situacao_func', 2)->count(),
            'totalExtintas'     => $collection->where('esc_situacao_func', 3)->count(),
            'search'            => $request->input('search', ''),
        ])->render();

        $mpdf = new Mpdf(['orientation' => 'L', 'format' => 'A4', 'margin_top' => 0, 'margin_bottom' => 0, 'margin_left' => 0, 'margin_right' => 0]);
        $mpdf->WriteHTML($html);

        return response($mpdf->Output($filename, 'S'), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
