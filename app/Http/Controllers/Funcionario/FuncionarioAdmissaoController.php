<?php

namespace App\Http\Controllers\Funcionario;

use App\Http\Controllers\Controller;
use App\Models\Funcionario\Funcionario;
use App\Models\Funcionario\FuncionarioAdmissao;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FuncionarioAdmissaoController extends Controller
{
    protected function rules(Funcionario $funcionario, ?int $admissaoId = null): array
    {
        return [
            'adm_matricula' => [
                'required',
                'string',
                'max:30',
                Rule::unique('edu_funcionario_admissao', 'adm_matricula')
                    ->where('adm_fun_id', $funcionario->fun_id)
                    ->whereNull('adm_deleted_at')
                    ->ignore($admissaoId, 'adm_id'),
            ],
            'adm_dt_admissao' => ['required', 'date'],
            'adm_crg_id' => ['required', 'integer', 'exists:edu_cargo,crg_id'],
            'adm_escolaridade_admissao' => ['nullable', 'integer', Rule::in([1, 2, 3, 4, 5, 6, 7, 8, 9, 10])],
        ];
    }

    protected function attributes(): array
    {
        return [
            'adm_matricula' => 'matrícula',
            'adm_dt_admissao' => 'data de admissão',
            'adm_crg_id' => 'cargo de admissão',
            'adm_escolaridade_admissao' => 'escolaridade na admissão',
        ];
    }

    public function store(Request $request, Funcionario $funcionario): RedirectResponse
    {
        $data = $request->validate($this->rules($funcionario), [], $this->attributes());

        $funcionario->admissoes()->create($data);

        return back()->with('success', 'Admissão cadastrada com sucesso.');
    }

    public function update(Request $request, Funcionario $funcionario, FuncionarioAdmissao $admissao): RedirectResponse
    {
        $data = $request->validate($this->rules($funcionario, $admissao->adm_id), [], $this->attributes());

        $admissao->update($data);

        return back()->with('success', 'Admissão atualizada com sucesso.');
    }

    public function destroy(Funcionario $funcionario, FuncionarioAdmissao $admissao): RedirectResponse
    {
        return $this->safeDelete($admissao)
            ?? back()->with('success', 'Admissão removida com sucesso.');
    }
}
