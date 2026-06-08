<?php

namespace App\Http\Controllers\Serie;

use App\Http\Controllers\Controller;
use App\Models\Serie\Serie;
use App\Models\Serie\SerieIndicador;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SerieIndicadorController extends Controller
{
    /** Catálogo via API — usado pelo modal de replicação para preview. */
    public function index(Serie $serie): JsonResponse
    {
        return response()->json(
            SerieIndicador::where('ind_ser_id', $serie->ser_id)
                ->orderBy('ind_id')
                ->get(['ind_id', 'ind_descricao', 'ind_fl_ativo'])
        );
    }

    public function store(Request $request, Serie $serie): RedirectResponse
    {
        $data = $request->validate([
            'ind_descricao' => ['required', 'string', 'max:1000'],
            'ind_fl_ativo'  => ['boolean'],
        ]);

        $descricao = trim($data['ind_descricao']);

        $duplicada = SerieIndicador::where('ind_ser_id', $serie->ser_id)
            ->whereRaw('UPPER(ind_descricao) = ?', [mb_strtoupper($descricao, 'UTF-8')])
            ->exists();

        if ($duplicada) {
            throw ValidationException::withMessages([
                'ind_descricao' => 'Já existe um indicador com esta descrição nesta série.',
            ]);
        }

        SerieIndicador::create([
            'ind_ser_id'    => $serie->ser_id,
            'ind_descricao' => $descricao,
            'ind_fl_ativo'  => $request->boolean('ind_fl_ativo', true),
        ]);

        return back()->with('success', 'Indicador adicionado com sucesso.');
    }

    public function update(Request $request, Serie $serie, SerieIndicador $indicador): RedirectResponse
    {
        abort_if($indicador->ind_ser_id !== $serie->ser_id, 404);

        $data = $request->validate([
            'ind_descricao' => ['required', 'string', 'max:1000'],
            'ind_fl_ativo'  => ['boolean'],
        ]);

        $descricao = trim($data['ind_descricao']);

        $duplicada = SerieIndicador::where('ind_ser_id', $serie->ser_id)
            ->where('ind_id', '!=', $indicador->ind_id)
            ->whereRaw('UPPER(ind_descricao) = ?', [mb_strtoupper($descricao, 'UTF-8')])
            ->exists();

        if ($duplicada) {
            throw ValidationException::withMessages([
                'ind_descricao' => 'Já existe outro indicador com esta descrição nesta série.',
            ]);
        }

        $indicador->update([
            'ind_descricao' => $descricao,
            'ind_fl_ativo'  => $request->boolean('ind_fl_ativo', $indicador->ind_fl_ativo),
        ]);

        return back()->with('success', 'Indicador atualizado com sucesso.');
    }

    public function destroy(Serie $serie, SerieIndicador $indicador): RedirectResponse
    {
        abort_if($indicador->ind_ser_id !== $serie->ser_id, 404);

        return $this->safeDelete($indicador)
            ?? back()->with('success', 'Indicador removido com sucesso.');
    }

    public function replicar(Request $request, Serie $serie): RedirectResponse
    {
        $data = $request->validate([
            'ser_id_origem' => ['required', 'integer', 'different:'.$serie->ser_id, 'exists:edu_serie,ser_id'],
            'substituir'    => ['boolean'],
        ], [], [
            'ser_id_origem' => 'série de origem',
        ]);

        $substituir = (bool) ($data['substituir'] ?? false);

        $origens = SerieIndicador::where('ind_ser_id', (int) $data['ser_id_origem'])
            ->where('ind_fl_ativo', true)
            ->orderBy('ind_id')
            ->get(['ind_descricao']);

        if ($origens->isEmpty()) {
            throw ValidationException::withMessages([
                'ser_id_origem' => 'Série de origem não possui indicadores ativos para replicar.',
            ]);
        }

        $inseridos = 0;

        DB::transaction(function () use ($serie, $origens, $substituir, &$inseridos) {
            if ($substituir) {
                SerieIndicador::where('ind_ser_id', $serie->ser_id)->delete();
            }

            $existentes = SerieIndicador::where('ind_ser_id', $serie->ser_id)
                ->pluck('ind_descricao')
                ->map(fn ($d) => mb_strtoupper(trim($d), 'UTF-8'))
                ->all();
            $existentesSet = array_flip($existentes);

            foreach ($origens as $o) {
                $descricao = trim($o->ind_descricao);
                $key = mb_strtoupper($descricao, 'UTF-8');
                if (isset($existentesSet[$key])) {
                    continue;
                }

                SerieIndicador::create([
                    'ind_ser_id'    => $serie->ser_id,
                    'ind_descricao' => $descricao,
                    'ind_fl_ativo'  => true,
                ]);

                $existentesSet[$key] = true;
                $inseridos++;
            }
        });

        $msg = $inseridos === 0
            ? 'Nenhum indicador novo para replicar (todos já existiam na série destino).'
            : "{$inseridos} indicador" . ($inseridos > 1 ? 'es' : '') . ' replicado' . ($inseridos > 1 ? 's' : '') . ' com sucesso.';

        return back()->with($inseridos > 0 ? 'success' : 'info', $msg);
    }
}
