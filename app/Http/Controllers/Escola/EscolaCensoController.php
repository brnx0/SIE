<?php

namespace App\Http\Controllers\Escola;

use App\Http\Controllers\Controller;
use App\Http\Requests\Escola\UpdateCensoEscolarRequest;
use App\Models\Escola\CensoEscolar;
use App\Models\Escola\Escola;
use App\Models\Parametro\AnoLetivo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EscolaCensoController extends Controller
{
    /**
     * Cria o censo do ano corrente (vazio ou replicado do anterior) e redireciona para edição.
     */
    public function store(Request $request, Escola $escola): RedirectResponse
    {
        $anoLetivo = AnoLetivo::where('anl_fl_em_exercicio', true)->firstOrFail();

        // Idempotente: se já existe, apenas redireciona
        $existing = CensoEscolar::where('cen_esc_id', $escola->esc_id)
            ->where('cen_anl_id', $anoLetivo->anl_id)
            ->first();

        if ($existing) {
            return to_route('escolas.censo.edit', [$escola, $existing]);
        }

        $replicate = $request->boolean('replicate');

        if ($replicate) {
            $previous = CensoEscolar::where('cen_esc_id', $escola->esc_id)
                ->where('cen_anl_id', '!=', $anoLetivo->anl_id)
                ->orderBy('cen_anl_id', 'desc')
                ->first();

            if ($previous) {
                $data = collect($previous->getAttributes())
                    ->except(['cen_id', 'cen_created_at', 'cen_updated_at'])
                    ->merge(['cen_anl_id' => $anoLetivo->anl_id])
                    ->all();

                $censo = CensoEscolar::create($data);
            } else {
                $censo = CensoEscolar::create([
                    'cen_esc_id' => $escola->esc_id,
                    'cen_anl_id' => $anoLetivo->anl_id,
                ]);
            }
        } else {
            $censo = CensoEscolar::create([
                'cen_esc_id' => $escola->esc_id,
                'cen_anl_id' => $anoLetivo->anl_id,
            ]);
        }

        return to_route('escolas.censo.edit', [$escola, $censo]);
    }

    /**
     * Exibe o formulário de edição do censo (somente ano letivo em exercício e dentro do prazo).
     */
    public function edit(Escola $escola, CensoEscolar $censoEscolar): Response
    {
        abort_if($censoEscolar->cen_esc_id !== $escola->esc_id, 403);

        $censoEscolar->load('anoLetivo');

        return Inertia::render('escolas/censo/Edit', [
            'escola' => $escola->only(['esc_id', 'esc_nome', 'esc_apelido']),
            'censo'  => $censoEscolar,
        ]);
    }

    /**
     * Exibe o censo em modo leitura.
     */
    public function show(Escola $escola, CensoEscolar $censoEscolar): Response
    {
        abort_if($censoEscolar->cen_esc_id !== $escola->esc_id, 403);

        $censoEscolar->load('anoLetivo');

        return Inertia::render('escolas/censo/Show', [
            'escola' => $escola->only(['esc_id', 'esc_nome', 'esc_apelido']),
            'censo'  => $censoEscolar,
        ]);
    }

    /**
     * Persiste as alterações do censo.
     */
    public function update(UpdateCensoEscolarRequest $request, Escola $escola, CensoEscolar $censoEscolar): RedirectResponse
    {
        abort_if($censoEscolar->cen_esc_id !== $escola->esc_id, 403);

        if ($censoEscolar->status === 'finalizado') {
            return back()->with('error', 'Prazo do censo encerrado. Não é possível editar.');
        }

        $censoEscolar->update($request->validated());

        return back()->with('success', 'Censo salvo com sucesso.');
    }
}
