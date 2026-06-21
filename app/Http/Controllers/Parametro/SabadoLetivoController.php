<?php

namespace App\Http\Controllers\Parametro;

use App\Http\Controllers\Controller;
use App\Http\Requests\Parametro\StoreSabadoLetivoRequest;
use App\Models\Escola\Escola;
use App\Models\Parametro\AnoLetivo;
use App\Models\Parametro\SabadoLetivo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SabadoLetivoController extends Controller
{
    public function index(Request $request): Response
    {
        $anosLetivos = AnoLetivo::orderByDesc('anl_ano')
            ->get(['anl_id', 'anl_ano', 'anl_fl_em_exercicio', 'anl_dt_inicio_ano', 'anl_dt_fim']);

        $anoLetivoId = $request->integer('anl_id') ?: $anosLetivos->firstWhere('anl_fl_em_exercicio', true)?->anl_id
            ?? $anosLetivos->first()?->anl_id;

        $ano = $anosLetivos->firstWhere('anl_id', $anoLetivoId);

        $sabados = [];
        $sabadosDisponiveis = [];
        if ($anoLetivoId) {
            $sabados = SabadoLetivo::where('sbl_anl_id', $anoLetivoId)
                ->with('escolasExcluidas:esc_id')
                ->orderBy('sbl_dt_sabado')
                ->get()
                ->map(fn($s) => [
                    'sbl_id'            => $s->sbl_id,
                    'sbl_dt_sabado'     => $s->sbl_dt_sabado->format('Y-m-d'),
                    'sbl_dia_semana'    => $s->sbl_dia_semana,
                    'escolas_excluidas' => $s->escolasExcluidas->pluck('esc_id')->all(),
                ])
                ->all();

            // Gera todos os sábados dentro do período letivo, exceto os já cadastrados.
            $jaCadastrados = array_column($sabados, 'sbl_dt_sabado');
            $sabadosDisponiveis = $this->sabadosNoPeriodo(
                $ano?->anl_dt_inicio_ano,
                $ano?->anl_dt_fim,
                $jaCadastrados,
            );
        }

        return Inertia::render('sabados-letivos/Index', [
            'anosLetivos'        => $anosLetivos,
            'anoLetivoId'        => $anoLetivoId,
            'sabados'            => $sabados,
            'sabadosDisponiveis' => $sabadosDisponiveis,
            'escolas'            => Escola::where('esc_fl_ativo', true)
                ->orderBy('esc_nome')
                ->get(['esc_id', 'esc_nome']),
        ]);
    }

    /**
     * Lista de datas 'Y-m-d' de todos os sábados entre início/fim, fora os já usados.
     *
     * @param  array<int,string>  $excluir
     * @return array<int,string>
     */
    private function sabadosNoPeriodo($inicio, $fim, array $excluir): array
    {
        if (!$inicio || !$fim) {
            return [];
        }

        $cursor = \Carbon\Carbon::parse($inicio)->startOfDay();
        $fim    = \Carbon\Carbon::parse($fim)->startOfDay();

        // Avança até o primeiro sábado (Carbon: 6 = sábado).
        while ($cursor->dayOfWeek !== \Carbon\Carbon::SATURDAY && $cursor->lte($fim)) {
            $cursor->addDay();
        }

        $excluir = array_flip($excluir);
        $out = [];
        while ($cursor->lte($fim)) {
            $d = $cursor->format('Y-m-d');
            if (!isset($excluir[$d])) {
                $out[] = $d;
            }
            $cursor->addWeek();
        }

        return $out;
    }

    public function store(StoreSabadoLetivoRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $excluidas = $data['escolas_excluidas'] ?? [];
        unset($data['escolas_excluidas']);

        $data['sbl_created_by_id'] = auth()->id();
        $data['sbl_updated_by_id'] = auth()->id();

        $sabado = SabadoLetivo::create($data);
        $sabado->escolasExcluidas()->sync($excluidas);

        return back()->with('success', 'Sábado letivo adicionado.');
    }

    /** Edita o dia espelhado e as escolas em exceção de um sábado já cadastrado. */
    public function update(Request $request, SabadoLetivo $sabadoLetivo): RedirectResponse
    {
        $data = $request->validate([
            'sbl_dia_semana'      => ['required', 'integer', 'between:1,5'],
            'escolas_excluidas'   => ['array'],
            'escolas_excluidas.*' => ['integer', 'exists:edu_escola,esc_id'],
        ]);

        $sabadoLetivo->update([
            'sbl_dia_semana'    => $data['sbl_dia_semana'],
            'sbl_updated_by_id' => auth()->id(),
        ]);
        $sabadoLetivo->escolasExcluidas()->sync($data['escolas_excluidas'] ?? []);

        return back()->with('success', 'Sábado letivo atualizado.');
    }

    public function destroy(SabadoLetivo $sabadoLetivo): RedirectResponse
    {
        $sabadoLetivo->delete();

        return back()->with('success', 'Sábado letivo removido.');
    }
}
