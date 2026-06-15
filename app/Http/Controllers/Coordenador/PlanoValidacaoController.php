<?php

namespace App\Http\Controllers\Coordenador;

use App\Http\Controllers\Controller;
use App\Http\Requests\Coordenador\UpdateStatusPlanoRequest;
use App\Models\Diario\DiarioPlanoAula;
use App\Models\Parametro\AnoLetivo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Mpdf\Mpdf;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PlanoValidacaoController extends Controller
{
    public function export(Request $request): StreamedResponse|HttpResponse
    {
        $this->abortIfNotCoordenador();

        $funId = (int) $request->user()->fun_id;
        $anlId = (int) $request->input('anl_id');
        $escId = (int) $request->input('esc_id');
        $segId = (int) $request->input('seg_id');
        $serId = (int) $request->input('ser_id');
        $turId = (int) $request->input('tur_id');
        $disId = (int) $request->input('dis_id');
        $status = (string) $request->input('status', '');
        $search = (string) $request->input('search', '');

        $planos = collect();

        if ($anlId && $escId && $this->coordenadorLotadoNaEscola($funId, $escId)) {
            $planos = DiarioPlanoAula::query()
                ->with([
                    'turma:tur_id,tur_nome,tur_ser_id',
                    'turma.serie:ser_id,ser_nome',
                    'disciplina:dis_id,dis_nome',
                    'unidade:uni_id,uni_numero,uni_tipo',
                    'funcionario:fun_id,fun_nome',
                    'escola:esc_id,esc_nome',
                ])
                ->whereHas('turma', function ($q) use ($anlId, $escId, $segId, $serId, $turId) {
                    $q->where('tur_esc_id', $escId)
                        ->where('tur_anl_id', $anlId)
                        ->where('tur_modalidade', 'REGULAR');
                    if ($turId) {
                        $q->where('tur_id', $turId);
                    } elseif ($serId) {
                        $q->where('tur_ser_id', $serId);
                    } elseif ($segId) {
                        $q->whereHas('serie', fn ($qq) => $qq->where('seg_id', $segId));
                    }
                })
                ->when($disId, fn ($q) => $q->where('dpa_dis_id', $disId))
                ->when($status, fn ($q) => $q->where('dpa_status', $status))
                ->when($search, function ($q) use ($search) {
                    $q->where(function ($qq) use ($search) {
                        $qq->where('dpa_tema', 'ilike', "%{$search}%")
                            ->orWhereHas('funcionario', fn ($qf) => $qf->where('fun_nome', 'ilike', "%{$search}%"));
                    });
                })
                ->orderByDesc('dpa_dt_inicio')
                ->get();
        }

        if ($request->input('format') === 'pdf') {
            return $this->exportPdf($planos, $request);
        }

        return $this->exportCsv($planos);
    }

    private function exportCsv($planos): StreamedResponse
    {
        $filename = 'validacao_planos_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($planos) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($out, ['Status', 'Professor', 'Escola', 'Turma', 'Série', 'Disciplina', 'Unidade', 'Tema', 'Data Inicial', 'Data Final'], ';');
            foreach ($planos as $p) {
                fputcsv($out, [
                    ucfirst($p->dpa_status ?? ''),
                    $p->funcionario?->fun_nome ?? '',
                    $p->escola?->esc_nome ?? '',
                    $p->turma?->tur_nome ?? '',
                    $p->turma?->serie?->ser_nome ?? '',
                    $p->disciplina?->dis_nome ?? '',
                    $p->unidade ? ($p->unidade->uni_numero . 'º ' . $p->unidade->uni_tipo) : '',
                    $p->dpa_tema,
                    optional($p->dpa_dt_inicio)?->format('d/m/Y') ?? '',
                    optional($p->dpa_dt_fim)?->format('d/m/Y') ?? '',
                ], ';');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function exportPdf($planos, Request $request): HttpResponse
    {
        $collection = collect($planos);
        $filename = 'validacao_planos_' . now()->format('Ymd_His') . '.pdf';

        $statusCounts = $collection->groupBy('dpa_status')->map->count()->all();

        $filtroParts = [];
        if ($request->input('search')) $filtroParts[] = 'Busca: "' . $request->input('search') . '"';
        if ($request->input('status')) $filtroParts[] = 'Status: ' . ucfirst($request->input('status'));

        $html = view('exports.planos_lista_pdf', [
            'titulo'          => 'Validação de Planos de Aula',
            'planos'          => $collection,
            'total'           => $collection->count(),
            'statusCounts'    => $statusCounts,
            'mostraProfessor' => true,
            'filtroDescricao' => implode(' · ', $filtroParts),
        ])->render();

        $mpdf = new Mpdf(['orientation' => 'L', 'format' => 'A4', 'margin_top' => 8, 'margin_bottom' => 8, 'margin_left' => 10, 'margin_right' => 10, 'tempDir' => sys_get_temp_dir()]);
        $mpdf->WriteHTML($html);

        return response($mpdf->Output($filename, 'S'), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function index(Request $request): Response
    {
        $this->abortIfNotCoordenador();

        $funId = (int) $request->user()->fun_id;

        $perPage = in_array((int) $request->input('per_page'), [10, 25, 50])
            ? (int) $request->input('per_page')
            : 10;

        $anoVigente = AnoLetivo::query()
            ->where('anl_fl_em_exercicio', true)
            ->whereNull('anl_deleted_at')
            ->orderByDesc('anl_ano')
            ->first();

        $anlId = (int) ($request->input('anl_id') ?: $anoVigente?->anl_id);
        $escId = (int) $request->input('esc_id');
        $segId = (int) $request->input('seg_id');
        $serId = (int) $request->input('ser_id');
        $turId = (int) $request->input('tur_id');
        $disId = (int) $request->input('dis_id');
        $status = (string) $request->input('status', '');
        $search = (string) $request->input('search', '');

        $planos = collect();
        $paginator = null;

        if ($anlId && $escId) {
            // Garantir que coordenador tem lotação na escola
            $temLotacao = $this->coordenadorLotadoNaEscola($funId, $escId);

            if ($temLotacao) {
                $query = DiarioPlanoAula::query()
                    ->with([
                        'turma:tur_id,tur_nome,tur_ser_id,tur_esc_id,tur_anl_id',
                        'turma.serie:ser_id,seg_id,ser_nome',
                        'turma.serie.segmento:seg_id,seg_nome_reduzido,seg_nome_completo',
                        'disciplina:dis_id,dis_nome',
                        'unidade:uni_id,uni_numero,uni_tipo',
                        'funcionario:fun_id,fun_nome',
                        'escola:esc_id,esc_nome',
                    ])
                    ->whereHas('turma', function ($q) use ($anlId, $escId, $segId, $serId, $turId) {
                        $q->where('tur_esc_id', $escId)
                            ->where('tur_anl_id', $anlId)
                            ->where('tur_modalidade', 'REGULAR');
                        if ($turId) {
                            $q->where('tur_id', $turId);
                        } elseif ($serId) {
                            $q->where('tur_ser_id', $serId);
                        } elseif ($segId) {
                            $q->whereHas('serie', fn ($qq) => $qq->where('seg_id', $segId));
                        }
                    })
                    ->when($disId, fn ($q) => $q->where('dpa_dis_id', $disId))
                    ->when($status, fn ($q) => $q->where('dpa_status', $status))
                    ->when($search, function ($q) use ($search) {
                        $q->where(function ($qq) use ($search) {
                            $qq->where('dpa_tema', 'ilike', "%{$search}%")
                                ->orWhereHas('funcionario', fn ($qf) => $qf->where('fun_nome', 'ilike', "%{$search}%"));
                        });
                    })
                    ->orderBy('dpa_status') // pendente vem primeiro alfabeticamente? Não - usa ordem custom
                    ->orderByDesc('dpa_dt_inicio');

                $paginator = $query->paginate($perPage)->withQueryString();
            }
        }

        return Inertia::render('coordenador/planos/Index', [
            'planos'  => $paginator ?? [
                'data' => [], 'current_page' => 1, 'last_page' => 1, 'from' => 0, 'to' => 0, 'total' => 0, 'links' => [],
            ],
            'filters' => [
                'anl_id'   => $anlId,
                'esc_id'   => $escId ?: '',
                'seg_id'   => $segId ?: '',
                'ser_id'   => $serId ?: '',
                'tur_id'   => $turId ?: '',
                'dis_id'   => $disId ?: '',
                'status'   => $status,
                'search'   => $search,
                'per_page' => $perPage,
            ],
            'anoVigenteId' => $anoVigente?->anl_id,
            'statuses' => DiarioPlanoAula::STATUSES,
        ]);
    }

    public function update(UpdateStatusPlanoRequest $request, DiarioPlanoAula $plano): RedirectResponse
    {
        $data = $request->validated();
        $data['dpa_validado_por_user_id'] = $request->user()->id;
        $data['dpa_validado_em'] = now();
        $plano->update($data);

        return back()->with('success', 'Plano revisado com sucesso.');
    }

    public function pdf(DiarioPlanoAula $plano, Request $request): HttpResponse
    {
        $this->abortIfNotCoordenador();
        $this->abortIfNotLotadoNaEscolaDoPlano($plano, $request);

        $plano->load([
            'funcionario:fun_id,fun_nome',
            'escola:esc_id,esc_nome',
            'anoLetivo:anl_id,anl_ano',
            'turma:tur_id,tur_nome,tur_ser_id',
            'turma.serie:ser_id,ser_nome',
            'disciplina:dis_id,dis_nome',
            'unidade:uni_id,uni_numero,uni_tipo',
        ]);

        $indicadores = DB::table('edu_diario_plano_indicador as p')
            ->join('edu_serie_indicador as i', 'i.ind_id', '=', 'p.dpi_ind_id')
            ->where('p.dpi_dpa_id', $plano->dpa_id)
            ->orderBy('i.ind_id')
            ->get(['i.ind_id', 'i.ind_descricao']);

        $entidade = DB::table('cfg_parametros_entidade')->value('par_nome_entidade');

        $html = view('exports.diario_plano_aula_pdf', [
            'plano'       => $plano,
            'indicadores' => $indicadores,
            'entidade'    => $entidade,
        ])->render();

        $filename = "plano_aula_{$plano->dpa_id}_" . now()->format('Ymd_His') . '.pdf';

        $mpdf = new Mpdf([
            'format'        => 'A4',
            'margin_top'    => 4,
            'margin_bottom' => 8,
            'margin_left'   => 10,
            'margin_right'  => 10,
            'tempDir'       => sys_get_temp_dir(),
        ]);
        $mpdf->WriteHTML($html);

        return response($mpdf->Output($filename, 'S'), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"{$filename}\"",
        ]);
    }

    public function show(DiarioPlanoAula $plano, Request $request): JsonResponse
    {
        $this->abortIfNotCoordenador();
        $this->abortIfNotLotadoNaEscolaDoPlano($plano, $request);

        $plano->load([
            'turma:tur_id,tur_nome,tur_ser_id',
            'turma.serie:ser_id,seg_id,ser_nome',
            'turma.serie.segmento:seg_id,seg_nome_reduzido',
            'disciplina:dis_id,dis_nome',
            'unidade:uni_id,uni_numero,uni_tipo',
            'funcionario:fun_id,fun_nome',
            'escola:esc_id,esc_nome',
            'indicadores',
            'validadoPor:id,name',
        ]);

        $indicadoresDescricao = DB::table('edu_diario_plano_indicador as p')
            ->join('edu_serie_indicador as i', 'i.ind_id', '=', 'p.dpi_ind_id')
            ->where('p.dpi_dpa_id', $plano->dpa_id)
            ->orderBy('i.ind_id')
            ->get(['i.ind_id', 'i.ind_descricao']);

        return response()->json([
            'plano'       => $plano,
            'indicadores' => $indicadoresDescricao,
        ]);
    }

    // ============ Lookups ============

    public function lookupAnos(Request $request): JsonResponse
    {
        $this->abortIfNotCoordenador();

        return response()->json(
            AnoLetivo::query()
                ->whereNull('anl_deleted_at')
                ->orderByDesc('anl_ano')
                ->get(['anl_id', 'anl_ano', 'anl_fl_em_exercicio'])
        );
    }

    public function lookupEscolas(Request $request): JsonResponse
    {
        $this->abortIfNotCoordenador();

        $funId = (int) $request->user()->fun_id;
        $anlId = (int) $request->input('anl_id');

        $ano = AnoLetivo::find($anlId);
        if (! $ano) {
            return response()->json([]);
        }

        $q = DB::table('edu_funcionario_lotacao as l')
            ->join('edu_funcionario_admissao as a', 'a.adm_id', '=', 'l.lot_adm_id')
            ->join('edu_escola as e', 'e.esc_id', '=', 'l.lot_esc_id')
            ->where('a.adm_fun_id', $funId)
            ->whereNull('a.adm_deleted_at')
            ->where('l.lot_fl_ativo', true)
            ->where(function ($qq) use ($ano) {
                $qq->whereNull('l.lot_dt_fim')
                    ->orWhere('l.lot_dt_fim', '>=', $ano->anl_dt_inicio_ano);
            });

        if ($ano->anl_dt_fim) {
            $q->where('l.lot_dt_inicio', '<=', $ano->anl_dt_fim);
        }

        return response()->json(
            $q->select('e.esc_id', 'e.esc_nome')
                ->distinct()
                ->orderBy('e.esc_nome')
                ->get()
        );
    }

    public function lookupSegmentos(Request $request): JsonResponse
    {
        $this->abortIfNotCoordenador();

        $escId = (int) $request->input('esc_id');
        $anlId = (int) $request->input('anl_id');

        if (! $escId) {
            return response()->json([]);
        }

        return response()->json(
            DB::table('edu_escola_segmento as es')
                ->join('edu_segmento as s', 's.seg_id', '=', 'es.seg_id')
                ->where('es.esc_id', $escId)
                ->where('es.esg_fl_ativo', true)
                ->whereNull('es.esg_deleted_at')
                ->whereNull('s.seg_deleted_at')
                ->when($anlId, function ($q) use ($anlId) {
                    $q->where(function ($qq) use ($anlId) {
                        $qq->whereNull('es.anl_id_inicio')->orWhere('es.anl_id_inicio', '<=', $anlId);
                    })->where(function ($qq) use ($anlId) {
                        $qq->whereNull('es.anl_id_fim')->orWhere('es.anl_id_fim', '>=', $anlId);
                    });
                })
                ->select('s.seg_id', 's.seg_nome_reduzido', 's.seg_nome_completo')
                ->distinct()
                ->orderBy('s.seg_nome_reduzido')
                ->get()
        );
    }

    public function lookupSeries(Request $request): JsonResponse
    {
        $this->abortIfNotCoordenador();

        $segId = (int) $request->input('seg_id');
        $escId = (int) $request->input('esc_id');
        $anlId = (int) $request->input('anl_id');

        if (! $segId || ! $escId || ! $anlId) {
            return response()->json([]);
        }

        return response()->json(
            DB::table('edu_serie as s')
                ->join('edu_turma as t', 't.tur_ser_id', '=', 's.ser_id')
                ->where('s.seg_id', $segId)
                ->whereNull('s.ser_deleted_at')
                ->where('s.ser_fl_ativo', true)
                ->where('t.tur_esc_id', $escId)
                ->where('t.tur_anl_id', $anlId)
                ->where('t.tur_modalidade', 'REGULAR')
                ->whereNull('t.tur_deleted_at')
                ->groupBy('s.ser_id', 's.ser_nome', 's.seg_id', 's.ser_nr_ordenacao')
                ->orderBy('s.ser_nr_ordenacao')
                ->select('s.ser_id', 's.ser_nome', 's.seg_id')
                ->get()
        );
    }

    public function lookupTurmas(Request $request): JsonResponse
    {
        $this->abortIfNotCoordenador();

        $escId = (int) $request->input('esc_id');
        $anlId = (int) $request->input('anl_id');
        $serId = (int) $request->input('ser_id');

        if (! $escId || ! $anlId || ! $serId) {
            return response()->json([]);
        }

        return response()->json(
            DB::table('edu_turma')
                ->where('tur_esc_id', $escId)
                ->where('tur_anl_id', $anlId)
                ->where('tur_ser_id', $serId)
                ->where('tur_modalidade', 'REGULAR')
                ->whereNull('tur_deleted_at')
                ->orderBy('tur_nome')
                ->select('tur_id', 'tur_nome')
                ->get()
        );
    }

    // ============ Helpers ============

    private function abortIfNotCoordenador(): void
    {
        $user = request()->user();
        abort_unless($user, 403, 'Acesso restrito a coordenadores pedagógicos.');
        if ($user->isAdmin()) {
            return;
        }
        abort_unless($user->hasRole('coordenador') && $user->fun_id, 403, 'Acesso restrito a coordenadores pedagógicos.');
    }

    private function coordenadorLotadoNaEscola(int $funId, int $escId): bool
    {
        return DB::table('edu_funcionario_lotacao as l')
            ->join('edu_funcionario_admissao as a', 'a.adm_id', '=', 'l.lot_adm_id')
            ->where('a.adm_fun_id', $funId)
            ->whereNull('a.adm_deleted_at')
            ->where('l.lot_esc_id', $escId)
            ->where('l.lot_fl_ativo', true)
            ->exists();
    }

    private function abortIfNotLotadoNaEscolaDoPlano(DiarioPlanoAula $plano, Request $request): void
    {
        $funId = (int) $request->user()->fun_id;
        $escId = (int) DB::table('edu_turma')->where('tur_id', $plano->dpa_tur_id)->value('tur_esc_id');
        abort_unless($this->coordenadorLotadoNaEscola($funId, $escId), 403);
    }
}
