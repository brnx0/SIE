<?php

namespace App\Http\Controllers\Aluno;

use App\Http\Controllers\Controller;
use App\Models\Aluno\Aluno;
use App\Models\Aluno\HistoricoManual;
use App\Models\Matricula\Matricula;
use App\Models\Turma\Turma;
use App\Services\Aluno\HistoricoEscolar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Lançamento MANUAL de anos do histórico escolar (escolaridade anterior / fora
 * da rede). Só edita registros criados por esta tela; anos calculados pelo
 * sistema (matrículas/notas) são somente leitura.
 */
class HistoricoManualController extends Controller
{
    private const PERFIS = ['admin', 'secretaria_escola', 'diretor'];

    private function autorizar(): void
    {
        abort_unless(
            request()->user()?->hasAnyRole(self::PERFIS),
            403,
            'Perfil sem permissão para lançar histórico.'
        );
    }

    /** Anos que o sistema já calcula (aprovados) — read-only, bloqueiam lançamento manual. */
    private function anosSistema(int $alnId): array
    {
        return Matricula::query()
            ->where('tma_aln_id', $alnId)
            ->where('tma_modalidade', Turma::MODALIDADE_REGULAR)
            ->whereIn('tma_tas_cod_saida', HistoricoEscolar::APROVADO)
            ->whereNull('tma_deleted_at')
            ->with([
                'turma:tur_id,tur_esc_id,tur_anl_id',
                'turma.escola:esc_id,esc_nome',
                'turma.anoLetivo:anl_id,anl_ano',
                'serie:ser_id,ser_nome',
            ])
            ->get()
            ->filter(fn (Matricula $m) => $m->turma && $m->turma->anoLetivo)
            ->map(fn (Matricula $m) => [
                'anl_id'  => (int) $m->turma->tur_anl_id,
                'anl_ano' => (int) $m->turma->anoLetivo->anl_ano,
                'serie'   => $m->serie?->ser_nome,
                'escola'  => $m->turma->escola?->esc_nome,
            ])
            ->unique('anl_id')
            ->sortBy('anl_ano')
            ->values()
            ->all();
    }

    public function listar(Aluno $aluno, HistoricoEscolar $service): JsonResponse
    {
        $this->autorizar();

        // Consolidado do sistema (anos aprovados, com disciplinas + MF) — read-only.
        $anosSistema = $service->sistemaConsolidado((int) $aluno->aln_id);

        $lancamentos = HistoricoManual::query()
            ->where('hmn_aln_id', $aluno->aln_id)
            ->with([
                'disciplinas:hmd_id,hmd_hmn_id,hmd_dis_id,hmd_mf,hmd_cnc_id,hmd_ordem',
                'disciplinas.disciplina:dis_id,dis_nome',
                'disciplinas.conceito:cnc_id,cnc_sigla',
                'serie:ser_id,ser_nome',
                'escola:esc_id,esc_nome',
                'municipio:mun_id,mun_nome,mun_uf',
                'situacao:tas_cod,tas_descricao',
            ])
            ->get()
            ->map(fn (HistoricoManual $h) => [
                'hmn_id'            => $h->hmn_id,
                'hmn_ano'           => $h->hmn_ano,
                'hmn_ser_id'        => $h->hmn_ser_id,
                'serie'             => $h->serie?->ser_nome,
                'hmn_esc_id'        => $h->hmn_esc_id,
                'escola'            => $h->escola?->esc_nome,
                'hmn_esc_nome'      => $h->hmn_esc_nome,
                'hmn_mun_id'        => $h->hmn_mun_id,
                'municipio'         => $h->municipio ? [
                    'mun_id'   => $h->municipio->mun_id,
                    'mun_nome' => $h->municipio->mun_nome,
                    'mun_uf'   => $h->municipio->mun_uf,
                ] : null,
                'hmn_carga_horaria' => $h->hmn_carga_horaria,
                'hmn_dias_letivos'  => $h->hmn_dias_letivos,
                'hmn_frequencia'    => $h->hmn_frequencia,
                'hmn_tas_cod'       => $h->hmn_tas_cod,
                'situacao'          => $h->situacao?->tas_descricao,
                'disciplinas'       => $h->disciplinas->map(fn ($d) => [
                    'hmd_dis_id' => $d->hmd_dis_id,
                    'dis_nome'   => $d->disciplina?->dis_nome,
                    'hmd_mf'     => $d->hmd_mf,
                    'hmd_cnc_id' => $d->hmd_cnc_id,
                    'cnc_sigla'  => $d->conceito?->cnc_sigla,
                    'hmd_ordem'  => $d->hmd_ordem,
                ])->values(),
            ])
            ->values();

        // Situações de aprovação (ctrl_cod = 1) para o select.
        $situacoes = DB::table('edu_turma_aluno_situacao')
            ->where('tas_ctrl_cod', 1)
            ->orderBy('tas_cod')
            ->get(['tas_cod', 'tas_descricao']);

        // Segmentos (p/ filtrar a série no form).
        $segmentos = DB::table('edu_segmento')
            ->where('seg_fl_ativo', true)
            ->orderBy('seg_ordem')
            ->get(['seg_id', 'seg_nome_reduzido']);

        // Conceitos (p/ MF conceitual).
        $conceitos = DB::table('cfg_conceito')
            ->orderByDesc('cnc_peso')
            ->get(['cnc_id', 'cnc_sigla', 'cnc_descricao']);

        return response()->json([
            'anos_sistema' => $anosSistema,
            'segmentos'    => $segmentos,
            'conceitos'    => $conceitos,
            'lancamentos'  => $lancamentos,
            'situacoes'    => $situacoes,
        ]);
    }

    public function salvar(Request $request, Aluno $aluno): RedirectResponse
    {
        $this->autorizar();

        $data = $request->validate([
            'hmn_id'            => ['nullable', 'integer', 'exists:edu_historico_manual,hmn_id'],
            'hmn_ano'           => ['required', 'integer', 'min:1900', 'max:' . ((int) date('Y'))],
            'hmn_ser_id'        => ['required', 'integer', 'exists:edu_serie,ser_id'],
            'hmn_esc_id'        => ['nullable', 'integer', 'exists:edu_escola,esc_id'],
            'hmn_esc_nome'      => ['nullable', 'string', 'max:180'],
            'hmn_mun_id'        => ['nullable', 'integer', 'exists:edu_municipio,mun_id'],
            'hmn_carga_horaria' => ['nullable', 'integer', 'min:0', 'max:99999'],
            'hmn_dias_letivos'  => ['nullable', 'integer', 'min:0', 'max:999'],
            'hmn_frequencia'    => ['nullable', 'numeric', 'min:0', 'max:100'],
            'hmn_tas_cod'       => ['required', 'integer', 'exists:edu_turma_aluno_situacao,tas_cod'],
            'disciplinas'                 => ['required', 'array', 'min:1'],
            'disciplinas.*.hmd_dis_id'    => ['required', 'integer', 'exists:edu_disciplina,dis_id'],
            'disciplinas.*.hmd_mf'        => ['nullable', 'numeric', 'min:0', 'max:10'],
            'disciplinas.*.hmd_cnc_id'    => ['nullable', 'integer', 'exists:cfg_conceito,cnc_id'],
        ], [
            'hmn_ano.max' => 'O ano letivo não pode ser posterior ao ano atual.',
        ], [
            'hmn_ano' => 'ano letivo',
            'hmn_ser_id' => 'série',
            'hmn_tas_cod' => 'situação final',
        ]);

        // Escola: rede (esc_id) OU nome livre. Fora da rede exige nome.
        if (empty($data['hmn_esc_id']) && empty($data['hmn_esc_nome'])) {
            throw ValidationException::withMessages([
                'hmn_esc_nome' => 'Informe a escola (da rede) ou digite o nome da escola.',
            ]);
        }

        // Sistema tem precedência: não permite lançar manual num ano que o sistema já calcula.
        if (in_array((int) $data['hmn_ano'], array_column($this->anosSistema((int) $aluno->aln_id), 'anl_ano'), true)) {
            throw ValidationException::withMessages([
                'hmn_ano' => 'O sistema já possui esse ano letivo para o aluno (calculado das matrículas). Ele não pode ser lançado manualmente.',
            ]);
        }

        // Só edita registro manual do próprio aluno.
        $registro = null;
        if (! empty($data['hmn_id'])) {
            $registro = HistoricoManual::where('hmn_id', $data['hmn_id'])
                ->where('hmn_aln_id', $aluno->aln_id)
                ->firstOrFail();
        }

        // Um lançamento por (aluno, ano) — bloqueia duplicar em outro registro.
        $dupQuery = HistoricoManual::where('hmn_aln_id', $aluno->aln_id)
            ->where('hmn_ano', $data['hmn_ano']);
        if ($registro) {
            $dupQuery->where('hmn_id', '!=', $registro->hmn_id);
        }
        if ($dupQuery->exists()) {
            throw ValidationException::withMessages([
                'hmn_ano' => 'Já existe um lançamento manual para este aluno neste ano letivo.',
            ]);
        }

        DB::transaction(function () use ($data, $aluno, &$registro) {
            $payload = [
                'hmn_aln_id'        => (int) $aluno->aln_id,
                'hmn_ano'           => (int) $data['hmn_ano'],
                'hmn_ser_id'        => (int) $data['hmn_ser_id'],
                'hmn_esc_id'        => $data['hmn_esc_id'] ?? null,
                'hmn_esc_nome'      => $data['hmn_esc_id'] ? null : ($data['hmn_esc_nome'] ?? null),
                'hmn_mun_id'        => $data['hmn_esc_id'] ? null : ($data['hmn_mun_id'] ?? null),
                'hmn_carga_horaria' => $data['hmn_carga_horaria'] ?? null,
                'hmn_dias_letivos'  => $data['hmn_dias_letivos'] ?? null,
                'hmn_frequencia'    => $data['hmn_frequencia'] ?? null,
                'hmn_tas_cod'       => (int) $data['hmn_tas_cod'],
                'hmn_user_id'       => request()->user()?->id,
            ];

            if ($registro) {
                $registro->update($payload);
            } else {
                $registro = HistoricoManual::create($payload);
            }

            // Sincroniza disciplinas: hard replace (tabela filha simples).
            $registro->disciplinas()->forceDelete();
            foreach (array_values($data['disciplinas']) as $i => $d) {
                // Conceitual tem precedência; se houver conceito, zera a MF numérica.
                $cnc = $d['hmd_cnc_id'] ?? null;
                $registro->disciplinas()->create([
                    'hmd_dis_id' => (int) $d['hmd_dis_id'],
                    'hmd_mf'     => $cnc ? null : ($d['hmd_mf'] ?? null),
                    'hmd_cnc_id' => $cnc,
                    'hmd_ordem'  => $i,
                ]);
            }
        });

        return back()->with('success', 'Lançamento do histórico salvo.');
    }

    public function remover(Aluno $aluno, HistoricoManual $historicoManual): RedirectResponse
    {
        $this->autorizar();

        abort_unless((int) $historicoManual->hmn_aln_id === (int) $aluno->aln_id, 404);

        DB::transaction(function () use ($historicoManual) {
            $historicoManual->disciplinas()->forceDelete();
            $historicoManual->delete();
        });

        return back()->with('success', 'Lançamento do histórico removido.');
    }
}
