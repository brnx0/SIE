<?php

namespace App\Http\Controllers\Transporte;

use App\Http\Controllers\Controller;
use App\Models\Aluno\Aluno;
use App\Models\Transporte\Passageiro;
use App\Models\Transporte\Ponto;
use App\Models\Transporte\Rota;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class RotaPassageiroController extends Controller
{
    public function store(Request $request, Rota $rota): RedirectResponse
    {
        $data = $request->validate([
            'pas_pto_id'    => ['required', 'integer'],
            'pas_aln_id'    => ['nullable', 'integer', 'exists:edu_aluno,aln_id'],
            'pas_tue_id'    => ['nullable', 'integer', 'exists:edu_transporte_usuario,tue_id'],
            'pas_dt_inicio' => ['nullable', 'date'],
            'pas_dt_fim'    => ['nullable', 'date', 'after_or_equal:pas_dt_inicio'],
            'pas_obs'       => ['nullable', 'string', 'max:200'],
        ], [], [
            'pas_pto_id'    => 'ponto de embarque',
            'pas_aln_id'    => 'aluno',
            'pas_tue_id'    => 'usuário externo',
            'pas_dt_inicio' => 'data de início',
            'pas_dt_fim'    => 'data de término',
        ]);

        // Exatamente um: aluno da rede OU usuário externo.
        $temAluno   = ! empty($data['pas_aln_id']);
        $temExterno = ! empty($data['pas_tue_id']);
        if ($temAluno === $temExterno) {
            throw ValidationException::withMessages([
                'pas_aln_id' => 'Informe um aluno da rede OU um usuário externo (exatamente um).',
            ]);
        }

        // O ponto de embarque precisa pertencer a esta rota.
        $ponto = Ponto::where('pto_id', $data['pas_pto_id'])->first();
        if (! $ponto || $ponto->pto_rot_id !== $rota->rot_id) {
            throw ValidationException::withMessages([
                'pas_pto_id' => 'Selecione um ponto de parada desta rota.',
            ]);
        }

        // N5: mesmo passageiro 2× na mesma rota → bloqueado.
        $jaNaRota = $rota->passageiros()
            ->when($temAluno, fn ($q) => $q->where('pas_aln_id', $data['pas_aln_id']))
            ->when($temExterno, fn ($q) => $q->where('pas_tue_id', $data['pas_tue_id']))
            ->exists();
        if ($jaNaRota) {
            throw ValidationException::withMessages([
                $temAluno ? 'pas_aln_id' : 'pas_tue_id' => 'Este passageiro já está vinculado a esta rota.',
            ]);
        }

        // N5: aluno já em outra rota do MESMO turno/ano → aviso com confirmação.
        if ($temAluno && ! $request->boolean('confirm_outra_rota')) {
            $outras = Rota::query()
                ->where('rot_id', '!=', $rota->rot_id)
                ->where('rot_anl_id', $rota->rot_anl_id)
                ->where('rot_turno', $rota->rot_turno)
                ->whereHas('passageiros', fn ($q) => $q->where('pas_aln_id', $data['pas_aln_id']))
                ->get(['rot_id', 'rot_nome']);

            if ($outras->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'passageiro_outra_rota' => $outras->pluck('rot_nome')->implode(', '),
                ]);
            }
        }

        $rota->passageiros()->create([
            'pas_rot_id'    => $rota->rot_id,
            'pas_pto_id'    => $data['pas_pto_id'],
            'pas_aln_id'    => $data['pas_aln_id'] ?? null,
            'pas_tue_id'    => $data['pas_tue_id'] ?? null,
            'pas_dt_inicio' => $data['pas_dt_inicio'] ?? null,
            'pas_dt_fim'    => $data['pas_dt_fim'] ?? null,
            'pas_obs'       => $data['pas_obs'] ?? null,
        ]);

        // N6: vincular aluno seta a flag de uso de transporte (não desmarca na remoção).
        if ($temAluno) {
            Aluno::where('aln_id', $data['pas_aln_id'])->update(['aln_fl_usa_transporte' => true]);
        }

        return back()->with('success', 'Passageiro vinculado à rota.');
    }

    public function destroy(Rota $rota, Passageiro $passageiro): RedirectResponse
    {
        abort_unless($passageiro->pas_rot_id === $rota->rot_id, 404);

        $passageiro->delete();

        return back()->with('success', 'Passageiro removido da rota.');
    }
}
