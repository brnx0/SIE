<?php

namespace App\Http\Controllers\Parametro;

use App\Http\Controllers\Controller;
use App\Http\Requests\Parametro\StoreMediaEscolaRequest;
use App\Models\Parametro\AnoLetivo;
use App\Models\Parametro\MediaEscola;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MediaEscolaController extends Controller
{
    public function store(StoreMediaEscolaRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['mde_created_by_id'] = auth()->id();
        $data['mde_updated_by_id'] = auth()->id();

        MediaEscola::create($data);

        return to_route('parametros.edit')->with('success', 'Média da escola cadastrada com sucesso.');
    }

    public function update(StoreMediaEscolaRequest $request, MediaEscola $mediaEscola): RedirectResponse
    {
        $data = $request->validated();
        $data['mde_updated_by_id'] = auth()->id();

        $mediaEscola->update($data);

        return to_route('parametros.edit')->with('success', 'Média da escola atualizada com sucesso.');
    }

    public function destroy(MediaEscola $mediaEscola): RedirectResponse
    {
        $mediaEscola->delete();

        return to_route('parametros.edit')->with('success', 'Média da escola removida com sucesso.');
    }

    /**
     * Replica as médias por escola do ano informado para o ano letivo seguinte (ano + 1).
     * Não sobrescreve escolas que já possuam média no ano de destino.
     */
    public function replicar(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'origem_anl_id' => ['required', 'integer', 'exists:cfg_ano_letivo,anl_id'],
        ]);

        $origem = AnoLetivo::findOrFail($data['origem_anl_id']);

        $destino = AnoLetivo::where('anl_ano', $origem->anl_ano + 1)->first();
        if (! $destino) {
            return back()->with('error', "Cadastre o ano letivo {$origem->anl_ano} + 1 antes de replicar.");
        }

        $registros = MediaEscola::where('mde_anl_id', $origem->anl_id)->get();
        if ($registros->isEmpty()) {
            return back()->with('error', 'Não há médias por escola para replicar neste ano letivo.');
        }

        // Escolas que já têm média no destino — não sobrescrever
        $jaExistentes = MediaEscola::where('mde_anl_id', $destino->anl_id)
            ->pluck('mde_esc_id')
            ->all();

        $novos = $registros->reject(fn ($r) => in_array($r->mde_esc_id, $jaExistentes, true));
        if ($novos->isEmpty()) {
            return back()->with('error', "Todas as escolas já possuem média no ano {$destino->anl_ano}.");
        }

        DB::transaction(function () use ($novos, $destino) {
            foreach ($novos as $r) {
                MediaEscola::create([
                    'mde_anl_id'        => $destino->anl_id,
                    'mde_esc_id'        => $r->mde_esc_id,
                    'mde_media'         => $r->mde_media,
                    'mde_created_by_id' => auth()->id(),
                    'mde_updated_by_id' => auth()->id(),
                ]);
            }
        });

        return back()->with('success', "{$novos->count()} média(s) replicada(s) para o ano {$destino->anl_ano}.");
    }
}
