<?php

namespace App\Http\Controllers\Escola;

use App\Http\Controllers\Controller;
use App\Http\Requests\Escola\StoreEscolaSegmentoRequest;
use App\Models\Escola\Escola;
use App\Models\Escola\EscolaSegmento;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class EscolaSegmentoController extends Controller
{
    public function store(StoreEscolaSegmentoRequest $request, Escola $escola): RedirectResponse
    {
        $escola->segmentos()->create($request->validated());

        return to_route('escolas.edit', $escola)
            ->with('success', 'Etapa de ensino adicionada com sucesso.');
    }

    public function update(StoreEscolaSegmentoRequest $request, Escola $escola, int $esg): RedirectResponse
    {
        $escolaSegmento = EscolaSegmento::where('esg_id', $esg)
            ->where('esc_id', $escola->esc_id)
            ->firstOrFail();

        $escolaSegmento->update($request->validated());

        return to_route('escolas.edit', $escola)
            ->with('success', 'Etapa de ensino atualizada com sucesso.');
    }

    public function destroy(Escola $escola, int $esg): RedirectResponse
    {
        $escolaSegmento = EscolaSegmento::where('esg_id', $esg)
            ->where('esc_id', $escola->esc_id)
            ->firstOrFail();

        // Bloqueia se há turmas vinculadas ao segmento nesta escola (turma referencia
        // edu_segmento, não o pivot — safeDelete não pega essa FK).
        $qtdTurmas = DB::table('edu_turma')
            ->where('tur_esc_id', $escola->esc_id)
            ->where('tur_seg_id', $escolaSegmento->seg_id)
            ->whereNull('tur_deleted_at')
            ->count();

        if ($qtdTurmas > 0) {
            return back()->with('error', "Não é possível remover: há {$qtdTurmas} turma(s) vinculada(s) a esta etapa de ensino nesta escola. Remova as turmas antes de excluir a etapa de ensino.");
        }

        return $this->safeDelete($escolaSegmento)
            ?? to_route('escolas.edit', $escola)->with('success', 'Etapa de ensino removida com sucesso.');
    }
}
