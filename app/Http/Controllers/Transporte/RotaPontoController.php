<?php

namespace App\Http\Controllers\Transporte;

use App\Http\Controllers\Controller;
use App\Models\Transporte\Ponto;
use App\Models\Transporte\Rota;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RotaPontoController extends Controller
{
    public function store(Request $request, Rota $rota): RedirectResponse
    {
        $data = $this->validated($request);

        $proximaOrdem = (int) $rota->pontos()->max('pto_ordem') + 1;

        $rota->pontos()->create(array_merge($data, [
            'pto_rot_id' => $rota->rot_id,
            'pto_ordem'  => $proximaOrdem,
        ]));

        return back()->with('success', 'Ponto de parada adicionado.');
    }

    public function update(Request $request, Rota $rota, Ponto $ponto): RedirectResponse
    {
        abort_unless($ponto->pto_rot_id === $rota->rot_id, 404);

        $ponto->update($this->validated($request));

        return back()->with('success', 'Ponto de parada atualizado.');
    }

    /**
     * Recebe a nova ordem dos pontos (array de pto_id) e regrava pto_ordem.
     */
    public function reordenar(Request $request, Rota $rota): RedirectResponse
    {
        $data = $request->validate([
            'ordem'   => ['required', 'array', 'min:1'],
            'ordem.*' => ['integer'],
        ]);

        $idsRota = $rota->pontos()->pluck('pto_id')->all();
        $ids     = array_values(array_intersect(array_map('intval', $data['ordem']), $idsRota));

        DB::transaction(function () use ($ids) {
            foreach ($ids as $i => $ptoId) {
                Ponto::where('pto_id', $ptoId)->update(['pto_ordem' => $i + 1]);
            }
        });

        return back()->with('success', 'Ordem dos pontos atualizada.');
    }

    public function destroy(Rota $rota, Ponto $ponto): RedirectResponse
    {
        abort_unless($ponto->pto_rot_id === $rota->rot_id, 404);

        // N13: ponto com passageiros vinculados não pode ser removido.
        if ($ponto->passageiros()->exists()) {
            return back()->with('error', 'Este ponto possui passageiros vinculados. Mova-os para outro ponto antes de removê-lo.');
        }

        return $this->safeDelete($ponto)
            ?? back()->with('success', 'Ponto de parada removido.');
    }

    private function validated(Request $request): array
    {
        // CEP chega mascarado (#####-###) do front — normaliza para dígitos.
        $cep = preg_replace('/\D/', '', (string) $request->input('pto_cep', ''));
        $request->merge(['pto_cep' => $cep !== '' ? $cep : null]);

        return $request->validate([
            'pto_nome'      => ['required', 'string', 'max:120'],
            'pto_cep'       => ['nullable', 'digits:8'],
            'pto_endereco'  => ['nullable', 'string', 'max:200'],
            'pto_bai_id'    => ['nullable', 'integer', 'exists:edu_bairro,bai_id'],
            'pto_latitude'  => ['nullable', 'numeric', 'between:-90,90'],
            'pto_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'pto_hr_prevista' => ['nullable', 'date_format:H:i'],
            'pto_esc_id'    => ['nullable', 'integer', 'exists:edu_escola,esc_id'],
        ], [], [
            'pto_nome'      => 'nome do ponto',
            'pto_cep'       => 'CEP',
            'pto_endereco'  => 'endereço',
            'pto_bai_id'    => 'bairro',
            'pto_latitude'  => 'latitude',
            'pto_longitude' => 'longitude',
            'pto_hr_prevista' => 'horário previsto',
            'pto_esc_id'    => 'instituição de ensino',
        ]);
    }
}
