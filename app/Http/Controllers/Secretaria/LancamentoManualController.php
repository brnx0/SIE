<?php

namespace App\Http\Controllers\Secretaria;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Relatorio\Concerns\NotaLookups;
use App\Models\Diario\NotaManual;
use App\Models\Matricula\Matricula;
use App\Models\Parametro\AnoLetivo;
use App\Models\Parametro\Conceito;
use App\Support\CalculoNota;
use App\Support\UserAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Lançamento MANUAL de média + faltas pela secretaria (escolas que controlam
 * fora do sistema). Grade: alunos × unidades (todos os períodos de uma vez),
 * para a disciplina escolhida. Tem precedência sobre a média calculada.
 */
class LancamentoManualController extends Controller
{
    use NotaLookups; // turmas(), disciplinas(), alunos(), unidades(), autorizarTurma()

    public function form(): Response
    {
        $user = auth()->user();

        return Inertia::render('secretaria/lancamento-manual/Index', [
            'anosLetivos' => AnoLetivo::orderByDesc('anl_ano')->get(['anl_id', 'anl_ano']),
            'escolas'     => UserAccess::escolasVisiveis($user),
            'userEscola'  => UserAccess::escolaDefault($user),
            'isAdmin'     => $user->isAdmin(),
        ]);
    }

    /** Unidades + alunos + modo de entrada + disciplinas (todas ou a filtrada) com valores já lançados. */
    public function contexto(Request $request): JsonResponse
    {
        $turId = (int) $request->input('tur_id');
        $disId = $request->filled('dis_id') ? (int) $request->input('dis_id') : null;

        if (! $turId) {
            return response()->json(['entrada' => null, 'unidades' => [], 'alunos' => [], 'conceitos' => [], 'disciplinas' => []]);
        }

        $this->autorizarTurma($turId);

        $turma = DB::table('edu_turma')->where('tur_id', $turId)->first(['tur_ser_id', 'tur_anl_id']);
        abort_unless($turma, 404);

        $tipos = $this->tiposSerie($turId);
        $modo  = $this->conceitoModoAno((int) $turma->tur_anl_id);
        if (empty($tipos)) {
            return response()->json([
                'tipos'        => [],
                'conceito_modo' => $modo,
                'unidades'     => [],
                'alunos'       => [],
                'conceitos'    => [],
                'disciplinas'  => [],
            ]);
        }

        $unidades = DB::table('cfg_unidade')
            ->where('uni_anl_id', $turma->tur_anl_id)
            ->orderBy('uni_numero')
            ->get(['uni_id', 'uni_numero', 'uni_tipo'])
            ->map(fn ($u) => ['uni_id' => (int) $u->uni_id, 'label' => $this->unidadeLabel((int) $u->uni_numero, (string) $u->uni_tipo)])
            ->values();

        $alunos = Matricula::query()
            ->where('tma_tur_id', $turId)
            ->where('tma_situacao', Matricula::SITUACAO_ATIVA)
            ->whereNull('tma_deleted_at')
            ->with('aluno:aln_id,aln_nome,aln_nr_matricula')
            ->get()
            ->filter(fn ($m) => $m->aluno)
            ->sortBy(fn ($m) => \Illuminate\Support\Str::ascii(mb_strtolower((string) $m->aluno->aln_nome)))
            ->values()
            ->map(fn ($m) => ['aln_id' => $m->aluno->aln_id, 'aln_nome' => $m->aluno->aln_nome, 'aln_nr_matricula' => $m->aluno->aln_nr_matricula]);

        // Disciplinas da grade (todas, ou só a filtrada).
        $grade = DB::table('edu_grade_disciplinar as gd')
            ->join('edu_disciplina as d', 'd.dis_id', '=', 'gd.grd_dis_id')
            ->where('gd.grd_ser_id', $turma->tur_ser_id)
            ->where('gd.grd_anl_id', $turma->tur_anl_id)
            ->where('gd.grd_fl_ativo', true)
            ->when($disId, fn ($q) => $q->where('d.dis_id', $disId))
            ->distinct()
            ->orderBy('gd.grd_ordem')
            ->orderBy('d.dis_nome')
            ->get(['d.dis_id', 'd.dis_nome', 'gd.grd_ordem']);

        // Valores já lançados, agrupados: [dis_id][aln_id][uni_id] => { media, cnc_id, faltas }.
        $valByDis = [];
        $rows = NotaManual::where('nmn_tur_id', $turId)
            ->when($disId, fn ($q) => $q->where('nmn_dis_id', $disId))
            ->get();
        foreach ($rows as $r) {
            $valByDis[(int) $r->nmn_dis_id][(int) $r->nmn_aln_id][(int) $r->nmn_uni_id] = [
                'media'  => $r->nmn_media !== null ? (float) $r->nmn_media : null,
                'cnc_id' => $r->nmn_cnc_id !== null ? (int) $r->nmn_cnc_id : null,
                'faltas' => $r->nmn_faltas,
                'tipo'   => $r->nmn_tipo,
            ];
        }

        // Médias calculadas do diário, por disciplina → [aln_id][uni_id] => { media, cnc_id, completo }.
        // Só vêm as células COMPLETAS (aluno com nota em todas as avaliações regulares);
        // o front auto-preenche a média quando não há override manual.
        $uniIds  = $unidades->pluck('uni_id')->map(fn ($v) => (int) $v)->all();
        $disIds  = $grade->pluck('dis_id')->map(fn ($v) => (int) $v)->unique()->values()->all();
        $calcAll = CalculoNota::calculadoTurma($turId, $disIds, $uniIds);

        $disciplinas = $grade->map(fn ($d) => [
            'dis_id'   => (int) $d->dis_id,
            'dis_nome' => $d->dis_nome,
            'valores'  => $valByDis[(int) $d->dis_id] ?? (object) [],
            'calc'     => $calcAll[(int) $d->dis_id] ?? (object) [],
        ])->values();

        return response()->json([
            'tipos'         => $tipos,         // ['numerica'] | ['conceitual'] | ['numerica','conceitual']
            'conceito_modo' => $modo,          // 'faixa' | 'conceito'
            'unidades'      => $unidades,
            'alunos'        => $alunos,
            'conceitos'     => in_array('conceitual', $tipos, true) && $modo === 'conceito'
                ? Conceito::orderBy('cnc_peso')->get(['cnc_id', 'cnc_sigla', 'cnc_descricao'])
                : [],
            'disciplinas'   => $disciplinas,
        ]);
    }

    /** Upsert de 1 célula (turma, disciplina, unidade, aluno): média e/ou faltas. */
    public function salvar(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tur_id' => ['required', 'integer', 'exists:edu_turma,tur_id'],
            'dis_id' => ['required', 'integer', 'exists:edu_disciplina,dis_id'],
            'uni_id' => ['required', 'integer', 'exists:cfg_unidade,uni_id'],
            'aln_id' => ['required', 'integer', 'exists:edu_aluno,aln_id'],
            'tipo'   => ['required', Rule::in(['numerica', 'conceitual'])],
            'media'  => ['nullable', 'numeric', 'min:0', 'max:10'],
            'cnc_id' => ['nullable', 'integer', 'exists:cfg_conceito,cnc_id'],
            'faltas' => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);

        $this->autorizarTurma((int) $data['tur_id']);

        $turma = DB::table('edu_turma')->where('tur_id', $data['tur_id'])->first(['tur_esc_id', 'tur_anl_id']);
        abort_unless($turma, 404);

        $tipos = $this->tiposSerie((int) $data['tur_id']);
        $tipo  = $data['tipo'];
        abort_unless(in_array($tipo, $tipos, true), 422, 'A série desta turma não permite este tipo de avaliação.');

        $modo = $this->conceitoModoAno((int) $turma->tur_anl_id);

        // Conceitual direto → conceito; numérica e conceitual-faixa → valor.
        $media = null;
        $cncId = null;
        if ($tipo === 'conceitual' && $modo === 'conceito') {
            $cncId = $data['cnc_id'] ?? null;
        } else {
            if (($data['media'] ?? null) !== null && (float) $data['media'] > 10) {
                throw ValidationException::withMessages(['media' => 'A média não pode passar de 10.']);
            }
            // Média final arredondada ao 0,5 mais próximo (mesma regra do diário).
            $media = ($data['media'] ?? null) !== null ? round(((float) $data['media']) * 2) / 2 : null;
        }
        $faltas = ($data['faltas'] ?? null) !== null ? (int) $data['faltas'] : null;

        // Exclusão mútua: aluno não pode ter numérica e conceitual na mesma disciplina.
        if ($media !== null || $cncId !== null) {
            $temOutroTipo = NotaManual::where('nmn_tur_id', $data['tur_id'])
                ->where('nmn_dis_id', $data['dis_id'])
                ->where('nmn_aln_id', $data['aln_id'])
                ->where('nmn_tipo', '!=', $tipo)
                ->where(fn ($q) => $q->whereNotNull('nmn_media')->orWhereNotNull('nmn_cnc_id'))
                ->exists();
            if ($temOutroTipo) {
                $outro = $tipo === 'numerica' ? 'conceitual' : 'numérica';
                throw ValidationException::withMessages([
                    'tipo' => "Aluno já possui lançamento {$outro} nesta disciplina. Remova-os para lançar como ".($tipo === 'numerica' ? 'numérica' : 'conceitual').'.',
                ]);
            }
        }

        $chave = [
            'nmn_tur_id' => $data['tur_id'],
            'nmn_dis_id' => $data['dis_id'],
            'nmn_uni_id' => $data['uni_id'],
            'nmn_aln_id' => $data['aln_id'],
        ];

        // Tudo vazio → remove o registro (volta a usar a média calculada).
        if ($media === null && $cncId === null && $faltas === null) {
            NotaManual::where($chave)->delete();

            return response()->json(['ok' => true, 'removido' => true]);
        }

        NotaManual::updateOrCreate($chave, [
            'nmn_anl_id'  => $turma->tur_anl_id,
            'nmn_esc_id'  => $turma->tur_esc_id,
            'nmn_tipo'    => $tipo,
            'nmn_media'   => $media,
            'nmn_cnc_id'  => $cncId,
            'nmn_faltas'  => $faltas,
            'nmn_user_id' => (int) $request->user()->id,
        ]);

        return response()->json(['ok' => true]);
    }

    // ============ Helpers ============

    /**
     * Tipos de avaliação que a série permite lançar (subconjunto de
     * ['numerica','conceitual'], nessa ordem). A série pode permitir ambos —
     * nesse caso a secretaria escolhe na tela.
     *
     * @return array<int,string>
     */
    private function tiposSerie(int $turId): array
    {
        $raw = DB::table('edu_turma as t')
            ->join('edu_serie as s', 's.ser_id', '=', 't.tur_ser_id')
            ->where('t.tur_id', $turId)
            ->value('s.ser_tipo_avaliacao');

        $tipos = is_array($raw) ? $raw : (json_decode((string) $raw, true) ?: []);

        return array_values(array_filter(['numerica', 'conceitual'], fn ($t) => in_array($t, $tipos, true)));
    }

    /** Modo de conceito do ano: 'conceito' (direto) ou 'faixa' (numérico → conceito). */
    private function conceitoModoAno(int $anlId): string
    {
        return DB::table('cfg_ano_letivo')->where('anl_id', $anlId)->value('anl_conceito_modo') === 'conceito'
            ? 'conceito'
            : 'faixa';
    }
}
