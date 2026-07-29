<?php

namespace App\Http\Controllers\Relatorio;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Relatorio\Concerns\NotaLookups;
use App\Models\Escola\Escola;
use App\Models\Matricula\Matricula;
use App\Models\Parametro\AnoLetivo;
use App\Models\Parametro\ParametroEntidade;
use App\Models\Turma\Turma;
use App\Support\Diario\FrequenciaMensal;
use App\Support\UserAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class FrequenciaMensalController extends Controller
{
    use NotaLookups;

    private const MESES = [
        1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril', 5 => 'Maio', 6 => 'Junho',
        7 => 'Julho', 8 => 'Agosto', 9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro',
    ];

    public function form(): Response
    {
        $user = auth()->user();

        return Inertia::render('relatorios/FrequenciaMensal/Form', [
            'anosLetivos' => AnoLetivo::orderByDesc('anl_ano')->get(['anl_id', 'anl_ano']),
            'escolas'     => UserAccess::escolasVisiveis($user),
            'userEscola'  => UserAccess::escolaDefault($user),
            'isAdmin'     => $user->isAdmin(),
            'meses'       => collect(self::MESES)->map(fn ($nome, $num) => ['id' => $num, 'label' => $nome])->values(),
        ]);
    }

    public function gerar(Request $request): Response
    {
        $data = $request->validate([
            'anl_id' => ['required', 'integer', 'exists:cfg_ano_letivo,anl_id'],
            'esc_id' => ['required', 'integer', 'exists:edu_escola,esc_id'],
            'tur_id' => ['required', 'integer', 'exists:edu_turma,tur_id'],
            'mes'    => ['required', 'integer', Rule::in(range(1, 12))],
        ]);

        $this->autorizarTurma((int) $data['tur_id']);

        $ano    = AnoLetivo::findOrFail($data['anl_id']);
        $escola = Escola::findOrFail($data['esc_id']);
        $turma  = Turma::with('serie:ser_id,ser_nome', 'segmento:seg_id,seg_nome_reduzido')->findOrFail($data['tur_id']);
        $mes    = (int) $data['mes'];

        $turmaSegNome = (string) ($turma->segmento?->seg_nome_reduzido ?? '');
        $turSerId     = (int) $turma->tur_ser_id;

        // Cálculo centralizado em App\Support\Diario\FrequenciaMensal — a mesma
        // classe que a API v1 do app usa, para não existirem duas fórmulas.
        $r = FrequenciaMensal::calcular((int) $turma->tur_id, (int) $ano->anl_id, (int) $ano->anl_ano, $mes);

        $totalAulas  = $r['total_aulas'];
        $diasLetivos = $r['dias_letivos'];
        $modosUsados = array_flip($r['modos']);
        $alunos      = collect($r['alunos']);

        // Turma "mista": multi-seriada explícita OU alunos com modos diferentes → mostra coluna de segmento.
        $multi = str_contains(mb_strtoupper(Str::ascii($turmaSegNome), 'UTF-8'), 'MULTI') || count($modosUsados) > 1;

        $p = ParametroEntidade::first();

        return Inertia::render('relatorios/FrequenciaMensal/Resultado', [
            'parametros' => $p ? ['nome_entidade' => $p->par_nome_entidade, 'municipio' => $p->municipio_label, 'logomarca_url' => $p->par_logomarca_url, 'brasao_url' => $p->par_brasao_url] : null,
            'filtros'    => [
                'anl_ano'   => $ano->anl_ano,
                'esc_nome'  => $escola->esc_nome,
                'turma'     => trim(($turma->serie?->ser_nome ? $turma->serie->ser_nome . ' - ' : '') . $turma->tur_nome),
                'segmento'  => $turmaSegNome,
                'mes_label' => (self::MESES[$mes] ?? '') . '/' . $ano->anl_ano,
            ],
            'multi'        => $multi,
            'dias_letivos' => $diasLetivos,
            'total_aulas'  => $totalAulas,
            'alunos'       => $alunos,
        ]);
    }

}
