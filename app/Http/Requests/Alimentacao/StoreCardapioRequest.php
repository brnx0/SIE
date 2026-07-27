<?php

namespace App\Http\Requests\Alimentacao;

use App\Models\Alimentacao\Cardapio;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCardapioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'crd_nome'     => mb_strtoupper(trim((string) $this->input('crd_nome', '')), 'UTF-8'),
            'crd_esc_id'   => $this->input('crd_esc_id') ?: null,
            'crd_fl_ativo' => $this->boolean('crd_fl_ativo'),
        ]);
    }

    public function rules(): array
    {
        return [
            // Nulo = cardápio padrão da rede.
            'crd_esc_id'    => ['nullable', 'integer', 'exists:edu_escola,esc_id'],
            'crd_anl_id'    => ['required', 'integer', 'exists:cfg_ano_letivo,anl_id'],
            'crd_nome'      => ['required', 'string', 'max:120'],
            'crd_dt_inicio' => ['required', 'date'],
            'crd_dt_fim'    => ['required', 'date', 'after_or_equal:crd_dt_inicio'],
            'crd_fl_ativo'  => ['boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $this->assertSemVigenciaSobreposta($validator);
        });
    }

    /**
     * Dois cardápios ativos do mesmo escopo (mesma escola, ou ambos padrão da rede)
     * e mesmo ano não podem ter vigências que se cruzam.
     *
     * A tela de preparação busca "o cardápio vigente nesta data": com dois válidos
     * ao mesmo tempo, qual ganha passaria a depender da ordem da query — a escola
     * poderia servir um cardápio hoje e outro amanhã, sem ninguém ter mudado nada.
     */
    private function assertSemVigenciaSobreposta($validator): void
    {
        if (! $this->boolean('crd_fl_ativo')) {
            return; // cardápio inativo não é lido por ninguém
        }

        $escId = $this->input('crd_esc_id');

        $conflito = Cardapio::query()
            ->where('crd_fl_ativo', true)
            ->where('crd_anl_id', $this->input('crd_anl_id'))
            ->when($escId === null,
                fn ($q) => $q->whereNull('crd_esc_id'),
                fn ($q) => $q->where('crd_esc_id', $escId))
            ->when($this->route('id'), fn ($q, $id) => $q->where('crd_id', '!=', $id))
            // Sobreposição: começa antes do outro terminar E termina depois de ele começar.
            ->where('crd_dt_inicio', '<=', $this->input('crd_dt_fim'))
            ->where('crd_dt_fim', '>=', $this->input('crd_dt_inicio'))
            ->first();

        if (! $conflito) {
            return;
        }

        $escopo = $escId === null ? 'o padrão da rede' : 'esta escola';

        $validator->errors()->add(
            'crd_dt_inicio',
            "O cardápio \"{$conflito->crd_nome}\" já vale para {$escopo} entre "
            .$conflito->crd_dt_inicio->format('d/m/Y').' e '.$conflito->crd_dt_fim->format('d/m/Y')
            .'. Dois cardápios ativos no mesmo período deixariam indefinido qual a escola deve seguir —'
            .' ajuste as datas ou inative o outro cardápio.',
        );
    }

    public function attributes(): array
    {
        return [
            'crd_esc_id'    => 'escola',
            'crd_anl_id'    => 'ano letivo',
            'crd_nome'      => 'nome',
            'crd_dt_inicio' => 'início da vigência',
            'crd_dt_fim'    => 'fim da vigência',
            'crd_fl_ativo'  => 'situação',
        ];
    }
}
