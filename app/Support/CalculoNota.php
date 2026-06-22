<?php

namespace App\Support;

use App\Models\Diario\DiarioAvaliacao;
use App\Models\Diario\DiarioNota;
use App\Models\Matricula\Matricula;
use App\Models\Parametro\Conceito;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Cálculo do resultado de notas (numérica / conceitual) por turma+disciplina+unidade,
 * replicando as regras dos painéis do diário. Usado pelos relatórios.
 *
 * Resultado por aluno: ['tipo','valor','conceito'=>['sigla','descricao','peso']|null]
 */
class CalculoNota
{
    /**
     * Resultado por aluno em uma (turma, disciplina, unidade).
     *
     * @return array<int, array{tipo:?string, valor:?float, conceito:?array}>
     */
    public static function resultado(int $turId, int $disId, int $uniId): array
    {
        // Lançamento manual da secretaria tem precedência sobre o cálculo.
        $manual = self::manual($turId, $disId, $uniId);

        $avaliacoes = DiarioAvaliacao::query()
            ->where('ava_tur_id', $turId)
            ->where('ava_dis_id', $disId)
            ->where('ava_uni_id', $uniId)
            ->get(['ava_id', 'ava_tipo', 'ava_valor', 'ava_fl_recuperacao', 'ava_fl_migrada', 'ava_dt']);

        if ($avaliacoes->isEmpty()) {
            return $manual; // pode ser [] ou só os manuais
        }

        $modo      = self::conceitoModo($uniId);
        $conceitos = self::conceitos();
        $notaMap   = self::notas($avaliacoes->pluck('ava_id')->all());
        $hoje      = now()->startOfDay();

        $futura = fn ($a) => $a->ava_dt && Carbon::parse($a->ava_dt)->startOfDay()->gt($hoje);

        $out = [];
        foreach (self::alunos($turId) as $alnId) {
            $out[$alnId] = self::resultadoAluno($avaliacoes, $notaMap, $alnId, $modo, $conceitos, $futura);
        }

        // Sobrepõe o manual onde houver.
        foreach ($manual as $alnId => $r) {
            $out[$alnId] = $r;
        }

        return $out;
    }

    /**
     * Médias CALCULADAS (sem aplicar o override manual) com flag de completude,
     * para várias disciplinas/unidades de uma turma de uma só vez. Retorna apenas
     * as células COMPLETAS (aluno com nota em todas as avaliações regulares).
     *
     * Usado pela tela de lançamento manual da secretaria para auto-preencher a
     * média a partir das notas do diário. Queries constantes (não por célula).
     *
     * @param  array<int>  $disIds
     * @param  array<int>  $uniIds
     * @return array<int, array<int, array<int, array{media:?float, cnc_id:?int, tipo:?string, completo:bool}>>>  [disId][alnId][uniId]
     */
    public static function calculadoTurma(int $turId, array $disIds, array $uniIds): array
    {
        if (empty($disIds) || empty($uniIds)) {
            return [];
        }

        $alunos = self::alunos($turId);
        if (empty($alunos)) {
            return [];
        }

        $avaliacoes = DiarioAvaliacao::query()
            ->where('ava_tur_id', $turId)
            ->whereIn('ava_dis_id', $disIds)
            ->whereIn('ava_uni_id', $uniIds)
            ->get(['ava_id', 'ava_dis_id', 'ava_uni_id', 'ava_tipo', 'ava_valor', 'ava_fl_recuperacao', 'ava_fl_migrada', 'ava_dt']);
        if ($avaliacoes->isEmpty()) {
            return [];
        }

        $conceitos = self::conceitos();
        $notaMap   = self::notas($avaliacoes->pluck('ava_id')->all());
        $hoje      = now()->startOfDay();
        $futura    = fn ($a) => $a->ava_dt && Carbon::parse($a->ava_dt)->startOfDay()->gt($hoje);

        // Modo do conceito por unidade (normalmente o mesmo ano, mas resolve por uni).
        $modoPorUni = [];
        foreach (array_unique($uniIds) as $u) {
            $modoPorUni[$u] = self::conceitoModo((int) $u);
        }

        $out = [];
        foreach ($avaliacoes->groupBy(fn ($a) => $a->ava_dis_id.'|'.$a->ava_uni_id) as $chave => $avs) {
            [$disId, $uniId] = array_map('intval', explode('|', (string) $chave));
            $modo = $modoPorUni[$uniId] ?? 'faixa';
            foreach ($alunos as $alnId) {
                $r = self::resultadoAluno($avs, $notaMap, $alnId, $modo, $conceitos, $futura);
                if (($r['completo'] ?? false) && ($r['valor'] !== null || ($r['conceito']['cnc_id'] ?? null) !== null)) {
                    $out[$disId][$alnId][$uniId] = [
                        'media'    => $r['valor'],
                        'cnc_id'   => $r['conceito']['cnc_id'] ?? null,
                        'tipo'     => $r['tipo'],
                        'completo' => true,
                    ];
                }
            }
        }

        return $out;
    }

    /**
     * Médias lançadas manualmente (secretaria) para (turma, disciplina, unidade).
     *
     * @return array<int, array{tipo:?string, valor:?float, conceito:?array}>
     */
    private static function manual(int $turId, int $disId, int $uniId): array
    {
        $rows = DB::table('edu_nota_manual')
            ->where('nmn_tur_id', $turId)
            ->where('nmn_dis_id', $disId)
            ->where('nmn_uni_id', $uniId)
            ->whereNull('nmn_deleted_at')
            ->get(['nmn_aln_id', 'nmn_tipo', 'nmn_media', 'nmn_cnc_id']);

        if ($rows->isEmpty()) {
            return [];
        }

        $conceitos = self::conceitos();
        $out = [];
        foreach ($rows as $r) {
            $aln = (int) $r->nmn_aln_id;
            if ($r->nmn_cnc_id !== null) {
                // Conceitual direto.
                $out[$aln] = ['tipo' => 'conceitual', 'valor' => null, 'conceito' => self::conceitoArray(self::conceitoPorIdModel((int) $r->nmn_cnc_id, $conceitos))];
            } elseif ($r->nmn_media !== null && $r->nmn_tipo === 'conceitual') {
                // Conceitual por faixa (valor numérico → conceito).
                $v = self::roundMeio((float) $r->nmn_media);
                $out[$aln] = ['tipo' => 'conceitual', 'valor' => $v, 'conceito' => self::faixaDe($v, $conceitos)];
            } elseif ($r->nmn_media !== null) {
                // Numérica.
                $out[$aln] = ['tipo' => 'numerica', 'valor' => (float) $r->nmn_media, 'conceito' => null];
            }
        }

        return $out;
    }

    /**
     * Consolida resultados de várias unidades de uma disciplina (média anual).
     *
     * @param  array<int, array{tipo:?string, valor:?float, conceito:?array}>  $porUnidade  resultados [uni_id => result] de UM aluno
     * @return array{tipo:?string, valor:?float, conceito:?array}
     */
    public static function consolidar(array $porUnidade): array
    {
        $validos = array_filter($porUnidade, fn ($r) => $r['tipo'] !== null);
        if (empty($validos)) {
            return ['tipo' => null, 'valor' => null, 'conceito' => null];
        }

        $tipo = $validos[array_key_first($validos)]['tipo'];

        // Numérica e conceitual-faixa: média dos valores das unidades.
        $valores = array_values(array_filter(array_map(fn ($r) => $r['valor'], $validos), fn ($v) => $v !== null));
        $temConceitoDireto = $tipo === 'conceitual' && collect($validos)->every(fn ($r) => $r['valor'] === null);

        if ($temConceitoDireto) {
            // Média dos pesos dos conceitos das unidades → conceito.
            $pesos = array_values(array_filter(array_map(fn ($r) => $r['conceito']['peso'] ?? null, $validos), fn ($p) => $p !== null));
            if (empty($pesos)) {
                return ['tipo' => $tipo, 'valor' => null, 'conceito' => null];
            }
            $media = array_sum($pesos) / count($pesos);
            $conceito = self::conceitoPorPeso((int) round($media), self::conceitos());

            return ['tipo' => $tipo, 'valor' => null, 'conceito' => $conceito];
        }

        if (empty($valores)) {
            return ['tipo' => $tipo, 'valor' => null, 'conceito' => null];
        }

        if ($tipo === 'conceitual') {
            $media = self::roundMeio(array_sum($valores) / count($valores));

            return ['tipo' => $tipo, 'valor' => $media, 'conceito' => self::faixaDe($media, self::conceitos())];
        }

        // Numérica: arredonda a média final ao 0,5 mais próximo.
        $media = self::roundMeio(array_sum($valores) / count($valores));

        return ['tipo' => $tipo, 'valor' => $media, 'conceito' => null];
    }

    // ───────────────────────────── internals ─────────────────────────────

    private static function resultadoAluno($avaliacoes, array $notaMap, int $alnId, string $modo, $conceitos, callable $futura): array
    {
        // Tipo do aluno: o tipo das avaliações em que ele tem nota lançada.
        $tipo = null;
        foreach ($avaliacoes as $a) {
            $n = $notaMap[$a->ava_id][$alnId] ?? null;
            if ($n && ($n['valor'] !== null || $n['cnc_id'] !== null)) {
                $tipo = $a->ava_tipo;
                break;
            }
        }
        if ($tipo === null) {
            return ['tipo' => null, 'valor' => null, 'conceito' => null, 'completo' => false];
        }

        // Migradas (trazidas de outra turma) contam normalmente na média e são editáveis.
        $doTipo      = $avaliacoes->where('ava_tipo', $tipo);
        $regulares   = $doTipo->where('ava_fl_recuperacao', false)->reject($futura);
        $recuperacao = $doTipo->where('ava_fl_recuperacao', true)->reject($futura);

        // Completo = aluno tem nota em TODAS as avaliações regulares não-futuras.
        // (Recuperação é opcional, não conta.) Usado para auto-preencher a média
        // na tela de lançamento manual da secretaria.
        $completo = $regulares->isNotEmpty() && $regulares->every(function ($a) use ($notaMap, $alnId) {
            $n = $notaMap[$a->ava_id][$alnId] ?? null;

            return $n && ($n['valor'] !== null || $n['cnc_id'] !== null);
        });

        if ($tipo === DiarioAvaliacao::TIPO_NUMERICA) {
            if ($regulares->isEmpty()) {
                return ['tipo' => $tipo, 'valor' => null, 'conceito' => null, 'completo' => false];
            }
            $soma = 0.0;
            foreach ($regulares as $a) {
                $soma += (float) ($notaMap[$a->ava_id][$alnId]['valor'] ?? 0);
            }
            // Recuperação: usa a nota da recuperação se for MAIOR que a média anterior.
            $recNota = null;
            foreach ($recuperacao as $a) {
                $v = $notaMap[$a->ava_id][$alnId]['valor'] ?? null;
                if ($v !== null && ($recNota === null || (float) $v > $recNota)) {
                    $recNota = (float) $v;
                }
            }
            $total = ($recNota !== null && $recNota > $soma) ? $recNota : $soma;

            // Numérica: média final arredondada ao 0,5.
            return ['tipo' => $tipo, 'valor' => self::roundMeio($total), 'conceito' => null, 'completo' => $completo];
        }

        // conceitual
        $base = $modo === 'faixa'
            ? self::baseFaixa($regulares, $notaMap, $alnId, $conceitos)
            : self::baseConceito($regulares, $notaMap, $alnId, $conceitos);

        $rec = self::melhorRecuperacao($recuperacao, $notaMap, $alnId, $modo, $conceitos);

        // recuperação sobrescreve só se for melhor
        $conceito = $base['conceito'];
        $valor    = $base['valor'];
        if ($rec && (! $conceito || ($rec['conceito']['peso'] ?? 0) > ($conceito['peso'] ?? 0))) {
            $conceito = $rec['conceito'];
            $valor    = $rec['valor'];
        }

        return ['tipo' => $tipo, 'valor' => $valor, 'conceito' => $conceito, 'completo' => $completo];
    }

    private static function baseFaixa($regulares, array $notaMap, int $alnId, $conceitos): array
    {
        if ($regulares->isEmpty()) {
            return ['valor' => null, 'conceito' => null];
        }
        $soma = 0.0;
        foreach ($regulares as $a) {
            $soma += (float) ($notaMap[$a->ava_id][$alnId]['valor'] ?? 0);
        }
        $soma = self::roundMeio($soma);

        return ['valor' => $soma, 'conceito' => self::faixaDe($soma, $conceitos)];
    }

    private static function baseConceito($regulares, array $notaMap, int $alnId, $conceitos): array
    {
        $counts = [];
        $pesos  = [];
        foreach ($regulares as $a) {
            $cid = $notaMap[$a->ava_id][$alnId]['cnc_id'] ?? null;
            if ($cid) {
                $counts[$cid] = ($counts[$cid] ?? 0) + 1;
                $pesos[] = self::pesoPorId($cid, $conceitos);
            }
        }
        if (empty($counts)) {
            return ['valor' => null, 'conceito' => null];
        }

        $max  = max($counts);
        $tied = array_keys(array_filter($counts, fn ($c) => $c === $max));

        if (count($tied) === 1) {
            return ['valor' => null, 'conceito' => self::conceitoArray(self::conceitoPorIdModel((int) $tied[0], $conceitos))];
        }

        // empate → média dos pesos arredondada
        $media = array_sum($pesos) / count($pesos);

        return ['valor' => null, 'conceito' => self::conceitoPorPeso((int) round($media), $conceitos)];
    }

    private static function melhorRecuperacao($recuperacao, array $notaMap, int $alnId, string $modo, $conceitos): ?array
    {
        $best = null;
        foreach ($recuperacao as $a) {
            $conceito = null;
            $valor    = null;
            if ($modo === 'faixa') {
                $v = $notaMap[$a->ava_id][$alnId]['valor'] ?? null;
                if ($v !== null) {
                    $valor = self::roundMeio((float) $v);
                    $conceito = self::faixaDe($valor, $conceitos);
                }
            } else {
                $cid = $notaMap[$a->ava_id][$alnId]['cnc_id'] ?? null;
                if ($cid) {
                    $conceito = self::conceitoArray(self::conceitoPorIdModel((int) $cid, $conceitos));
                }
            }
            if ($conceito && (! $best || ($conceito['peso'] ?? 0) > ($best['conceito']['peso'] ?? 0))) {
                $best = ['valor' => $valor, 'conceito' => $conceito];
            }
        }

        return $best;
    }

    // ───────────────────────────── lookups ─────────────────────────────

    private static function conceitoModo(int $uniId): string
    {
        $modo = DB::table('cfg_unidade as u')
            ->join('cfg_ano_letivo as a', 'a.anl_id', '=', 'u.uni_anl_id')
            ->where('u.uni_id', $uniId)
            ->value('a.anl_conceito_modo');

        return $modo === 'conceito' ? 'conceito' : 'faixa';
    }

    private static function conceitos()
    {
        return Conceito::query()->orderBy('cnc_peso')->get(['cnc_id', 'cnc_sigla', 'cnc_descricao', 'cnc_limite_inferior', 'cnc_limite_superior', 'cnc_peso']);
    }

    private static function notas(array $avaIds): array
    {
        $map = [];
        foreach (DiarioNota::query()->whereIn('nta_ava_id', $avaIds ?: [0])->get(['nta_ava_id', 'nta_aln_id', 'nta_valor', 'nta_cnc_id']) as $n) {
            $map[$n->nta_ava_id][$n->nta_aln_id] = [
                'valor'  => $n->nta_valor === null ? null : (float) $n->nta_valor,
                'cnc_id' => $n->nta_cnc_id,
            ];
        }

        return $map;
    }

    /** @return array<int> aln_ids ativos da turma */
    public static function alunos(int $turId): array
    {
        return Matricula::query()
            ->where('tma_tur_id', $turId)
            ->where('tma_situacao', Matricula::SITUACAO_ATIVA)
            ->whereNull('tma_deleted_at')
            ->pluck('tma_aln_id')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    private static function faixaDe(float $total, $conceitos): ?array
    {
        // Maior conceito cujo limite inferior ≤ total (faixas contíguas, sem vãos).
        $lista = $conceitos->sortBy(fn ($c) => (float) $c->cnc_limite_inferior)->values();
        $res = null;
        foreach ($lista as $c) {
            if ($total >= (float) $c->cnc_limite_inferior) {
                $res = $c;
            }
        }

        return self::conceitoArray($res ?? $lista->first());
    }

    private static function conceitoPorPeso(int $peso, $conceitos): ?array
    {
        if ($conceitos->isEmpty()) {
            return null;
        }
        $c = $conceitos->reduce(fn ($best, $c) => abs($c->cnc_peso - $peso) < abs($best->cnc_peso - $peso) ? $c : $best, $conceitos->first());

        return self::conceitoArray($c);
    }

    private static function conceitoPorIdModel(int $id, $conceitos)
    {
        return $conceitos->firstWhere('cnc_id', $id);
    }

    private static function pesoPorId(int $id, $conceitos): int
    {
        return (int) ($conceitos->firstWhere('cnc_id', $id)?->cnc_peso ?? 0);
    }

    /** Arredonda ao múltiplo de 0,5 mais próximo (1 casa, termina em 0 ou 5). */
    private static function roundMeio(float $v): float
    {
        return round($v * 2) / 2;
    }

    private static function conceitoArray($c): ?array
    {
        if (! $c) {
            return null;
        }

        return ['cnc_id' => (int) $c->cnc_id, 'sigla' => $c->cnc_sigla, 'descricao' => $c->cnc_descricao, 'peso' => (int) $c->cnc_peso];
    }
}
