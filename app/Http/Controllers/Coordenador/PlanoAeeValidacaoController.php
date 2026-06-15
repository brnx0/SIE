<?php

namespace App\Http\Controllers\Coordenador;

use App\Http\Controllers\Controller;
use App\Http\Requests\Coordenador\UpdateStatusPlanoAeeRequest;
use App\Models\Diario\DiarioPlanoAee;
use App\Models\Parametro\AnoLetivo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Mpdf\Mpdf;

class PlanoAeeValidacaoController extends Controller
{
    public function index(Request $request): Response
    {
        $this->abortIfNotCoordenadorInterno();

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
        $turId = (int) $request->input('tur_id');
        $status = (string) $request->input('status', '');
        $search = (string) $request->input('search', '');

        $paginator = null;

        if ($anlId && $escId) {
            {
                $query = DiarioPlanoAee::query()
                    ->with([
                        'turma:tur_id,tur_nome,tur_esc_id,tur_anl_id',
                        'funcionario:edu_funcionario.fun_id,edu_funcionario.fun_nome',
                        'escola:esc_id,esc_nome',
                    ])
                    ->whereHas('turma', function ($q) use ($anlId, $escId, $turId) {
                        $q->where('tur_esc_id', $escId)
                            ->where('tur_anl_id', $anlId)
                            ->where('tur_modalidade', 'AEE');
                        if ($turId) {
                            $q->where('tur_id', $turId);
                        }
                    })
                    ->when($status, fn ($q) => $q->where('dae_status', $status))
                    ->when($search, function ($q) use ($search) {
                        $q->where(function ($qq) use ($search) {
                            $qq->where('dae_tema', 'ilike', "%{$search}%")
                                ->orWhereHas('funcionario', fn ($qf) => $qf->where('fun_nome', 'ilike', "%{$search}%"));
                        });
                    })
                    ->orderByDesc('dae_dt_inicio');

                $paginator = $query->paginate($perPage)->withQueryString();
            }
        }

        return Inertia::render('coordenador-interno/planos-aee/Index', [
            'planos'  => $paginator ?? [
                'data' => [], 'current_page' => 1, 'last_page' => 1, 'from' => 0, 'to' => 0, 'total' => 0, 'links' => [],
            ],
            'filters' => [
                'anl_id'   => $anlId,
                'esc_id'   => $escId ?: '',
                'tur_id'   => $turId ?: '',
                'status'   => $status,
                'search'   => $search,
                'per_page' => $perPage,
            ],
            'anoVigenteId' => $anoVigente?->anl_id,
            'statuses' => DiarioPlanoAee::STATUSES,
        ]);
    }

    public function update(UpdateStatusPlanoAeeRequest $request, DiarioPlanoAee $plano): RedirectResponse
    {
        $data = $request->validated();
        $data['dae_validado_por_user_id'] = $request->user()->id;
        $data['dae_validado_em'] = now();
        $plano->update($data);
        return back()->with('success', 'Plano AEE revisado com sucesso.');
    }

    public function show(DiarioPlanoAee $plano, Request $request): JsonResponse
    {
        $this->abortIfNotCoordenadorInterno();

        $plano->load([
            'turma:tur_id,tur_nome',
            'funcionario:edu_funcionario.fun_id,edu_funcionario.fun_nome',
            'escola:esc_id,esc_nome',
            'anoLetivo:anl_id,anl_ano',
            'validadoPor:id,name',
        ]);

        return response()->json(['plano' => $plano]);
    }

    public function pdf(DiarioPlanoAee $plano, Request $request): HttpResponse
    {
        $this->abortIfNotCoordenadorInterno();

        $plano->load([
            'funcionario:edu_funcionario.fun_id,edu_funcionario.fun_nome',
            'escola:esc_id,esc_nome',
            'anoLetivo:anl_id,anl_ano',
            'turma:tur_id,tur_nome',
        ]);

        $entidade = DB::table('cfg_parametros_entidade')->value('par_nome_entidade');

        $html = view('exports.diario_plano_aee_pdf', [
            'plano'    => $plano,
            'entidade' => $entidade,
        ])->render();

        $filename = "plano_aee_{$plano->dae_id}_" . now()->format('Ymd_His') . '.pdf';

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

    // ============ Lookups ============

    public function lookupAnos(Request $request): JsonResponse
    {
        $this->abortIfNotCoordenadorInterno();

        return response()->json(
            AnoLetivo::query()
                ->whereNull('anl_deleted_at')
                ->orderByDesc('anl_ano')
                ->get(['anl_id', 'anl_ano', 'anl_fl_em_exercicio'])
        );
    }

    public function lookupEscolas(Request $request): JsonResponse
    {
        $this->abortIfNotCoordenadorInterno();

        return response()->json(
            DB::table('edu_escola')
                ->whereNull('esc_deleted_at')
                ->orderBy('esc_nome')
                ->select('esc_id', 'esc_nome')
                ->get()
        );
    }

    public function lookupTurmas(Request $request): JsonResponse
    {
        $this->abortIfNotCoordenadorInterno();

        $escId = (int) $request->input('esc_id');
        $anlId = (int) $request->input('anl_id');

        if (! $escId || ! $anlId) {
            return response()->json([]);
        }

        return response()->json(
            DB::table('edu_turma')
                ->where('tur_esc_id', $escId)
                ->where('tur_anl_id', $anlId)
                ->where('tur_modalidade', 'AEE')
                ->whereNull('tur_deleted_at')
                ->orderBy('tur_nome')
                ->select('tur_id', 'tur_nome')
                ->get()
        );
    }

    // ============ Helpers ============

    private function abortIfNotCoordenadorInterno(): void
    {
        $user = request()->user();
        abort_unless($user && $user->hasAnyRole(['coordenador_interno', 'admin']), 403, 'Acesso restrito a coordenadores pedagógicos internos.');
    }

    private function lotadoNaEscola(int $funId, int $escId): bool
    {
        return DB::table('edu_funcionario_lotacao as l')
            ->join('edu_funcionario_admissao as a', 'a.adm_id', '=', 'l.lot_adm_id')
            ->where('a.adm_fun_id', $funId)
            ->whereNull('a.adm_deleted_at')
            ->where('l.lot_esc_id', $escId)
            ->where('l.lot_fl_ativo', true)
            ->exists();
    }

    private function abortIfNotLotadoNaEscolaDoPlano(DiarioPlanoAee $plano, Request $request): void
    {
        if ($request->user()->isAdmin()) {
            return;
        }
        $funId = (int) $request->user()->fun_id;
        if (! $funId) {
            abort(403, 'Seu usuário não possui vínculo de funcionário.');
        }
        $escId = (int) DB::table('edu_turma')->where('tur_id', $plano->dae_tur_id)->value('tur_esc_id');
        abort_unless($this->lotadoNaEscola($funId, $escId), 403);
    }
}
