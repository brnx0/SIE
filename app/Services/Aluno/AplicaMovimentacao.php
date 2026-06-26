<?php

namespace App\Services\Aluno;

use App\Models\Aluno\AlunoMovimentacao;
use App\Models\Aluno\TipoMovimentacao;
use App\Models\Matricula\Matricula;
use App\Models\Turma\Turma;
use App\Services\Aluno\MigrarDiario;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AplicaMovimentacao
{
    public function executar(array $dados): AlunoMovimentacao
    {
        return DB::transaction(function () use ($dados) {
            $tipo = TipoMovimentacao::where('tmv_cod', $dados['tmv_cod'])
                ->where('tmv_fl_ativo', true)
                ->firstOrFail();

            $matriculaOrigem = Matricula::with('turma')
                ->where('tma_id', $dados['tma_id_origem'])
                ->where('tma_situacao', Matricula::SITUACAO_ATIVA)
                ->firstOrFail();

            if (in_array($tipo->tmv_cod, TipoMovimentacao::SEM_DESTINO, true)) {
                $mva = $this->semDestino($tipo, $matriculaOrigem, $dados);
            } elseif ($tipo->tmv_cod === 5) {
                $this->validarRemanejamento($matriculaOrigem, $dados);
                $mva = $this->comDestino($tipo, $matriculaOrigem, $dados);
            } elseif ($tipo->tmv_cod === 7) {
                $this->validarReclassificacao($matriculaOrigem, $dados);
                $mva = $this->comDestino($tipo, $matriculaOrigem, $dados);
            } else {
                throw ValidationException::withMessages(['tmv_cod' => 'Tipo de movimentação inválido.']);
            }

            return $mva;
        });
    }

    /**
     * Remanejamento em LOTE (move e/ou troca). Fecha TODAS as origens primeiro
     * (libera vaga para trocas), valida vaga líquida por turma e então abre os
     * destinos. Tudo-ou-nada (transação). Preserva a série de cada aluno.
     *
     * @param  array<int, array{tma_id:int, tur_id_destino:int, tma_ser_id_destino:?int}>  $itens
     * @param  array{dt_movimentacao:string, migrar_notas?:bool, migrar_faltas?:bool}  $dados
     * @return array{ok:int}
     */
    public function remanejarLote(array $itens, array $dados): array
    {
        return DB::transaction(function () use ($itens, $dados) {
            $tipo = TipoMovimentacao::where('tmv_cod', 5)->where('tmv_fl_ativo', true)->firstOrFail();

            $tmaIds = array_values(array_unique(array_map(fn ($i) => (int) $i['tma_id'], $itens)));
            if (count($tmaIds) !== count($itens)) {
                throw ValidationException::withMessages(['lote' => 'Aluno repetido na seleção.']);
            }

            $matriculas = Matricula::with(['turma', 'aluno:aln_id,aln_nome'])
                ->whereIn('tma_id', $tmaIds)
                ->where('tma_situacao', Matricula::SITUACAO_ATIVA)
                ->get()->keyBy('tma_id');

            $destIds = array_values(array_unique(array_map(fn ($i) => (int) $i['tur_id_destino'], $itens)));
            $destinos = Turma::whereIn('tur_id', $destIds)->get()->keyBy('tur_id');

            $erros = [];
            $plano = [];
            foreach ($itens as $it) {
                $origem  = $matriculas[$it['tma_id']] ?? null;
                $destino = $destinos[$it['tur_id_destino']] ?? null;
                $nome = $origem?->aluno?->aln_nome ?? "matrícula {$it['tma_id']}";

                if (! $origem) { $erros[] = "{$nome}: matrícula não está ativa."; continue; }
                if (! $destino || $destino->tur_situacao !== 'ABERTA') { $erros[] = "{$nome}: turma destino indisponível."; continue; }
                if ((int) $destino->tur_id === (int) $origem->tma_tur_id) { $erros[] = "{$nome}: já está nessa turma."; continue; }
                if ((int) $destino->tur_esc_id !== (int) $origem->turma->tur_esc_id || (int) $destino->tur_seg_id !== (int) $origem->turma->tur_seg_id) {
                    $erros[] = "{$nome}: escola/segmento incompatível.";
                    continue;
                }
                $plano[] = ['origem' => $origem, 'destino' => $destino, 'ser' => $it['tma_ser_id_destino'] ?? null, 'nome' => $nome];
            }

            // Vaga líquida por turma destino: atual − saídas + entradas ≤ capacidade.
            $incoming = [];
            $outgoing = [];
            foreach ($plano as $p) {
                $incoming[$p['destino']->tur_id] = ($incoming[$p['destino']->tur_id] ?? 0) + 1;
                $outgoing[$p['origem']->tma_tur_id] = ($outgoing[$p['origem']->tma_tur_id] ?? 0) + 1;
            }
            foreach (array_unique(array_keys($incoming)) as $turId) {
                $t = $destinos[$turId];
                if ($t->tur_capacidade === null) { continue; }
                // Limite efetivo = capacidade + expansão.
                $limite = (int) $t->tur_capacidade + (int) ($t->tur_qt_expansao ?? 0);
                $atual = Matricula::where('tma_tur_id', $turId)->where('tma_situacao', Matricula::SITUACAO_ATIVA)->count();
                $final = $atual - ($outgoing[$turId] ?? 0) + ($incoming[$turId] ?? 0);
                if ($final > $limite) {
                    $erros[] = "Turma {$t->tur_nome} ficaria com {$final}/{$limite} (sem vaga).";
                }
            }

            if ($erros) {
                throw ValidationException::withMessages(['lote' => implode(' ', array_unique($erros))]);
            }

            // Fase 1 — fecha todas as origens (situação 15). Libera vaga p/ troca.
            foreach ($plano as $p) {
                $this->encerrarMatricula($p['origem'], $tipo->tmv_tas_cod_saida, $dados['dt_movimentacao']);
            }

            // Fase 2 — abre destinos (situação 16), preserva série, migra diário opcional.
            foreach ($plano as $p) {
                $origem = $p['origem'];
                $destino = $p['destino'];

                $novo = Matricula::create([
                    'tma_aln_id'          => $origem->tma_aln_id,
                    'tma_tur_id'          => $destino->tur_id,
                    'tma_ser_id'          => $p['ser'],
                    'tma_anl_id'          => $destino->tur_anl_id,
                    'tma_modalidade'      => $destino->tur_modalidade,
                    'tma_situacao'        => Matricula::SITUACAO_ATIVA,
                    'tma_tas_cod_entrada' => $tipo->tmv_tas_cod_entrada,
                    'tma_dt_matricula'    => $dados['dt_movimentacao'],
                    'tma_created_by_id'   => Auth::id(),
                ]);

                if ($destino->tur_modalidade === 'REGULAR') {
                    $svc = app(MigrarDiario::class);
                    if (! empty($dados['migrar_notas'])) {
                        $svc->migrarNotas((int) $origem->tma_aln_id, (int) $origem->tma_tur_id, $destino);
                    }
                    if (! empty($dados['migrar_faltas'])) {
                        $svc->migrarFaltas((int) $origem->tma_aln_id, (int) $origem->tma_tur_id, $destino);
                    }
                }

                AlunoMovimentacao::create([
                    'mva_aln_id'          => $origem->tma_aln_id,
                    'mva_tmv_cod'         => $tipo->tmv_cod,
                    'mva_tma_id_origem'   => $origem->tma_id,
                    'mva_tma_id_destino'  => $novo->tma_id,
                    'mva_dt_movimentacao' => $dados['dt_movimentacao'],
                    'mva_user_id'         => Auth::id(),
                ]);
            }

            return ['ok' => count($plano)];
        });
    }

    protected function semDestino(TipoMovimentacao $tipo, Matricula $origem, array $dados): AlunoMovimentacao
    {
        $this->encerrarMatricula($origem, $tipo->tmv_tas_cod_saida, $dados['dt_movimentacao']);

        $extras = $this->encerrarMatriculasComplementares($origem->tma_aln_id, $tipo->tmv_tas_cod_saida, $dados['dt_movimentacao']);

        return AlunoMovimentacao::create([
            'mva_aln_id'           => $origem->tma_aln_id,
            'mva_tmv_cod'          => $tipo->tmv_cod,
            'mva_tma_id_origem'    => $origem->tma_id,
            'mva_tma_id_destino'   => null,
            'mva_dt_movimentacao'  => $dados['dt_movimentacao'],
            'mva_protocolo'        => $dados['protocolo'] ?? null,
            'mva_observacao'       => $dados['observacao'] ?? null,
            'mva_tmas_extras'      => $extras ?: null,
            'mva_user_id'          => Auth::id(),
        ]);
    }

    protected function comDestino(TipoMovimentacao $tipo, Matricula $origem, array $dados): AlunoMovimentacao
    {
        $turmaDestino = Turma::where('tur_id', $dados['tur_id_destino'])
            ->where('tur_situacao', 'ABERTA')
            ->firstOrFail();

        $this->encerrarMatricula($origem, $tipo->tmv_tas_cod_saida, $dados['dt_movimentacao']);

        $destino = Matricula::create([
            'tma_aln_id'          => $origem->tma_aln_id,
            'tma_tur_id'          => $turmaDestino->tur_id,
            // Série explícita (preserva a do aluno em turmas multisseriadas).
            // Sem o parâmetro, mantém o comportamento antigo (deriva da turma destino).
            'tma_ser_id'          => $dados['tma_ser_id_destino'] ?? null,
            'tma_anl_id'          => $turmaDestino->tur_anl_id,
            'tma_modalidade'      => $turmaDestino->tur_modalidade,
            'tma_situacao'        => Matricula::SITUACAO_ATIVA,
            'tma_tas_cod_entrada' => $tipo->tmv_tas_cod_entrada,
            'tma_dt_matricula'    => $dados['dt_movimentacao'],
            'tma_created_by_id'   => Auth::id(),
        ]);

        // Leva o histórico do diário só no REMANEJAMENTO (tmv_cod 5), turma REGULAR,
        // e conforme o usuário escolheu (notas e/ou faltas). Reclassificação (7) não migra.
        if ($tipo->tmv_cod === 5 && $turmaDestino->tur_modalidade === 'REGULAR') {
            $svc = app(MigrarDiario::class);
            $alnId = (int) $origem->tma_aln_id;
            $turOrigem = (int) $origem->tma_tur_id;

            if (! empty($dados['migrar_notas'])) {
                $svc->migrarNotas($alnId, $turOrigem, $turmaDestino);
            }
            if (! empty($dados['migrar_faltas'])) {
                $svc->migrarFaltas($alnId, $turOrigem, $turmaDestino);
            }
        }

        return AlunoMovimentacao::create([
            'mva_aln_id'          => $origem->tma_aln_id,
            'mva_tmv_cod'         => $tipo->tmv_cod,
            'mva_tma_id_origem'   => $origem->tma_id,
            'mva_tma_id_destino'  => $destino->tma_id,
            'mva_dt_movimentacao' => $dados['dt_movimentacao'],
            'mva_protocolo'       => $dados['protocolo'] ?? null,
            'mva_observacao'      => $dados['observacao'] ?? null,
            'mva_user_id'         => Auth::id(),
        ]);
    }

    protected function encerrarMatricula(Matricula $m, int $tasCodSaida, string $dt): void
    {
        $m->update([
            'tma_situacao'      => 'SAIDA',
            'tma_tas_cod_saida' => $tasCodSaida,
            'tma_dt_saida'      => $dt,
        ]);
    }

    /** @return array<int, array{tma_id:int,tur_modalidade:string}> */
    protected function encerrarMatriculasComplementares(int $alnId, int $tasCodSaida, string $dt): array
    {
        $outras = Matricula::with('turma:tur_id,tur_modalidade')
            ->where('tma_aln_id', $alnId)
            ->where('tma_situacao', Matricula::SITUACAO_ATIVA)
            ->whereHas('turma', fn ($q) => $q->whereIn('tur_modalidade', [Turma::MODALIDADE_AEE, Turma::MODALIDADE_ATIVIDADE]))
            ->get();

        $extras = [];
        foreach ($outras as $m) {
            $this->encerrarMatricula($m, $tasCodSaida, $dt);
            $extras[] = [
                'tma_id'         => $m->tma_id,
                'tur_modalidade' => $m->turma?->tur_modalidade,
            ];
        }

        return $extras;
    }

    protected function validarRemanejamento(Matricula $origem, array $dados): void
    {
        $destino = Turma::findOrFail($dados['tur_id_destino']);

        if ($destino->tur_id === $origem->tma_tur_id) {
            throw ValidationException::withMessages([
                'tur_id_destino' => 'A turma de destino deve ser diferente da turma atual do aluno.',
            ]);
        }

        if ($destino->tur_esc_id !== $origem->turma->tur_esc_id) {
            throw ValidationException::withMessages([
                'tur_id_destino' => 'Remanejamento exige mesma escola da turma de origem.',
            ]);
        }

        $segOrigem  = $origem->turma->tur_seg_id;
        $segDestino = $destino->tur_seg_id;

        if ($segOrigem !== $segDestino) {
            $ejaIds = [6, 7];
            $fund2  = 4;

            if ($segOrigem !== $fund2 || ! in_array($segDestino, $ejaIds, true)) {
                throw ValidationException::withMessages([
                    'tur_id_destino' => 'Troca de segmento permitida apenas de Fundamental 2 para EJA.',
                ]);
            }

            $aluno = $origem->aluno;
            $corte = optional($destino->anoLetivo)->anl_dt_corte ?? now()->toDateString();
            $idade = $this->idadeNaData($aluno->aln_dt_nascimento, $corte);

            if ($idade < 15) {
                throw ValidationException::withMessages([
                    'tur_id_destino' => 'Aluno precisa ter pelo menos 15 anos completos para EJA.',
                ]);
            }
        }

        $this->validarVagaEDuplicidade($origem, $destino);
    }

    protected function validarReclassificacao(Matricula $origem, array $dados): void
    {
        $destino = Turma::findOrFail($dados['tur_id_destino']);

        if ($destino->tur_esc_id !== $origem->turma->tur_esc_id) {
            throw ValidationException::withMessages([
                'tur_id_destino' => 'Reclassificação exige mesma escola da turma de origem.',
            ]);
        }

        $this->validarVagaEDuplicidade($origem, $destino);
    }

    protected function validarVagaEDuplicidade(Matricula $origem, Turma $destino): void
    {
        $ativas = Matricula::where('tma_tur_id', $destino->tur_id)
            ->where('tma_situacao', Matricula::SITUACAO_ATIVA)
            ->count();

        if ($destino->tur_capacidade && $ativas >= $destino->tur_capacidade) {
            throw ValidationException::withMessages([
                'tur_id_destino' => 'Turma destino sem vaga disponível.',
            ]);
        }

        $jaMatriculado = Matricula::where('tma_tur_id', $destino->tur_id)
            ->where('tma_aln_id', $origem->tma_aln_id)
            ->where('tma_situacao', Matricula::SITUACAO_ATIVA)
            ->exists();

        if ($jaMatriculado) {
            throw ValidationException::withMessages([
                'tur_id_destino' => 'Aluno já possui matrícula ativa na turma destino.',
            ]);
        }
    }

    protected function idadeNaData(string $nascimento, string $referencia): int
    {
        $nasc = \Carbon\Carbon::parse($nascimento);
        $ref  = \Carbon\Carbon::parse($referencia);
        $idade = $ref->year - $nasc->year;
        if ($ref->format('m-d') < $nasc->format('m-d')) {
            $idade--;
        }
        return $idade;
    }
}
