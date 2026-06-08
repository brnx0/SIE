<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Parametro\AtendimentoAee;
use App\Models\Turma\Turma;
use App\Models\Turma\TurmaAtendimentoAee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TurmaAeeAtendimentoController extends Controller
{
    /** Catálogo de atendimentos disponíveis (lookup global). */
    public function catalogo(): JsonResponse
    {
        return response()->json(
            AtendimentoAee::where('ate_fl_ativo', true)
                ->orderBy('ate_descricao')
                ->get(['ate_id', 'ate_descricao'])
        );
    }

    /** Atendimentos atualmente ofertados pela turma AEE. */
    public function index(int $turId): JsonResponse
    {
        $turma = $this->aeeTurma($turId);

        return response()->json(
            TurmaAtendimentoAee::where('tat_tur_id', $turma->tur_id)
                ->with('atendimento:ate_id,ate_descricao')
                ->orderBy('tat_id')
                ->get()
                ->map(fn ($t) => [
                    'tat_id'        => $t->tat_id,
                    'ate_id'        => $t->tat_ate_id,
                    'ate_descricao' => $t->atendimento?->ate_descricao,
                ])
        );
    }

    public function store(Request $request, int $turId): JsonResponse
    {
        $turma = $this->aeeTurma($turId);

        $data = $request->validate([
            'ate_id' => ['required', 'integer', 'exists:cfg_atendimento_aee,ate_id'],
        ], [], ['ate_id' => 'atendimento']);

        $existe = TurmaAtendimentoAee::where('tat_tur_id', $turma->tur_id)
            ->where('tat_ate_id', $data['ate_id'])
            ->exists();

        if ($existe) {
            throw ValidationException::withMessages([
                'ate_id' => 'Atendimento já ofertado nesta turma.',
            ]);
        }

        TurmaAtendimentoAee::create([
            'tat_tur_id' => $turma->tur_id,
            'tat_ate_id' => $data['ate_id'],
        ]);

        return response()->json(['ok' => true], 201);
    }

    public function destroy(int $turId, int $tatId): JsonResponse
    {
        $turma = $this->aeeTurma($turId);

        $registro = TurmaAtendimentoAee::where('tat_tur_id', $turma->tur_id)
            ->where('tat_id', $tatId)
            ->firstOrFail();

        $registro->delete();

        return response()->json(['ok' => true]);
    }

    private function aeeTurma(int $turId): Turma
    {
        return Turma::aee()->findOrFail($turId);
    }
}
