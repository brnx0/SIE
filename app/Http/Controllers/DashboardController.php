<?php

namespace App\Http\Controllers;

use App\Models\Parametro\ParametroEntidade;
use App\Models\User;
use App\Support\UserAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /** Rótulo do perfil por role (ordem = prioridade p/ cabeçalho). */
    private const PERFIL_LABEL = [
        'admin'               => 'Suporte / Administração',
        'gestor_secretaria'   => 'Gestão da Secretaria',
        'diretor'             => 'Direção Escolar',
        'secretaria_escola'   => 'Secretaria Escolar',
        'coordenador'         => 'Coordenação',
        'coordenador_interno' => 'Coordenação AEE',
        'pedagogico'          => 'Pedagógico',
        'professor_aee'       => 'Professor(a) AEE',
        'professor'           => 'Professor(a)',
        'monitor'             => 'Monitor(a)',
        'matricula_censo'     => 'Matrícula / Censo',
    ];

    public function index(Request $request): Response
    {
        $user   = $request->user();
        $roles  = $user?->roles ?? [];
        $has    = fn (string $r) => in_array($r, $roles, true);

        $anlId     = $this->anoLetivoId();
        $escolaIds = UserAccess::escolasIds($user); // null = todas (rede)

        $kpis      = [];
        $shortcuts = [];
        $panels    = [];

        // ── Rede (admin / gestor) ────────────────────────────────────────────
        if ($has('admin') || $has('gestor_secretaria')) {
            $rede = $this->statsRede($anlId);
            $kpis['alunos']       = $this->kpi('alunos', 'Alunos matriculados', $rede['alunos'], 'graduation-cap', 'sky', '/alunos');
            $kpis['funcionarios'] = $this->kpi('funcionarios', 'Funcionários ativos', $rede['funcionarios'], 'school', 'teal', '/funcionarios');
            $kpis['turmas']       = $this->kpi('turmas', 'Turmas em andamento', $rede['turmas'], 'book-marked', 'amber', '/turmas');
            $kpis['escolas']      = $this->kpi('escolas', 'Escolas ativas', $rede['escolas'], 'users', 'rose', '/escolas');
            $kpis['vagas']        = $this->kpi('vagas', 'Vagas livres (rede)', $rede['vagas'], 'door-open', 'emerald');
            $kpis['lotadas']      = $this->kpi('lotadas', 'Turmas lotadas', $rede['lotadas'], 'alert-triangle', 'red', '/turmas');

            $shortcuts = array_merge($shortcuts, [
                $this->short('Cadastrar aluno', 'Registre um novo aluno', '/alunos/create', 'graduation-cap'),
                $this->short('Cadastrar funcionário', 'Professores e equipe', '/funcionarios/create', 'user-plus'),
                $this->short('Criar turma', 'Disciplinas, horários e professores', '/turmas/create', 'book-marked'),
                $this->short('Realizar matrícula', 'Matricule alunos nas turmas', '/matriculas', 'clipboard-list'),
            ]);

            $panels['config'] = $this->panelConfig();
        }

        // ── Escola (diretor / secretaria_escola) ─────────────────────────────
        if ($has('diretor') || $has('secretaria_escola')) {
            $esc = $this->statsEscola($anlId, $escolaIds);
            $kpis['alunos']  = $this->kpi('alunos', 'Alunos matriculados', $esc['alunos'], 'graduation-cap', 'sky', '/alunos');
            $kpis['turmas']  = $this->kpi('turmas', 'Turmas abertas', $esc['turmas'], 'book-marked', 'amber', '/turmas');
            $kpis['vagas']   = $this->kpi('vagas', 'Vagas livres', $esc['vagas'], 'door-open', 'emerald');
            $kpis['lotadas'] = $this->kpi('lotadas', 'Turmas lotadas', $esc['lotadas'], 'alert-triangle', 'red', '/turmas');
            $kpis['semprof'] = $this->kpi('semprof', 'Turmas sem professor', $esc['sem_prof'], 'user-plus', 'violet', '/turmas');

            $panels['lotadas'] = $this->panelTurmasLotadas($anlId, $escolaIds);

            $shortcuts = array_merge($shortcuts, [
                $this->short('Realizar matrícula', 'Matricule alunos nas turmas', '/matriculas', 'clipboard-list'),
                $this->short('Movimentar aluno', 'Transferências e situações', '/movimentacoes', 'arrow-right-left'),
                $this->short('Troca entre turmas', 'Remaneje alunos', '/remanejamento-turmas', 'arrow-right-left'),
            ]);

            if ($has('secretaria_escola')) {
                $shortcuts = array_merge($shortcuts, [
                    $this->short('Notas e Faltas', 'Lançamento manual', '/secretaria/lancamento-manual', 'clipboard-pen'),
                    $this->short('Justificativa de Falta', 'Registre justificativas', '/secretaria/justificativas-falta', 'file-text'),
                    $this->short('Acessos de Professores', 'Libere acesso ao diário', '/secretaria/acessos-professores', 'users'),
                ]);
            } else {
                $shortcuts[] = $this->short('Encerramento de Turmas', 'Feche o ano letivo', '/encerramento-turmas', 'calendar-days');
            }
        }

        // ── Professor / Monitor (diário) ─────────────────────────────────────
        if (($has('professor') || $has('monitor') || $has('professor_aee')) && $user?->fun_id) {
            $meu = $this->statsProfessor($user, $anlId);
            $kpis['minhas_turmas'] = $this->kpi('minhas_turmas', 'Minhas turmas', $meu['turmas'], 'book-marked', 'sky', '/diario');
        }

        if ($has('professor')) {
            $p = $this->statsPlanosProprios($user, $anlId);
            $kpis['planos_pend']   = $this->kpi('planos_pend', 'Planos aguardando validação', $p['pendente'], 'clipboard-check', 'amber', '/diario/planos');
            $kpis['planos_ajuste'] = $this->kpi('planos_ajuste', 'Planos devolvidos p/ ajuste', $p['correcao'], 'alert-triangle', 'red', '/diario/planos');
            $panels['meus_planos'] = $this->panelPlanosProprios($user, $anlId);

            $shortcuts = array_merge($shortcuts, [
                $this->short('Diário de Classe', 'Frequência e conteúdos', '/diario', 'clipboard-list'),
                $this->short('Planos de Aula', 'Crie e acompanhe seus planos', '/diario/planos', 'file-text'),
            ]);
        }

        if ($has('professor_aee')) {
            $shortcuts = array_merge($shortcuts, [
                $this->short('Diário AEE', 'Atendimentos especializados', '/diario-aee', 'stethoscope'),
                $this->short('Planos de Aula AEE', 'Crie e acompanhe planos AEE', '/diario/planos-aee', 'file-text'),
            ]);
        }

        if ($has('monitor')) {
            $shortcuts[] = $this->short('Diário Atividade', 'Frequência das atividades', '/diario-atividade', 'activity');
        }

        // ── Validação de planos (coordenador / coord_interno / pedagogico) ───
        if ($has('coordenador') || $has('pedagogico')) {
            $kpis['fila_aula'] = $this->kpi('fila_aula', 'Planos de aula pendentes', $this->countPlanosAula($anlId, $escolaIds), 'clipboard-check', 'amber', '/coordenador/planos');
            $panels['fila_aula'] = $this->panelFilaAula($anlId, $escolaIds);
            $shortcuts[] = $this->short('Validação de Planos', 'Aprove os planos de aula', '/coordenador/planos', 'clipboard-check');
        }

        if ($has('coordenador_interno') || $has('pedagogico')) {
            $kpis['fila_aee'] = $this->kpi('fila_aee', 'Planos AEE pendentes', $this->countPlanosAee($anlId, $escolaIds), 'clipboard-check', 'violet', '/coordenador-interno/planos-aee');
            $panels['fila_aee'] = $this->panelFilaAee($anlId, $escolaIds);
            $shortcuts[] = $this->short('Validação de Planos AEE', 'Aprove os planos AEE', '/coordenador-interno/planos-aee', 'clipboard-check');
        }

        // ── Matrícula / Censo ────────────────────────────────────────────────
        if ($has('matricula_censo')) {
            $esc = $this->statsEscola($anlId, $escolaIds);
            $kpis['alunos'] = $this->kpi('alunos', 'Alunos matriculados', $esc['alunos'], 'graduation-cap', 'sky', '/alunos');
            $kpis['turmas'] = $this->kpi('turmas', 'Turmas abertas', $esc['turmas'], 'book-marked', 'amber', '/turmas');
            $kpis['vagas']  = $this->kpi('vagas', 'Vagas livres', $esc['vagas'], 'door-open', 'emerald');
            $shortcuts = array_merge($shortcuts, [
                $this->short('Realizar matrícula', 'Matricule alunos nas turmas', '/matriculas', 'clipboard-list'),
                $this->short('2ª Via de Comprovante', 'Reimprima comprovantes', '/matriculas/segunda-via', 'file-text'),
            ]);
        }

        return Inertia::render('Dashboard', [
            'perfil'    => $this->perfilPrincipal($roles),
            'contexto'  => $this->contexto($user, $escolaIds),
            'kpis'      => array_values($kpis),
            'shortcuts' => $this->dedupeShortcuts($shortcuts),
            'panels'    => array_values(array_filter($panels)),
        ]);
    }

    // ── Helpers de contrato ──────────────────────────────────────────────────

    private function kpi(string $key, string $label, $value, string $icon, string $tone, ?string $href = null): array
    {
        return compact('key', 'label', 'value', 'icon', 'tone', 'href');
    }

    private function short(string $title, string $desc, string $href, string $icon): array
    {
        return compact('title', 'desc', 'href', 'icon');
    }

    private function dedupeShortcuts(array $shortcuts): array
    {
        $seen = [];
        $out  = [];
        foreach ($shortcuts as $s) {
            if (isset($seen[$s['href']])) continue;
            $seen[$s['href']] = true;
            $out[] = $s;
        }
        return $out;
    }

    private function perfilPrincipal(array $roles): string
    {
        foreach (self::PERFIL_LABEL as $role => $label) {
            if (in_array($role, $roles, true)) return $label;
        }
        return 'Educador(a)';
    }

    private function contexto(?User $user, ?array $escolaIds): string
    {
        if ($escolaIds === null) return 'Rede completa';
        if (empty($escolaIds)) return 'Sem lotação ativa';
        if (count($escolaIds) === 1) {
            $nome = DB::table('edu_escola')->where('esc_id', $escolaIds[0])->value('esc_nome');
            return $nome ?: '1 escola';
        }
        return count($escolaIds) . ' escolas';
    }

    // ── Ano letivo (cache) ───────────────────────────────────────────────────

    private function anoLetivoId(): ?int
    {
        return Cache::remember('dash:anl_atual', 600, function () {
            // Ano letivo do ano-calendário atual; se ainda não existir (ex.: virou
            // 2027 e o ano letivo não foi criado), cai no último ano cadastrado.
            $id = DB::table('cfg_ano_letivo')->where('anl_ano', (int) now()->year)->value('anl_id')
                ?? DB::table('cfg_ano_letivo')->orderByDesc('anl_ano')->value('anl_id');
            return $id ? (int) $id : null;
        });
    }

    private function escKey(?array $escolaIds): string
    {
        if ($escolaIds === null) return 'rede';
        if (empty($escolaIds)) return 'vazio';
        sort($escolaIds);
        return implode('-', $escolaIds);
    }

    // ── Estatísticas de rede (cache 300s) ────────────────────────────────────

    private function statsRede(?int $anlId): array
    {
        return Cache::remember("dash:rede:$anlId", 300, function () use ($anlId) {
            $turmas = $this->turmaAgregado($anlId, null);
            return [
                'alunos'       => $this->countAlunosAtivos($anlId, null),
                'funcionarios' => DB::table('edu_funcionario')->where('fun_fl_ativo', true)->whereNull('fun_deleted_at')->count(),
                'escolas'      => DB::table('edu_escola')->where('esc_fl_ativo', true)->whereNull('esc_deleted_at')->count(),
                'turmas'       => $turmas['turmas'],
                'vagas'        => $turmas['vagas'],
                'lotadas'      => $turmas['lotadas'],
            ];
        });
    }

    // ── Estatísticas por escola (cache 180s) ─────────────────────────────────

    private function statsEscola(?int $anlId, ?array $escolaIds): array
    {
        $key = $this->escKey($escolaIds);
        return Cache::remember("dash:escola:$anlId:$key", 180, function () use ($anlId, $escolaIds) {
            $turmas = $this->turmaAgregado($anlId, $escolaIds);
            return [
                'alunos'   => $this->countAlunosAtivos($anlId, $escolaIds),
                'turmas'   => $turmas['turmas'],
                'vagas'    => $turmas['vagas'],
                'lotadas'  => $turmas['lotadas'],
                'sem_prof' => $turmas['sem_prof'],
            ];
        });
    }

    /** Agregado de turmas abertas: total, vagas livres, lotadas, sem professor. */
    private function turmaAgregado(?int $anlId, ?array $escolaIds): array
    {
        $rows = DB::table('edu_turma as t')
            ->selectRaw(
                't.tur_capacidade as cap, coalesce(t.tur_qt_expansao, 0) as expansao,
                 (select count(*) from edu_turma_aluno tma
                    where tma.tma_tur_id = t.tur_id and tma.tma_situacao = ? and tma.tma_deleted_at is null) as ativos,
                 exists(select 1 from edu_turma_professor tp
                    where tp.tup_tur_id = t.tur_id and tp.tup_deleted_at is null) as tem_prof',
                ['ATIVA']
            )
            ->where('t.tur_situacao', 'ABERTA')
            ->whereNull('t.tur_deleted_at')
            ->when($anlId, fn ($q) => $q->where('t.tur_anl_id', $anlId))
            ->when($escolaIds !== null, fn ($q) => $q->whereIn('t.tur_esc_id', $escolaIds ?? []))
            ->get();

        $vagas = 0; $lotadas = 0; $semProf = 0;
        foreach ($rows as $r) {
            if (! $r->tem_prof) $semProf++;
            if ($r->cap === null) continue;
            $limite = (int) $r->cap + (int) $r->expansao;
            $vagas += max(0, $limite - (int) $r->ativos);
            if ((int) $r->ativos > $limite) $lotadas++;
        }

        return ['turmas' => $rows->count(), 'vagas' => $vagas, 'lotadas' => $lotadas, 'sem_prof' => $semProf];
    }

    private function countAlunosAtivos(?int $anlId, ?array $escolaIds): int
    {
        return DB::table('edu_turma_aluno as tma')
            ->when($escolaIds !== null, fn ($q) => $q
                ->join('edu_turma as t', 't.tur_id', '=', 'tma.tma_tur_id')
                ->whereIn('t.tur_esc_id', $escolaIds ?? []))
            ->where('tma.tma_situacao', 'ATIVA')
            ->whereNull('tma.tma_deleted_at')
            ->when($anlId, fn ($q) => $q->where('tma.tma_anl_id', $anlId))
            ->distinct()
            ->count('tma.tma_aln_id');
    }

    /** Lista de turmas abertas lotadas (ativos > capacidade + expansão). */
    private function panelTurmasLotadas(?int $anlId, ?array $escolaIds): ?array
    {
        $rows = DB::table('edu_turma as t')
            ->selectRaw(
                't.tur_id, t.tur_nome, t.tur_capacidade as cap, coalesce(t.tur_qt_expansao, 0) as expansao,
                 (select count(*) from edu_turma_aluno tma
                    where tma.tma_tur_id = t.tur_id and tma.tma_situacao = ? and tma.tma_deleted_at is null) as ativos',
                ['ATIVA']
            )
            ->where('t.tur_situacao', 'ABERTA')
            ->whereNull('t.tur_deleted_at')
            ->when($anlId, fn ($q) => $q->where('t.tur_anl_id', $anlId))
            ->when($escolaIds !== null, fn ($q) => $q->whereIn('t.tur_esc_id', $escolaIds ?? []))
            ->whereNotNull('t.tur_capacidade')
            ->get();

        $items = $rows
            ->filter(fn ($r) => (int) $r->ativos > ((int) $r->cap + (int) $r->expansao))
            ->sortByDesc(fn ($r) => (int) $r->ativos - ((int) $r->cap + (int) $r->expansao))
            ->take(6)
            ->map(fn ($r) => [
                'title' => "Turma {$r->tur_nome}",
                'meta'  => (int) $r->ativos . '/' . ((int) $r->cap + (int) $r->expansao) . ' matriculados',
                'href'  => '/turmas',
                'tone'  => 'red',
            ])->values()->all();

        return [
            'key'      => 'lotadas',
            'title'    => 'Turmas lotadas',
            'subtitle' => 'Acima da capacidade + expansão',
            'items'    => $items,
            'empty'    => 'Nenhuma turma acima da capacidade. 🎉',
        ];
    }

    // ── Professor ────────────────────────────────────────────────────────────

    private function statsProfessor(User $user, ?int $anlId): array
    {
        $turmas = DB::table('edu_turma_professor as tp')
            ->join('edu_turma as t', 't.tur_id', '=', 'tp.tup_tur_id')
            ->where('tp.tup_fun_id', $user->fun_id)
            ->whereNull('tp.tup_deleted_at')
            ->whereNull('t.tur_deleted_at')
            ->where('t.tur_situacao', 'ABERTA')
            ->when($anlId, fn ($q) => $q->where('t.tur_anl_id', $anlId))
            ->distinct()
            ->count('t.tur_id');

        return ['turmas' => $turmas];
    }

    private function statsPlanosProprios(?User $user, ?int $anlId): array
    {
        $base = DB::table('edu_diario_plano_aula')->where('dpa_user_id', $user?->id)
            ->when($anlId, fn ($q) => $q->where('dpa_anl_id', $anlId));
        return [
            'pendente' => (clone $base)->where('dpa_status', 'pendente')->count(),
            'correcao' => (clone $base)->where('dpa_status', 'correcao')->count(),
        ];
    }

    private function panelPlanosProprios(?User $user, ?int $anlId): ?array
    {
        $items = DB::table('edu_diario_plano_aula as p')
            ->leftJoin('edu_turma as t', 't.tur_id', '=', 'p.dpa_tur_id')
            ->where('p.dpa_user_id', $user?->id)
            ->when($anlId, fn ($q) => $q->where('p.dpa_anl_id', $anlId))
            ->where('p.dpa_status', 'correcao')
            ->orderByDesc('p.dpa_id')
            ->limit(5)
            ->get(['p.dpa_id', 'p.dpa_tema', 't.tur_nome'])
            ->map(fn ($r) => [
                'title' => $r->dpa_tema ?: 'Plano de aula',
                'meta'  => $r->tur_nome ? "Turma {$r->tur_nome}" : null,
                'href'  => '/diario/planos',
                'tone'  => 'red',
            ])->all();

        return [
            'key'      => 'meus_planos',
            'title'    => 'Planos devolvidos p/ ajuste',
            'subtitle' => 'Corrija e reenvie para validação',
            'items'    => $items,
            'empty'    => 'Nenhum plano aguardando ajuste.',
        ];
    }

    // ── Validação de planos ──────────────────────────────────────────────────

    private function countPlanosAula(?int $anlId, ?array $escolaIds): int
    {
        return DB::table('edu_diario_plano_aula')
            ->where('dpa_status', 'pendente')
            ->when($anlId, fn ($q) => $q->where('dpa_anl_id', $anlId))
            ->when($escolaIds !== null, fn ($q) => $q->whereIn('dpa_esc_id', $escolaIds ?? []))
            ->count();
    }

    private function countPlanosAee(?int $anlId, ?array $escolaIds): int
    {
        return DB::table('edu_diario_plano_aee')
            ->where('dae_status', 'pendente')
            ->when($anlId, fn ($q) => $q->where('dae_anl_id', $anlId))
            ->when($escolaIds !== null, fn ($q) => $q->whereIn('dae_esc_id', $escolaIds ?? []))
            ->count();
    }

    private function panelFilaAula(?int $anlId, ?array $escolaIds): ?array
    {
        $items = DB::table('edu_diario_plano_aula as p')
            ->join('users as u', 'u.id', '=', 'p.dpa_user_id')
            ->leftJoin('edu_turma as t', 't.tur_id', '=', 'p.dpa_tur_id')
            ->where('p.dpa_status', 'pendente')
            ->when($anlId, fn ($q) => $q->where('p.dpa_anl_id', $anlId))
            ->when($escolaIds !== null, fn ($q) => $q->whereIn('p.dpa_esc_id', $escolaIds ?? []))
            ->orderByDesc('p.dpa_id')
            ->limit(6)
            ->get(['u.name as professor', 't.tur_nome', 'p.dpa_tema'])
            ->map(fn ($r) => [
                'title' => $r->professor ?: 'Professor',
                'meta'  => trim(($r->tur_nome ? "Turma {$r->tur_nome}" : '') . ($r->dpa_tema ? " · {$r->dpa_tema}" : '')) ?: null,
                'href'  => '/coordenador/planos',
                'tone'  => 'amber',
            ])->all();

        return [
            'key'      => 'fila_aula',
            'title'    => 'Fila de validação — Planos de Aula',
            'subtitle' => 'Aguardando sua aprovação',
            'items'    => $items,
            'empty'    => 'Nenhum plano pendente. 🎉',
        ];
    }

    private function panelFilaAee(?int $anlId, ?array $escolaIds): ?array
    {
        $items = DB::table('edu_diario_plano_aee as p')
            ->join('users as u', 'u.id', '=', 'p.dae_user_id')
            ->leftJoin('edu_turma as t', 't.tur_id', '=', 'p.dae_tur_id')
            ->where('p.dae_status', 'pendente')
            ->when($anlId, fn ($q) => $q->where('p.dae_anl_id', $anlId))
            ->when($escolaIds !== null, fn ($q) => $q->whereIn('p.dae_esc_id', $escolaIds ?? []))
            ->orderByDesc('p.dae_id')
            ->limit(6)
            ->get(['u.name as professor', 't.tur_nome'])
            ->map(fn ($r) => [
                'title' => $r->professor ?: 'Professor AEE',
                'meta'  => $r->tur_nome ? "Turma {$r->tur_nome}" : null,
                'href'  => '/coordenador-interno/planos-aee',
                'tone'  => 'violet',
            ])->all();

        return [
            'key'      => 'fila_aee',
            'title'    => 'Fila de validação — Planos AEE',
            'subtitle' => 'Aguardando sua aprovação',
            'items'    => $items,
            'empty'    => 'Nenhum plano AEE pendente. 🎉',
        ];
    }

    // ── Config (cache 600s) — visão de suporte/gestão ────────────────────────

    private function panelConfig(): ?array
    {
        $data = Cache::remember('dash:config', 600, function () {
            $p   = ParametroEntidade::current();
            $anl = DB::table('cfg_ano_letivo')->where('anl_fl_em_exercicio', true)
                ->first(['anl_ano', 'anl_dt_corte']);

            $flag = fn (bool $v) => $v ? 'Sim' : 'Não';

            return [
                ['title' => 'Ano letivo em exercício', 'meta' => $anl->anl_ano ?? '— não definido'],
                ['title' => 'Data de corte (idade × série)', 'meta' => $anl && $anl->anl_dt_corte ? date('d/m/Y', strtotime($anl->anl_dt_corte)) : '—'],
                ['title' => 'CPF obrigatório', 'meta' => $flag((bool) $p->par_fl_cpf_obrigatorio)],
                ['title' => 'Validar idade × série', 'meta' => $flag((bool) $p->par_fl_validar_idade_serie)],
                ['title' => 'Gerar matrícula automática', 'meta' => $flag((bool) $p->par_fl_gerar_matricula_auto)],
                ['title' => 'Alertar homônimos', 'meta' => $flag((bool) $p->par_fl_alertar_homonimos)],
                ['title' => 'Validação de carga do professor', 'meta' => $p->par_tipo_validacao_carga ?: '—'],
            ];
        });

        return [
            'key'      => 'config',
            'title'    => 'Configuração da entidade',
            'subtitle' => 'Parâmetros que regem matrículas e validações',
            'items'    => $data,
            'empty'    => '',
        ];
    }
}
