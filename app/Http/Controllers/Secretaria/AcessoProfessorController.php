<?php

namespace App\Http\Controllers\Secretaria;

use App\Http\Controllers\Controller;
use App\Http\Requests\Secretaria\StoreAcessoProfessorRequest;
use App\Models\Funcionario\Funcionario;
use App\Models\User;
use App\Support\CargoDocente;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;
use Mpdf\Mpdf;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AcessoProfessorController extends Controller
{
    public function index(Request $request): Response
    {
        $user      = $request->user();
        $escolaIds = $this->escolasDoUsuario($user);

        $perPage = in_array((int) $request->input('per_page'), [10, 25, 50])
            ? (int) $request->input('per_page')
            : 10;

        $escolas = $this->escolasDisponiveis($escolaIds);

        $page = $this->baseQuery($request, $escolaIds)
            ->paginate($perPage)
            ->withQueryString();

        $page->getCollection()->transform(fn (Funcionario $f) => $this->montarLinha($f, $escolaIds));

        return Inertia::render('secretaria/AcessosProfessores/Index', [
            'professores' => $page,
            'escolas'     => $escolas->values(),
            'filters'     => [
                'search'   => $request->string('search')->toString(),
                'escola'   => $request->input('escola') ? (int) $request->input('escola') : null,
                'status'   => $request->string('status')->toString(),
                'per_page' => $perPage,
            ],
            'semVinculo'  => ! $user->isAdmin() && $escolaIds !== null && empty($escolaIds),
        ]);
    }

    public function gerar(StoreAcessoProfessorRequest $request): RedirectResponse
    {
        $user      = $request->user();
        $escolaIds = $this->escolasDoUsuario($user);
        $cargoIds  = CargoDocente::ids();

        $funIds = $request->validated()['fun_ids'];

        $criados = 0;
        $pulados = [];

        DB::transaction(function () use ($funIds, $escolaIds, $cargoIds, &$criados, &$pulados) {
            $funcionarios = Funcionario::query()
                ->whereIn('fun_id', $funIds)
                ->get();

            foreach ($funcionarios as $fun) {
                $info = $this->avaliarFuncionario($fun, $escolaIds, $cargoIds);

                if (! $info['pode_gerar']) {
                    $pulados[] = "{$fun->fun_nome}: {$info['motivo_label']}";
                    continue;
                }

                $usuario = User::create([
                    'name'     => $fun->fun_nome,
                    'login'    => $info['login'],
                    'email'    => $fun->fun_email,
                    'password' => Hash::make($info['login']), // senha = CPF
                    'active'   => true,
                    'fun_id'   => $fun->fun_id,
                ]);

                $usuario->syncRoles($info['roles']);
                $criados++;
            }
        });

        $msg = "{$criados} acesso(s) criado(s) com sucesso.";
        if (! empty($pulados)) {
            $msg .= ' Ignorados: ' . implode(' | ', $pulados);
        }

        return back()->with($criados > 0 ? 'success' : 'error', $msg);
    }

    public function export(Request $request): StreamedResponse|HttpResponse
    {
        $escolaIds = $this->escolasDoUsuario($request->user());

        $linhas = $this->baseQuery($request, $escolaIds)
            ->get()
            ->map(fn (Funcionario $f) => $this->montarLinha($f, $escolaIds));

        if ($request->input('format') === 'pdf') {
            return $this->exportPdf($linhas);
        }

        return $this->exportCsv($linhas);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Escopo
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Escolas que o usuário pode operar.
     * - Admin → null (todas)
     * - Secretária → ids das escolas onde possui lotação ATIVA
     * - Secretária sem fun_id/lotação → [] (nenhuma)
     *
     * @return array<int>|null
     */
    private function escolasDoUsuario(User $user): ?array
    {
        if ($user->isAdmin()) {
            return null;
        }

        if (! $user->fun_id) {
            return [];
        }

        return DB::table('edu_funcionario_lotacao as l')
            ->join('edu_funcionario_admissao as a', 'a.adm_id', '=', 'l.lot_adm_id')
            ->where('a.adm_fun_id', $user->fun_id)
            ->whereNull('a.adm_deleted_at')
            ->where('l.lot_fl_ativo', true)
            ->distinct()
            ->pluck('l.lot_esc_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function escolasDisponiveis(?array $escolaIds): Collection
    {
        return DB::table('edu_escola')
            ->when($escolaIds !== null, fn ($q) => $q->whereIn('esc_id', $escolaIds ?: [0]))
            ->where('esc_fl_ativo', true)
            ->whereNull('esc_deleted_at')
            ->orderBy('esc_nome')
            ->get(['esc_id', 'esc_nome']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Query de candidatos
    // ─────────────────────────────────────────────────────────────────────────

    private function baseQuery(Request $request, ?array $escolaIds)
    {
        $cargoIds   = CargoDocente::ids();
        $escolaFiltro = $request->input('escola') ? (int) $request->input('escola') : null;
        $search     = $request->string('search')->toString();
        $status     = $request->string('status')->toString();

        // Restringe o filtro de escola ao escopo do usuário.
        if ($escolaFiltro !== null && $escolaIds !== null && ! in_array($escolaFiltro, $escolaIds, true)) {
            $escolaFiltro = -1; // força lista vazia
        }

        $lotacaoExists = function ($q) use ($escolaIds, $cargoIds, $escolaFiltro, $status) {
            $q->from('edu_funcionario_lotacao as l')
                ->join('edu_funcionario_admissao as a', 'a.adm_id', '=', 'l.lot_adm_id')
                ->whereColumn('a.adm_fun_id', 'edu_funcionario.fun_id')
                ->whereNull('a.adm_deleted_at')
                ->whereIn('l.lot_crg_id', $cargoIds);

            if ($escolaIds !== null) {
                $q->whereIn('l.lot_esc_id', $escolaIds ?: [0]);
            }
            if ($escolaFiltro !== null) {
                $q->where('l.lot_esc_id', $escolaFiltro);
            }
            if ($status === 'nao_lotado') {
                $q->where('l.lot_fl_ativo', true);
            }
        };

        return Funcionario::query()
            ->where('fun_fl_ativo', true)
            ->whereExists($lotacaoExists)
            ->when($search !== '', function ($q) use ($search) {
                $digits = preg_replace('/\D/', '', $search);
                $q->where(function ($w) use ($search, $digits) {
                    $w->where('fun_nome', 'ilike', "%{$search}%");
                    if ($digits !== '') {
                        $w->orWhere('fun_cpf', 'like', "%{$digits}%");
                    }
                });
            })
            ->when($status === 'com_login', fn ($q) => $q->whereIn('fun_id', fn ($s) => $s->from('users')->select('fun_id')->whereNotNull('fun_id')))
            ->when($status === 'sem_login', fn ($q) => $q->whereNotIn('fun_id', fn ($s) => $s->from('users')->select('fun_id')->whereNotNull('fun_id')))
            ->when($status === 'nao_lotado', fn ($q) => $q->whereNotExists(function ($s) use ($escolaIds, $cargoIds) {
                $s->from('edu_funcionario_lotacao as l')
                    ->join('edu_funcionario_admissao as a', 'a.adm_id', '=', 'l.lot_adm_id')
                    ->whereColumn('a.adm_fun_id', 'edu_funcionario.fun_id')
                    ->whereNull('a.adm_deleted_at')
                    ->whereIn('l.lot_crg_id', $cargoIds)
                    ->where('l.lot_fl_ativo', true)
                    ->when($escolaIds !== null, fn ($qq) => $qq->whereIn('l.lot_esc_id', $escolaIds ?: [0]));
            }))
            ->orderBy('fun_nome');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Enriquecimento de linha
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Lotações docentes (no escopo) de um funcionário, agrupadas.
     */
    private function lotacoesDocentes(int $funId, ?array $escolaIds, array $cargoIds): Collection
    {
        return DB::table('edu_funcionario_lotacao as l')
            ->join('edu_funcionario_admissao as a', 'a.adm_id', '=', 'l.lot_adm_id')
            ->join('edu_escola as e', 'e.esc_id', '=', 'l.lot_esc_id')
            ->where('a.adm_fun_id', $funId)
            ->whereNull('a.adm_deleted_at')
            ->whereIn('l.lot_crg_id', $cargoIds)
            ->when($escolaIds !== null, fn ($q) => $q->whereIn('l.lot_esc_id', $escolaIds ?: [0]))
            ->get(['l.lot_esc_id', 'e.esc_nome', 'l.lot_fl_ativo', 'l.lot_funcoes_sala_aula']);
    }

    /**
     * Avalia elegibilidade + roles sugeridas de um funcionário.
     *
     * @return array{login:string,roles:array<string>,pode_gerar:bool,motivo:string,motivo_label:string,escolas:array<string>,tem_login:bool}
     */
    private function avaliarFuncionario(Funcionario $fun, ?array $escolaIds, array $cargoIds): array
    {
        $lotacoes  = $this->lotacoesDocentes($fun->fun_id, $escolaIds, $cargoIds);
        $ativas    = $lotacoes->where('lot_fl_ativo', true);

        $escolasAtivas = $ativas->pluck('esc_nome')->unique()->values()->all();
        $escolasExibe  = $escolasAtivas ?: $lotacoes->pluck('esc_nome')->unique()->values()->all();

        // Roles sugeridas a partir das lotações ativas.
        $temRegular = false;
        $temAee     = false;
        foreach ($ativas as $l) {
            $funcoes = $this->decodeFuncoes($l->lot_funcoes_sala_aula);
            if (in_array('Docente AEE', $funcoes, true)) {
                $temAee = true;
            } else {
                $temRegular = true;
            }
        }

        $roles = [];
        if ($temRegular) {
            $roles[] = 'professor';
        }
        if ($temAee) {
            $roles[] = 'professor_aee';
        }

        $cpf       = preg_replace('/\D/', '', (string) $fun->fun_cpf);
        $temLogin  = User::where('fun_id', $fun->fun_id)->exists();

        // Determina motivo de bloqueio (ordem de prioridade).
        $motivo = 'ok';
        if ($temLogin) {
            $motivo = 'com_login';
        } elseif ($ativas->isEmpty()) {
            $motivo = 'nao_lotado';
        } elseif ($cpf === '') {
            $motivo = 'sem_cpf';
        } elseif (User::where('login', $cpf)->exists()) {
            $motivo = 'cpf_em_uso';
        }

        return [
            'login'        => $cpf,
            'roles'        => $roles,
            'pode_gerar'   => $motivo === 'ok',
            'motivo'       => $motivo,
            'motivo_label' => self::MOTIVOS[$motivo] ?? $motivo,
            'escolas'      => $escolasExibe,
            'tem_login'    => $temLogin,
        ];
    }

    private function montarLinha(Funcionario $fun, ?array $escolaIds): array
    {
        $info = $this->avaliarFuncionario($fun, $escolaIds, CargoDocente::ids());

        return [
            'fun_id'       => (int) $fun->fun_id,
            'fun_nome'     => $fun->fun_nome,
            'fun_cpf'      => $fun->fun_cpf,
            'fun_email'    => $fun->fun_email,
            'escolas'      => $info['escolas'],
            'roles'        => $info['roles'],
            'tem_login'    => $info['tem_login'],
            'pode_gerar'   => $info['pode_gerar'],
            'motivo'       => $info['motivo'],
            'motivo_label' => $info['motivo_label'],
        ];
    }

    private function decodeFuncoes($raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }
        if (! $raw) {
            return [];
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    private const MOTIVOS = [
        'ok'         => 'Pronto para gerar',
        'com_login'  => 'Já possui login',
        'nao_lotado' => 'Funcionário não lotado',
        'sem_cpf'    => 'Sem CPF cadastrado',
        'cpf_em_uso' => 'CPF já usado como login',
    ];

    // ─────────────────────────────────────────────────────────────────────────
    // Exportações
    // ─────────────────────────────────────────────────────────────────────────

    private function exportCsv(Collection $linhas): StreamedResponse
    {
        $filename = 'acessos_professores_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($linhas) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($out, ['Nome', 'CPF', 'Escola(s)', 'Perfil sugerido', 'Situação'], ';');
            foreach ($linhas as $l) {
                fputcsv($out, [
                    $l['fun_nome'],
                    $l['fun_cpf'],
                    implode(', ', $l['escolas']),
                    implode(', ', array_map(fn ($r) => User::ROLES[$r] ?? $r, $l['roles'])),
                    $l['motivo_label'],
                ], ';');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function exportPdf(Collection $linhas): HttpResponse
    {
        $filename = 'acessos_professores_' . now()->format('Ymd_His') . '.pdf';

        $html = view('exports.acessos_professores_pdf', [
            'linhas' => $linhas,
            'roles'  => User::ROLES,
            'total'  => $linhas->count(),
        ])->render();

        $mpdf = new Mpdf(['orientation' => 'L', 'format' => 'A4', 'tempDir' => sys_get_temp_dir()]);
        $mpdf->WriteHTML($html);

        return response($mpdf->Output($filename, 'S'), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
