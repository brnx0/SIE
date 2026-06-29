<?php

namespace App\Http\Controllers\Encerramento;

use App\Http\Controllers\Controller;
use App\Models\Parametro\AnoLetivo;
use App\Models\Parametro\Unidade;
use App\Models\Turma\Turma;
use App\Support\UserAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Duplicar Turmas — cria as turmas do ano seguinte a partir das turmas
 * ENCERRADAS do ano selecionado, evitando recriá-las manualmente.
 * Cada turma só pode ser duplicada uma vez (lineage em tur_origem_tur_id).
 */
class DuplicarTurmaController extends Controller
{
    /** Campos copiados da turma de origem para a nova turma. */
    private const CAMPOS = [
        'tur_esc_id', 'tur_seg_id', 'tur_ser_id', 'tur_cd_inep', 'tur_nome', 'tur_turno',
        'tur_capacidade', 'tur_semestre', 'tur_qt_expansao', 'tur_tipo_atendimento',
        'tur_hora_inicio', 'tur_hora_fim', 'tur_mediacao', 'tur_local_diferenciado',
        'tur_aee_sala', 'tur_dias_funcionamento', 'tur_obs', 'tur_modalidade',
    ];

    public function index(): Response
    {
        $user = auth()->user();

        return Inertia::render('duplicar-turmas/Index', [
            'anosLetivos' => AnoLetivo::orderByDesc('anl_ano')->get(['anl_id', 'anl_ano', 'anl_fl_em_exercicio']),
            'escolas'     => UserAccess::escolasVisiveis($user),
            'userEscola'  => UserAccess::escolaDefault($user),
            'isAdmin'     => $user->isAdmin(),
        ]);
    }

    /** Turmas ENCERRADAS do ano/escola + indicador de já duplicada; e se o próximo ano existe. */
    public function dados(Request $request): JsonResponse
    {
        $data = $request->validate([
            'anl_id' => ['required', 'integer', 'exists:cfg_ano_letivo,anl_id'],
            'esc_id' => ['required', 'integer', 'exists:edu_escola,esc_id'],
        ]);

        $this->autorizarEscola((int) $data['esc_id']);

        $ano  = AnoLetivo::findOrFail($data['anl_id']);
        $prox = AnoLetivo::where('anl_ano', $ano->anl_ano + 1)->first(['anl_id', 'anl_ano']);

        $jaDup = [];
        if ($prox) {
            foreach (DB::table('edu_turma')->where('tur_anl_id', $prox->anl_id)->whereNotNull('tur_origem_tur_id')->whereNull('tur_deleted_at')->pluck('tur_origem_tur_id') as $id) {
                $jaDup[(int) $id] = true;
            }
        }

        $turmas = DB::table('edu_turma as t')
            ->leftJoin('edu_serie as s', 's.ser_id', '=', 't.tur_ser_id')
            ->leftJoin('edu_segmento as seg', 'seg.seg_id', '=', 's.seg_id')
            ->whereNull('t.tur_deleted_at')
            ->where('t.tur_modalidade', 'REGULAR')
            ->where('t.tur_situacao', 'ENCERRADA')
            ->where('t.tur_anl_id', $data['anl_id'])
            ->where('t.tur_esc_id', $data['esc_id'])
            ->orderBy('seg.seg_ordem')
            ->orderBy('s.ser_nome')
            ->orderBy('t.tur_nome')
            ->get(['t.tur_id', 't.tur_nome', 's.ser_nome', 'seg.seg_nome_reduzido'])
            ->map(fn ($t) => [
                'tur_id'    => (int) $t->tur_id,
                'turma'     => $t->tur_nome,
                'serie'     => $t->ser_nome,
                'segmento'  => $t->seg_nome_reduzido,
                'duplicada' => isset($jaDup[(int) $t->tur_id]),
            ])
            ->values();

        return response()->json([
            'turmas'        => $turmas,
            'tem_prox_ano'  => (bool) $prox,
            // Sempre devolve o ANO (número) seguinte — exista ou não; se não existir, é criado ao duplicar.
            'prox_ano'      => $prox?->anl_ano ?? ($ano->anl_ano + 1),
        ]);
    }

    /** Duplica UMA turma para o ano seguinte. */
    public function duplicar(Request $request): JsonResponse
    {
        $data = $request->validate(['tur_id' => ['required', 'integer', 'exists:edu_turma,tur_id']]);

        $src = DB::table('edu_turma')->where('tur_id', $data['tur_id'])->whereNull('tur_deleted_at')->first();
        abort_unless($src, 404);
        $this->autorizarEscola((int) $src->tur_esc_id);

        [$prox, $erro] = $this->resolverProximoAno($src);
        if ($erro) {
            return response()->json(['ok' => false, 'message' => $erro], 422);
        }

        if ($this->jaDuplicada((int) $src->tur_id, (int) $prox->anl_id)) {
            return response()->json(['ok' => false, 'message' => 'Esta turma já foi duplicada para o ano seguinte.'], 422);
        }

        $novoId = $this->criarCopia($src, (int) $prox->anl_id);

        return response()->json(['ok' => true, 'tur_id' => $novoId]);
    }

    /** Duplica TODAS as turmas encerradas ainda não duplicadas do ano/escola. */
    public function duplicarTodas(Request $request): JsonResponse
    {
        $data = $request->validate([
            'anl_id' => ['required', 'integer', 'exists:cfg_ano_letivo,anl_id'],
            'esc_id' => ['required', 'integer', 'exists:edu_escola,esc_id'],
        ]);
        $this->autorizarEscola((int) $data['esc_id']);

        $ano  = AnoLetivo::findOrFail($data['anl_id']);
        $prox = $this->proximoAnoOuCriar($ano);

        $origens = DB::table('edu_turma')
            ->where('tur_anl_id', $prox->anl_id)->whereNotNull('tur_origem_tur_id')->whereNull('tur_deleted_at')
            ->pluck('tur_origem_tur_id')->map(fn ($v) => (int) $v)->all();
        $jaDup = array_flip($origens);

        $turmas = DB::table('edu_turma')
            ->whereNull('tur_deleted_at')
            ->where('tur_modalidade', 'REGULAR')
            ->where('tur_situacao', 'ENCERRADA')
            ->where('tur_anl_id', $data['anl_id'])
            ->where('tur_esc_id', $data['esc_id'])
            ->get();

        $criadas = 0;
        DB::transaction(function () use ($turmas, $jaDup, $prox, &$criadas) {
            foreach ($turmas as $src) {
                if (isset($jaDup[(int) $src->tur_id])) {
                    continue;
                }
                $this->criarCopia($src, (int) $prox->anl_id);
                $criadas++;
            }
        });

        return response()->json(['ok' => true, 'criadas' => $criadas]);
    }

    // ============ Helpers ============

    /** @return array{0: ?object, 1: ?string} [proximoAno, erro] */
    private function resolverProximoAno(object $src): array
    {
        if ($src->tur_situacao !== 'ENCERRADA') {
            return [null, 'A turma só pode ser duplicada depois de encerrada.'];
        }
        $ano = AnoLetivo::find($src->tur_anl_id);
        if (! $ano) {
            return [null, 'Ano letivo de origem não encontrado.'];
        }

        return [$this->proximoAnoOuCriar($ano), null];
    }

    /**
     * Próximo ano letivo. Se ainda não existe, é CRIADO copiando as informações
     * do ano atual (config + unidades), com as datas deslocadas +1 ano — assim o
     * usuário nunca fica impedido de duplicar por falta do ano seguinte.
     */
    private function proximoAnoOuCriar(AnoLetivo $ano): AnoLetivo
    {
        $prox = AnoLetivo::where('anl_ano', $ano->anl_ano + 1)->first();
        if ($prox) {
            return $prox;
        }

        return DB::transaction(function () use ($ano) {
            // Re-checa sob lock p/ evitar corrida em cliques simultâneos.
            $existe = AnoLetivo::where('anl_ano', $ano->anl_ano + 1)->lockForUpdate()->first();
            if ($existe) {
                return $existe;
            }

            $uid  = auth()->id();
            $novo = AnoLetivo::create([
                'anl_ano'                        => $ano->anl_ano + 1,
                'anl_dt_inicio_ano'              => $ano->anl_dt_inicio_ano?->copy()->addYear(),
                'anl_dt_fim'                     => $ano->anl_dt_fim?->copy()->addYear(),
                'anl_dt_censo'                   => $ano->anl_dt_censo?->copy()->addYear(),
                'anl_dt_corte'                   => $ano->anl_dt_corte?->copy()->addYear(),
                'anl_fl_em_exercicio'            => $ano->anl_fl_em_exercicio,
                'anl_fl_progressao_parcial'      => $ano->anl_fl_progressao_parcial,
                'anl_fl_aprovacao_conselho_freq' => $ano->anl_fl_aprovacao_conselho_freq,
                'anl_frequencia_minima'          => $ano->anl_frequencia_minima,
                'anl_media_geral'                => $ano->anl_media_geral,
                'anl_cnc_id_geral'               => $ano->anl_cnc_id_geral,
                'anl_conceito_modo'              => $ano->anl_conceito_modo,
                'anl_qtd_materias_conselho'      => $ano->anl_qtd_materias_conselho,
                'anl_created_by_id'              => $uid,
                'anl_updated_by_id'              => $uid,
            ]);

            // Copia os períodos (unidades) deslocando as datas +1 ano.
            foreach (Unidade::where('uni_anl_id', $ano->anl_id)->orderBy('uni_numero')->get() as $u) {
                Unidade::create([
                    'uni_anl_id'        => $novo->anl_id,
                    'uni_tipo'          => $u->uni_tipo,
                    'uni_numero'        => $u->uni_numero,
                    'uni_dt_inicio'     => $u->uni_dt_inicio?->copy()->addYear(),
                    'uni_dt_fim'        => $u->uni_dt_fim?->copy()->addYear(),
                    'uni_dias_extensao' => $u->uni_dias_extensao,
                ]);
            }

            return $novo;
        });
    }

    private function jaDuplicada(int $turId, int $proxAnlId): bool
    {
        return DB::table('edu_turma')
            ->where('tur_origem_tur_id', $turId)
            ->where('tur_anl_id', $proxAnlId)
            ->whereNull('tur_deleted_at')
            ->exists();
    }

    private function criarCopia(object $src, int $proxAnlId): int
    {
        $dados = [];
        foreach (self::CAMPOS as $c) {
            $dados[$c] = $src->{$c} ?? null;
        }
        $dados['tur_anl_id']        = $proxAnlId;
        $dados['tur_origem_tur_id'] = $src->tur_id;
        $dados['tur_situacao']      = 'ABERTA';

        return (int) Turma::create($dados)->tur_id;
    }

    private function autorizarEscola(int $escId): void
    {
        $user = auth()->user();
        if ($user->isAdmin()) {
            return;
        }
        $ids = UserAccess::escolasIds($user);
        abort_unless(is_array($ids) && in_array($escId, $ids, true), 403, 'Você não tem acesso a esta escola.');
    }
}
