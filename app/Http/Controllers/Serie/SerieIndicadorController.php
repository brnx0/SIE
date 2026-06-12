<?php

namespace App\Http\Controllers\Serie;

use App\Http\Controllers\Controller;
use App\Models\Parametro\GradeDisciplinar;
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
                ->get(['ind_id', 'ind_dis_id', 'ind_descricao', 'ind_fl_ativo'])
        );
    }

    public function store(Request $request, Serie $serie): RedirectResponse
    {
        $data = $request->validate([
            'ind_descricao' => ['required', 'string', 'max:1000'],
            'ind_dis_id'    => ['nullable', 'integer', 'exists:edu_disciplina,dis_id'],
            'ind_anl_id'    => ['required', 'integer', 'exists:cfg_ano_letivo,anl_id'],
            'ind_fl_ativo'  => ['boolean'],
        ]);

        $descricao = trim($data['ind_descricao']);

        $this->validarDisciplinaNaGrade($serie, $data['ind_anl_id'], $data['ind_dis_id'] ?? null);

        $duplicada = SerieIndicador::where('ind_ser_id', $serie->ser_id)
            ->where('ind_anl_id', $data['ind_anl_id'])
            ->whereRaw('UPPER(ind_descricao) = ?', [mb_strtoupper($descricao, 'UTF-8')])
            ->exists();

        if ($duplicada) {
            throw ValidationException::withMessages([
                'ind_descricao' => 'Já existe um indicador com esta descrição nesta série e ano letivo.',
            ]);
        }

        SerieIndicador::create([
            'ind_ser_id'    => $serie->ser_id,
            'ind_dis_id'    => $data['ind_dis_id'] ?? null,
            'ind_anl_id'    => $data['ind_anl_id'],
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
            'ind_dis_id'    => ['nullable', 'integer', 'exists:edu_disciplina,dis_id'],
            'ind_anl_id'    => ['nullable', 'integer', 'exists:cfg_ano_letivo,anl_id'],
            'ind_fl_ativo'  => ['boolean'],
        ]);

        $descricao = trim($data['ind_descricao']);
        $anlId = $data['ind_anl_id'] ?? $indicador->ind_anl_id;

        $this->validarDisciplinaNaGrade($serie, $anlId, $data['ind_dis_id'] ?? null);

        $duplicada = SerieIndicador::where('ind_ser_id', $serie->ser_id)
            ->where('ind_anl_id', $anlId)
            ->where('ind_id', '!=', $indicador->ind_id)
            ->whereRaw('UPPER(ind_descricao) = ?', [mb_strtoupper($descricao, 'UTF-8')])
            ->exists();

        if ($duplicada) {
            throw ValidationException::withMessages([
                'ind_descricao' => 'Já existe outro indicador com esta descrição nesta série e ano letivo.',
            ]);
        }

        $indicador->update([
            'ind_descricao' => $descricao,
            'ind_dis_id'    => array_key_exists('ind_dis_id', $data) ? $data['ind_dis_id'] : $indicador->ind_dis_id,
            'ind_anl_id'    => $anlId,
            'ind_fl_ativo'  => $request->boolean('ind_fl_ativo', $indicador->ind_fl_ativo),
        ]);

        return back()->with('success', 'Indicador atualizado com sucesso.');
    }

    private function validarDisciplinaNaGrade(Serie $serie, ?int $anlId, ?int $disId): void
    {
        if (! $disId || ! $anlId) return;

        $existe = GradeDisciplinar::where('grd_ser_id', $serie->ser_id)
            ->where('grd_anl_id', $anlId)
            ->where('grd_dis_id', $disId)
            ->where('grd_fl_ativo', true)
            ->exists();

        if (! $existe) {
            throw ValidationException::withMessages([
                'ind_dis_id' => 'Disciplina não está na grade desta série para o ano letivo selecionado.',
            ]);
        }
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
            'ser_id_origem'    => ['required', 'integer', 'different:'.$serie->ser_id, 'exists:edu_serie,ser_id'],
            'anl_id_destino'   => ['required', 'integer', 'exists:cfg_ano_letivo,anl_id'],
            'substituir'       => ['boolean'],
        ], [], [
            'ser_id_origem'  => 'série de origem',
            'anl_id_destino' => 'ano letivo destino',
        ]);

        $substituir = (bool) ($data['substituir'] ?? false);
        $anlDestino = (int) $data['anl_id_destino'];

        $origens = SerieIndicador::where('ind_ser_id', (int) $data['ser_id_origem'])
            ->where('ind_fl_ativo', true)
            ->orderBy('ind_id')
            ->get(['ind_descricao', 'ind_dis_id']);

        if ($origens->isEmpty()) {
            throw ValidationException::withMessages([
                'ser_id_origem' => 'Série de origem não possui indicadores ativos para replicar.',
            ]);
        }

        $inseridos = $this->copiarIndicadores($serie, $origens, $anlDestino, $substituir);

        return back()->with($inseridos > 0 ? 'success' : 'info', $this->mensagemReplicacao($inseridos));
    }

    public function replicarAno(Request $request, Serie $serie): RedirectResponse
    {
        $data = $request->validate([
            'anl_id_origem'  => ['required', 'integer', 'different:anl_id_destino', 'exists:cfg_ano_letivo,anl_id'],
            'anl_id_destino' => ['required', 'integer', 'exists:cfg_ano_letivo,anl_id'],
            'substituir'     => ['boolean'],
        ], [], [
            'anl_id_origem'  => 'ano letivo origem',
            'anl_id_destino' => 'ano letivo destino',
        ]);

        $substituir = (bool) ($data['substituir'] ?? false);

        $origens = SerieIndicador::where('ind_ser_id', $serie->ser_id)
            ->where('ind_anl_id', (int) $data['anl_id_origem'])
            ->where('ind_fl_ativo', true)
            ->orderBy('ind_id')
            ->get(['ind_descricao', 'ind_dis_id']);

        if ($origens->isEmpty()) {
            throw ValidationException::withMessages([
                'anl_id_origem' => 'Ano de origem não possui indicadores ativos para replicar.',
            ]);
        }

        $inseridos = $this->copiarIndicadores($serie, $origens, (int) $data['anl_id_destino'], $substituir);

        return back()->with($inseridos > 0 ? 'success' : 'info', $this->mensagemReplicacao($inseridos));
    }

    private function copiarIndicadores(Serie $serie, $origens, int $anlDestino, bool $substituir): int
    {
        $inseridos = 0;

        DB::transaction(function () use ($serie, $origens, $anlDestino, $substituir, &$inseridos) {
            if ($substituir) {
                SerieIndicador::where('ind_ser_id', $serie->ser_id)
                    ->where('ind_anl_id', $anlDestino)
                    ->delete();
            }

            $existentes = SerieIndicador::where('ind_ser_id', $serie->ser_id)
                ->where('ind_anl_id', $anlDestino)
                ->pluck('ind_descricao')
                ->map(fn ($d) => mb_strtoupper(trim($d), 'UTF-8'))
                ->all();
            $existentesSet = array_flip($existentes);

            // Disciplinas presentes na grade do (ano destino, série) — copia só essas.
            $disciplinasGrade = GradeDisciplinar::where('grd_ser_id', $serie->ser_id)
                ->where('grd_anl_id', $anlDestino)
                ->where('grd_fl_ativo', true)
                ->pluck('grd_dis_id')
                ->flip();

            foreach ($origens as $o) {
                $descricao = trim($o->ind_descricao);
                $key = mb_strtoupper($descricao, 'UTF-8');
                if (isset($existentesSet[$key])) {
                    continue;
                }

                // Mantém disciplina apenas se ela está na grade destino. Caso contrário, deixa nula.
                $disId = ($o->ind_dis_id && isset($disciplinasGrade[$o->ind_dis_id])) ? $o->ind_dis_id : null;

                SerieIndicador::create([
                    'ind_ser_id'    => $serie->ser_id,
                    'ind_dis_id'    => $disId,
                    'ind_anl_id'    => $anlDestino,
                    'ind_descricao' => $descricao,
                    'ind_fl_ativo'  => true,
                ]);

                $existentesSet[$key] = true;
                $inseridos++;
            }
        });

        return $inseridos;
    }

    private function mensagemReplicacao(int $inseridos): string
    {
        return $inseridos === 0
            ? 'Nenhum indicador novo para replicar (todos já existiam no destino).'
            : "{$inseridos} indicador" . ($inseridos > 1 ? 'es' : '') . ' replicado' . ($inseridos > 1 ? 's' : '') . ' com sucesso.';
    }
}
