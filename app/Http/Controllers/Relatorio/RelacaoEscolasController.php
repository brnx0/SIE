<?php

namespace App\Http\Controllers\Relatorio;

use App\Http\Controllers\Controller;
use App\Models\Escola\Escola;
use App\Models\Parametro\ParametroEntidade;
use App\Support\Telefone;
use App\Support\UserAccess;
use App\Support\XlsxExport;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Relação de Escolas — cadastro institucional da rede, em ordem alfabética.
 *
 * ATENÇÃO ao controle de acesso: este é o único relatório que precisa enxergar
 * escolas INATIVAS (o filtro de status é um requisito). Por isso ele NÃO usa
 * UserAccess::escolasVisiveis(), que embute `esc_fl_ativo = true`; usa
 * UserAccess::scopeEscolas(), que aplica só o recorte por lotação e deixa a
 * decisão sobre o status para o filtro da tela.
 */
class RelacaoEscolasController extends Controller
{
    /** Espelha resources/js/lib/escolaEnums.ts — resolvido aqui para valer também no XLSX/CSV. */
    private const SITUACAO_FUNC = [1 => 'Em atividade', 2 => 'Paralisada', 3 => 'Extinta'];

    private const DEP_ADMINISTRATIVA = [1 => 'Federal', 2 => 'Estadual', 3 => 'Municipal', 4 => 'Privada'];

    private const ZONAS = ['U' => 'Urbana', 'R' => 'Rural'];

    public function form(): Response
    {
        $user = auth()->user();

        // Só os bairros/segmentos que realmente aparecem nas escolas visíveis —
        // filtro com opção que não retorna nada é ruído.
        $escolasIds = $this->escolasIdsVisiveis($user);

        return Inertia::render('relatorios/RelacaoEscolas/Form', [
            'bairros' => DB::table('edu_bairro as b')
                ->join('edu_escola as e', 'e.esc_bai_id', '=', 'b.bai_id')
                ->whereNull('e.esc_deleted_at')
                ->when($escolasIds !== null, fn ($q) => $q->whereIn('e.esc_id', $escolasIds ?: [-1]))
                ->distinct()
                ->orderBy('b.bai_nome')
                ->get(['b.bai_id', 'b.bai_nome'])
                ->map(fn ($b) => ['bai_id' => (int) $b->bai_id, 'bai_nome' => $b->bai_nome]),

            'segmentos' => DB::table('edu_segmento as s')
                ->join('edu_escola_segmento as es', 'es.seg_id', '=', 's.seg_id')
                ->join('edu_escola as e', 'e.esc_id', '=', 'es.esc_id')
                ->whereNull('es.esg_deleted_at')
                ->where('es.esg_fl_ativo', true)
                ->whereNull('e.esc_deleted_at')
                ->when($escolasIds !== null, fn ($q) => $q->whereIn('e.esc_id', $escolasIds ?: [-1]))
                ->distinct()
                ->orderBy('s.seg_ordem')
                ->get(['s.seg_id', 's.seg_nome_reduzido', 's.seg_nome_completo'])
                ->map(fn ($s) => [
                    'seg_id'   => (int) $s->seg_id,
                    'seg_nome' => $s->seg_nome_reduzido ?: $s->seg_nome_completo,
                ]),
        ]);
    }

    public function gerar(Request $request): Response
    {
        [
            'parametros' => $parametros,
            'filtros'    => $filtros,
            'linhas'     => $linhas,
            'resumo'     => $resumo,
        ] = $this->montar($request);

        return Inertia::render('relatorios/RelacaoEscolas/Resultado', [
            'parametros' => $parametros,
            'filtros'    => $filtros,
            'linhas'     => $linhas,
            'resumo'     => $resumo,
        ]);
    }

    public function exportarXlsx(Request $request): \Illuminate\Http\Response
    {
        ['linhas' => $linhas] = $this->montar($request);

        $headers = [
            'Escola', 'Cód. da Escola', 'Cód. INEP', 'Tipo de Ensino',
            'Endereço', 'Bairro', 'Município/UF', 'CEP', 'Zona',
            'Telefone', 'Telefone 2', 'E-mail', 'Site',
            'Dependência Administrativa', 'Situação de Funcionamento', 'Status',
        ];

        $rows = $linhas->map(fn ($l) => [
            $l['esc_nome'], $l['esc_cd_escola'] ?? '', $l['esc_cd_inep'] ?? '', implode(', ', $l['tipos_ensino']),
            $l['logradouro'] ?? '', $l['bairro'] ?? '', $l['municipio'] ?? '', $l['cep'] ?? '', $l['zona'] ?? '',
            $l['telefone'] ?? '', $l['telefone_2'] ?? '', $l['email'] ?? '', $l['site'] ?? '',
            $l['dep_administrativa'] ?? '', $l['situacao_func'] ?? '', $l['status'],
        ])->all();

        return XlsxExport::download('relacao_escolas_' . now()->format('Ymd_His') . '.xlsx', $headers, $rows);
    }

    /**
     * Monta a relação de escolas (compartilhado entre tela e export).
     *
     * @return array{parametros: array|null, filtros: array, linhas: Collection, resumo: array}
     */
    private function montar(Request $request): array
    {
        $data = $request->validate([
            'seg_id' => ['nullable', 'integer', 'exists:edu_segmento,seg_id'],
            'bai_id' => ['nullable', 'integer', 'exists:edu_bairro,bai_id'],
            'status' => ['nullable', 'in:ativa,inativa,todas'],
        ]);

        $status = $data['status'] ?? 'todas';

        $query = Escola::query();
        // Recorte por lotação, SEM o filtro de ativo (ver docblock da classe).
        UserAccess::scopeEscolas($query, auth()->user(), 'esc_id');

        if ($status === 'ativa') {
            $query->where('esc_fl_ativo', true);
        } elseif ($status === 'inativa') {
            $query->where('esc_fl_ativo', false);
        }

        if (! empty($data['bai_id'])) {
            $query->where('esc_bai_id', (int) $data['bai_id']);
        }

        if (! empty($data['seg_id'])) {
            $query->whereExists(fn ($q) => $q
                ->select(DB::raw(1))
                ->from('edu_escola_segmento as es')
                ->whereColumn('es.esc_id', 'edu_escola.esc_id')
                ->where('es.seg_id', (int) $data['seg_id'])
                ->where('es.esg_fl_ativo', true)
                ->whereNull('es.esg_deleted_at'));
        }

        $escolas = $query->with([
            'bairro:bai_id,bai_nome',
            'municipio:mun_id,mun_nome,mun_uf',
        ])->get();

        $tipos = $this->tiposEnsinoPorEscola($escolas->pluck('esc_id')->all());

        $linhas = $escolas
            // Ordenação alfabética insensível a acento/caixa (requisito do relatório).
            ->sortBy(fn (Escola $e) => Str::ascii(mb_strtolower((string) $e->esc_nome)), SORT_STRING)
            ->values()
            ->map(fn (Escola $e) => [
                'esc_id'       => (int) $e->esc_id,
                'esc_nome'     => $e->esc_nome,
                'esc_cd_escola' => $e->esc_cd_escola,
                'esc_cd_inep'  => $e->esc_cd_inep,
                'tipos_ensino' => $tipos[(int) $e->esc_id] ?? [],
                'logradouro'   => $this->logradouro($e),
                'bairro'       => $e->bairro?->bai_nome,
                'municipio'    => $e->municipio
                    ? trim($e->municipio->mun_nome . ($e->municipio->mun_uf ? '/' . $e->municipio->mun_uf : ''))
                    : null,
                'endereco'     => $this->enderecoCompleto($e),
                'cep'          => $this->cep($e->esc_cep),
                'zona'         => self::ZONAS[$e->esc_zona] ?? null,
                'telefone'     => Telefone::comDdd($e->esc_ddd, $e->esc_telefone_fixo),
                'telefone_2'   => Telefone::comDdd($e->esc_ddd, $e->esc_telefone_2),
                'email'        => $e->esc_email,
                'site'         => $e->esc_site,
                'dep_administrativa' => self::DEP_ADMINISTRATIVA[$e->esc_dep_administrativa] ?? null,
                'situacao_func'      => self::SITUACAO_FUNC[$e->esc_situacao_func] ?? null,
                'ativa'        => (bool) $e->esc_fl_ativo,
                'status'       => $e->esc_fl_ativo ? 'Ativa' : 'Inativa',
            ]);

        $parametros = ParametroEntidade::first();

        return [
            'parametros' => $parametros ? $parametros->cabecalhoRelatorio() : null,
            'filtros' => [
                'status'   => $status,
                'seg_id'   => ! empty($data['seg_id']) ? (int) $data['seg_id'] : null,
                'seg_nome' => ! empty($data['seg_id'])
                    ? DB::table('edu_segmento')->where('seg_id', $data['seg_id'])->value('seg_nome_reduzido')
                    : null,
                'bai_id'   => ! empty($data['bai_id']) ? (int) $data['bai_id'] : null,
                'bai_nome' => ! empty($data['bai_id'])
                    ? DB::table('edu_bairro')->where('bai_id', $data['bai_id'])->value('bai_nome')
                    : null,
            ],
            'linhas' => $linhas,
            'resumo' => [
                'total'        => $linhas->count(),
                'ativas'       => $linhas->where('ativa', true)->count(),
                'inativas'     => $linhas->where('ativa', false)->count(),
                'em_atividade' => $linhas->where('situacao_func', 'Em atividade')->count(),
                'paralisadas'  => $linhas->where('situacao_func', 'Paralisada')->count(),
                'sem_tipo'     => $linhas->filter(fn ($l) => empty($l['tipos_ensino']))->count(),
            ],
        ];
    }

    /**
     * Tipos de ensino (segmentos ativos) por escola.
     *
     * @return array<int, array<int, string>>
     */
    private function tiposEnsinoPorEscola(array $escolasIds): array
    {
        if (! $escolasIds) {
            return [];
        }

        $out = [];
        $rows = DB::table('edu_escola_segmento as es')
            ->join('edu_segmento as s', 's.seg_id', '=', 'es.seg_id')
            ->whereIn('es.esc_id', $escolasIds)
            ->where('es.esg_fl_ativo', true)
            ->whereNull('es.esg_deleted_at')
            ->orderBy('s.seg_ordem')
            ->get(['es.esc_id', 's.seg_nome_reduzido', 's.seg_nome_completo']);

        foreach ($rows as $r) {
            $nome = $r->seg_nome_reduzido ?: $r->seg_nome_completo;
            $escId = (int) $r->esc_id;
            // Mesma escola pode ter 2 vínculos ao mesmo segmento (períodos distintos).
            if (! in_array($nome, $out[$escId] ?? [], true)) {
                $out[$escId][] = $nome;
            }
        }

        return $out;
    }

    /** IDs visíveis ao usuário; null = admin (sem recorte). */
    private function escolasIdsVisiveis($user): ?array
    {
        return UserAccess::escolasIds($user);
    }

    /** "Logradouro, nº, complemento". */
    private function logradouro(Escola $e): ?string
    {
        $partes = array_filter(
            [$e->esc_logradouro, $e->esc_numero, $e->esc_complemento],
            fn ($v) => $v !== null && $v !== '',
        );

        return $partes ? implode(', ', $partes) : null;
    }

    /** Endereço em linha única, para impressão. */
    private function enderecoCompleto(Escola $e): ?string
    {
        $municipio = $e->municipio
            ? trim($e->municipio->mun_nome . ($e->municipio->mun_uf ? '/' . $e->municipio->mun_uf : ''))
            : null;

        $partes = array_filter(
            [$this->logradouro($e), $e->bairro?->bai_nome, $municipio, $this->cep($e->esc_cep)],
            fn ($v) => $v !== null && $v !== '',
        );

        return $partes ? implode(' - ', $partes) : null;
    }

    /** 58000000 → 58000-000. */
    private function cep(?string $cep): ?string
    {
        $d = preg_replace('/\D/', '', $cep ?? '');

        return strlen($d) === 8 ? substr($d, 0, 5) . '-' . substr($d, 5) : ($d ?: null);
    }
}
