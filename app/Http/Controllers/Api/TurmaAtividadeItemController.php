<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Parametro\Atividade;
use App\Models\Turma\Turma;
use App\Models\Turma\TurmaAtividade;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TurmaAtividadeItemController extends Controller
{
    /** Catálogo de atividades disponíveis. */
    public function catalogo(): JsonResponse
    {
        return response()->json(
            Atividade::where('atv_fl_ativo', true)
                ->orderBy('atv_descricao')
                ->get(['atv_id', 'atv_descricao'])
        );
    }

    /** Atividades ofertadas pela turma. */
    public function index(int $turId): JsonResponse
    {
        $turma = $this->turma($turId);

        return response()->json(
            TurmaAtividade::where('tta_tur_id', $turma->tur_id)
                ->with('atividade:atv_id,atv_descricao')
                ->orderBy('tta_id')
                ->get()
                ->map(fn ($t) => [
                    'tta_id'        => $t->tta_id,
                    'atv_id'        => $t->tta_atv_id,
                    'atv_descricao' => $t->atividade?->atv_descricao,
                ])
        );
    }

    public function store(Request $request, int $turId): JsonResponse
    {
        $turma = $this->turma($turId);

        $data = $request->validate([
            'atv_id' => ['required', 'integer', 'exists:cfg_atividade,atv_id'],
        ], [], ['atv_id' => 'atividade']);

        $existe = TurmaAtividade::where('tta_tur_id', $turma->tur_id)
            ->where('tta_atv_id', $data['atv_id'])
            ->exists();

        if ($existe) {
            throw ValidationException::withMessages([
                'atv_id' => 'Atividade já ofertada nesta turma.',
            ]);
        }

        TurmaAtividade::create([
            'tta_tur_id' => $turma->tur_id,
            'tta_atv_id' => $data['atv_id'],
        ]);

        return response()->json(['ok' => true], 201);
    }

    public function destroy(int $turId, int $ttaId): JsonResponse
    {
        $turma = $this->turma($turId);

        $registro = TurmaAtividade::where('tta_tur_id', $turma->tur_id)
            ->where('tta_id', $ttaId)
            ->firstOrFail();

        $registro->delete();

        return response()->json(['ok' => true]);
    }

    private function turma(int $turId): Turma
    {
        return Turma::atividade()->findOrFail($turId);
    }
}
