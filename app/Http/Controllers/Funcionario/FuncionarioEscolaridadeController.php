<?php

namespace App\Http\Controllers\Funcionario;

use App\Http\Controllers\Controller;
use App\Models\Funcionario\Funcionario;
use App\Models\Funcionario\FuncionarioEscolaridade;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FuncionarioEscolaridadeController extends Controller
{
    /** Níveis de escolaridade que exigem informar o curso superior. */
    private const NIVEIS_EXIGEM_CURSO = [5, 6, 7, 8, 10];

    private function rules(Request $request): array
    {
        $exigeCurso = in_array((int) $request->input('fes_nivel'), self::NIVEIS_EXIGEM_CURSO, true);
        $anoMax = (int) date('Y') + 1;

        return [
            'fes_nivel'      => ['required', 'integer', Rule::in([1, 2, 3, 4, 5, 6, 7, 8, 9, 10])],
            'fes_ano_inicio' => ['nullable', 'integer', 'min:1900', 'max:' . $anoMax],
            'fes_ano_fim'    => ['nullable', 'integer', 'min:1900', 'max:' . $anoMax, 'gte:fes_ano_inicio'],
            'fes_cfs_id'     => [$exigeCurso ? 'required' : 'nullable', 'integer', 'exists:edu_curso_formacao_superior,cfs_id'],
        ];
    }

    private function messages(): array
    {
        return [
            'fes_cfs_id.required' => 'Selecione o curso para níveis de ensino superior.',
            'fes_ano_fim.gte'     => 'O ano final deve ser igual ou posterior ao ano inicial.',
        ];
    }

    private function attributes(): array
    {
        return [
            'fes_nivel'      => 'nível de escolaridade',
            'fes_ano_inicio' => 'ano inicial',
            'fes_ano_fim'    => 'ano final',
            'fes_cfs_id'     => 'curso',
        ];
    }

    public function store(Request $request, Funcionario $funcionario): RedirectResponse
    {
        $data = $request->validate($this->rules($request), $this->messages(), $this->attributes());

        $funcionario->escolaridades()->create($data);

        return back()->with('success', 'Escolaridade adicionada com sucesso.');
    }

    public function update(Request $request, Funcionario $funcionario, FuncionarioEscolaridade $escolaridade): RedirectResponse
    {
        abort_unless($escolaridade->fes_fun_id === $funcionario->fun_id, 404);

        $data = $request->validate($this->rules($request), $this->messages(), $this->attributes());

        $escolaridade->update($data);

        return back()->with('success', 'Escolaridade atualizada com sucesso.');
    }

    public function destroy(Funcionario $funcionario, FuncionarioEscolaridade $escolaridade): RedirectResponse
    {
        abort_unless($escolaridade->fes_fun_id === $funcionario->fun_id, 404);

        if ($funcionario->escolaridades()->count() <= 1) {
            return back()->with('error', 'O funcionário deve ter ao menos uma escolaridade cadastrada.');
        }

        $escolaridade->delete();

        return back()->with('success', 'Escolaridade removida com sucesso.');
    }
}
