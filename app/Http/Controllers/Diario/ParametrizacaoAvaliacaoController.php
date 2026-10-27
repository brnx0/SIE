<?php

namespace App\Http\Controllers\Diario;

use App\Http\Controllers\Controller;
use App\Models\Diario\InstrumentoAvaliativo;
use App\Models\Parametro\AnoLetivo;
use App\Models\Parametro\ParametrizacaoAvaliacao;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Diário › Cadastro › Parametrização de avaliações.
 *
 * Por UNIDADE letiva (rede inteira): quais instrumentos avaliativos a unidade terá
 * e o valor máximo de cada um, com opção de bloquear o valor para o professor.
 * As regras de uso no diário ficam em RegrasParametrizacaoAvaliacao.
 * Acesso governado pela matriz de telas (/diario/parametrizacao-avaliacoes).
 */
class ParametrizacaoAvaliacaoController extends Controller
{
    public function index(Request $request): Response
    {
        $anosLetivos = AnoLetivo::orderByDesc('anl_ano')->get(['anl_id', 'anl_ano', 'anl_fl_em_exercicio']);
        $anlId = $request->integer('anl_id')
            ?: ($anosLetivos->firstWhere('anl_fl_em_exercicio', true)?->anl_id ?? $anosLetivos->first()?->anl_id);

        $parametrizadas = ParametrizacaoAvaliacao::query()->has('itens')->pluck('pav_uni_id')->all();

        $unidades = DB::table('cfg_unidade')
            ->where('uni_anl_id', $anlId)
            ->orderBy('uni_numero')
            ->get(['uni_id', 'uni_numero', 'uni_tipo', 'uni_dt_inicio', 'uni_dt_fim'])
            ->map(fn ($u) => [
                'uni_id'         => (int) $u->uni_id,
                'label'          => $u->uni_numero . 'º ' . ucfirst(mb_strtolower((string) $u->uni_tipo)),
                'dt_inicio'      => $u->uni_dt_inicio,
                'dt_fim'         => $u->uni_dt_fim,
                'parametrizada'  => in_array((int) $u->uni_id, $parametrizadas, true),
            ]);

        $uniId = $request->integer('uni_id');
        if (! $unidades->contains('uni_id', $uniId)) {
            // Padrão: unidade em andamento; senão a primeira do ano.
            $hoje = now()->toDateString();
            $uniId = $unidades->first(fn ($u) => $u['dt_inicio'] && $u['dt_fim'] && $hoje >= substr($u['dt_inicio'], 0, 10) && $hoje <= substr($u['dt_fim'], 0, 10))['uni_id']
                ?? $unidades->first()['uni_id'] ?? null;
        }

        $pav = $uniId
            ? ParametrizacaoAvaliacao::query()->where('pav_uni_id', $uniId)->with(['itens', 'updatedBy:id,name'])->first()
            : null;
        $itens = $pav ? $pav->itens->mapWithKeys(fn ($i) => [(int) $i->pai_iav_id => (float) $i->pai_valor]) : collect();

        // Ativos + os já parametrizados (mesmo que inativados depois), para não sumirem.
        $instrumentos = InstrumentoAvaliativo::query()
            ->where(fn ($q) => $q->where('iav_fl_ativo', true)->orWhereIn('iav_id', $itens->keys()->all()))
            ->orderBy('iav_nome')
            ->get(['iav_id', 'iav_nome', 'iav_fl_recuperacao', 'iav_fl_ativo'])
            ->map(fn ($i) => [
                'iav_id'             => (int) $i->iav_id,
                'iav_nome'           => $i->iav_nome,
                'iav_fl_recuperacao' => (bool) $i->iav_fl_recuperacao,
                'iav_fl_ativo'       => (bool) $i->iav_fl_ativo,
                'selecionado'        => $itens->has((int) $i->iav_id),
                'valor'              => $itens->get((int) $i->iav_id),
            ]);

        return Inertia::render('diario/parametrizacao-avaliacoes/Index', [
            'anosLetivos'  => $anosLetivos,
            'anlId'        => $anlId,
            'unidades'     => $unidades->values(),
            'uniId'        => $uniId,
            'instrumentos' => $instrumentos->values(),
            'bloqueado'    => (bool) ($pav?->pav_fl_bloqueado ?? false),
            'updatedAt'    => $pav?->pav_updated_at?->toIso8601String(),
            'updatedBy'    => $pav?->updatedBy?->name,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'uni_id'        => ['required', 'integer', 'exists:cfg_unidade,uni_id'],
            'bloqueado'     => ['required', 'boolean'],
            'selecionados'  => ['array'],
            'selecionados.*' => ['integer', 'exists:edu_instrumento_avaliativo,iav_id'],
        ]);

        $selecionados = array_values(array_unique(array_map('intval', $data['selecionados'] ?? [])));

        // Valor por instrumento em chave plana (valor_<iav_id>) = id do input no form,
        // para o foco no primeiro campo inválido funcionar (useErrorFocus).
        $rules = [];
        $attributes = [];
        foreach ($selecionados as $iavId) {
            $rules["valor_{$iavId}"] = ['required', 'numeric', 'min:0.1', 'max:10'];
            $attributes["valor_{$iavId}"] = 'valor';
        }
        $valores = $request->validate($rules, [], $attributes);

        foreach ($selecionados as $iavId) {
            // Mesmo passo do diário: 1 casa decimal.
            if (round((float) $valores["valor_{$iavId}"], 1) != (float) $valores["valor_{$iavId}"]) {
                throw ValidationException::withMessages(["valor_{$iavId}" => 'Use no máximo 1 casa decimal.']);
            }
        }

        $uniId = (int) $data['uni_id'];

        DB::transaction(function () use ($uniId, $data, $selecionados, $valores, $request) {
            $pav = ParametrizacaoAvaliacao::query()->where('pav_uni_id', $uniId)->first();

            // Nenhum instrumento: a unidade volta ao comportamento livre.
            if (! $selecionados) {
                $pav?->delete();
                return;
            }

            $pav ??= new ParametrizacaoAvaliacao(['pav_uni_id' => $uniId]);
            $pav->pav_fl_bloqueado = (bool) $data['bloqueado'];
            $pav->pav_updated_by_id = $request->user()->id;
            $pav->save();

            $pav->itens()->whereNotIn('pai_iav_id', $selecionados)->delete();
            foreach ($selecionados as $iavId) {
                $pav->itens()->updateOrCreate(
                    ['pai_iav_id' => $iavId],
                    ['pai_valor' => round((float) $valores["valor_{$iavId}"], 1)],
                );
            }
        });

        return to_route('diario.parametrizacao-avaliacoes.index', [
            'anl_id' => DB::table('cfg_unidade')->where('uni_id', $uniId)->value('uni_anl_id'),
            'uni_id' => $uniId,
        ])->with('success', $selecionados ? 'Parametrização salva.' : 'Parametrização removida — unidade sem restrição de instrumentos.');
    }
}
