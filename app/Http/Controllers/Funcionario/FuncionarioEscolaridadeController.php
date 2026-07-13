<?php

namespace App\Http\Controllers\Funcionario;

use App\Http\Controllers\Controller;
use App\Models\Funcionario\Funcionario;
use App\Models\Funcionario\FuncionarioEscolaridade;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class FuncionarioEscolaridadeController extends Controller
{
    /** Níveis de graduação — exigem o curso de formação superior (cfs). */
    private const NIVEIS_EXIGEM_CURSO = [5, 6, 10];

    /** Níveis de pós — exigem a área de pós-graduação. */
    private const NIVEIS_POS = [7, 8];

    /** Níveis superior (graduação + pós) — aceitam a instituição (IES). */
    private const NIVEIS_EXIGE_IES = [5, 6, 7, 8, 10];

    /** Graduação concluída — pré-requisito para cadastrar pós. */
    private const NIVEIS_SUPERIOR_COMPLETO = [6, 10];

    /** Códigos válidos de área de pós-graduação (Censo/INEP). */
    private const AREAS_POS = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 99];

    private function rules(Request $request): array
    {
        $nivel      = (int) $request->input('fes_nivel');
        $exigeCurso = in_array($nivel, self::NIVEIS_EXIGEM_CURSO, true);
        $ehPos      = in_array($nivel, self::NIVEIS_POS, true);
        $anoMax     = (int) date('Y') + 1;

        return [
            'fes_nivel'      => ['required', 'integer', Rule::in([1, 2, 3, 4, 5, 6, 7, 8, 9, 10])],
            'fes_ano_inicio' => ['nullable', 'integer', 'min:1900', 'max:' . $anoMax],
            'fes_ano_fim'    => ['nullable', 'integer', 'min:1900', 'max:' . $anoMax, 'gte:fes_ano_inicio'],
            'fes_cfs_id'     => [$exigeCurso ? 'required' : 'nullable', 'integer', 'exists:edu_curso_formacao_superior,cfs_id'],
            'fes_pos_area'   => [$ehPos ? 'required' : 'nullable', 'integer', Rule::in(self::AREAS_POS)],
            'fes_ies_id'     => ['nullable', 'integer', 'exists:edu_instituicao_ensino_superior,ies_id'],
        ];
    }

    private function messages(): array
    {
        return [
            'fes_cfs_id.required'   => 'Selecione o curso para níveis de graduação.',
            'fes_pos_area.required' => 'Selecione a área de pós-graduação.',
            'fes_ano_fim.gte'       => 'O ano final deve ser igual ou posterior ao ano inicial.',
        ];
    }

    private function attributes(): array
    {
        return [
            'fes_nivel'      => 'nível de escolaridade',
            'fes_ano_inicio' => 'ano inicial',
            'fes_ano_fim'    => 'ano final',
            'fes_cfs_id'     => 'curso',
            'fes_pos_area'   => 'área de pós-graduação',
            'fes_ies_id'     => 'instituição de ensino superior',
        ];
    }

    /**
     * Monta o payload já normalizado por nível: zera campos irrelevantes
     * (curso fora da graduação, área fora da pós, instituição fora do superior).
     */
    private function payload(array $data): array
    {
        $nivel = (int) $data['fes_nivel'];

        return [
            'fes_nivel'      => $nivel,
            'fes_ano_inicio' => $data['fes_ano_inicio'] ?? null,
            'fes_ano_fim'    => $data['fes_ano_fim'] ?? null,
            'fes_cfs_id'     => in_array($nivel, self::NIVEIS_EXIGEM_CURSO, true) ? ($data['fes_cfs_id'] ?? null) : null,
            'fes_pos_area'   => in_array($nivel, self::NIVEIS_POS, true) ? ($data['fes_pos_area'] ?? null) : null,
            'fes_ies_id'     => in_array($nivel, self::NIVEIS_EXIGE_IES, true) ? ($data['fes_ies_id'] ?? null) : null,
        ];
    }

    /**
     * Regra: só é possível cadastrar pós (7,8) se o funcionário já tiver ao menos
     * um ensino superior concluído (6 ou 10) cadastrado. Ignora a própria linha na edição.
     */
    private function assertPreRequisitoPos(Funcionario $funcionario, int $nivel, ?int $ignorarId = null): void
    {
        if (! in_array($nivel, self::NIVEIS_POS, true)) {
            return;
        }

        $temSuperior = $funcionario->escolaridades()
            ->whereIn('fes_nivel', self::NIVEIS_SUPERIOR_COMPLETO)
            ->when($ignorarId, fn ($q) => $q->where('fes_id', '!=', $ignorarId))
            ->exists();

        if (! $temSuperior) {
            throw ValidationException::withMessages([
                'fes_nivel' => 'Para cadastrar pós-graduação, o funcionário deve ter ao menos um ensino superior completo cadastrado.',
            ]);
        }
    }

    public function store(Request $request, Funcionario $funcionario): RedirectResponse
    {
        $data = $request->validate($this->rules($request), $this->messages(), $this->attributes());

        $this->assertPreRequisitoPos($funcionario, (int) $data['fes_nivel']);

        $funcionario->escolaridades()->create($this->payload($data));

        return back()->with('success', 'Escolaridade adicionada com sucesso.');
    }

    public function update(Request $request, Funcionario $funcionario, FuncionarioEscolaridade $escolaridade): RedirectResponse
    {
        abort_unless($escolaridade->fes_fun_id === $funcionario->fun_id, 404);

        $data = $request->validate($this->rules($request), $this->messages(), $this->attributes());

        $this->assertPreRequisitoPos($funcionario, (int) $data['fes_nivel'], $escolaridade->fes_id);

        $escolaridade->update($this->payload($data));

        return back()->with('success', 'Escolaridade atualizada com sucesso.');
    }

    public function destroy(Funcionario $funcionario, FuncionarioEscolaridade $escolaridade): RedirectResponse
    {
        abort_unless($escolaridade->fes_fun_id === $funcionario->fun_id, 404);

        if ($funcionario->escolaridades()->count() <= 1) {
            return back()->with('error', 'O funcionário deve ter ao menos uma escolaridade cadastrada.');
        }

        // Não permite remover o último ensino superior completo enquanto existir pós dependente.
        if (in_array($escolaridade->fes_nivel, self::NIVEIS_SUPERIOR_COMPLETO, true)) {
            $outrosSuperiores = $funcionario->escolaridades()
                ->whereIn('fes_nivel', self::NIVEIS_SUPERIOR_COMPLETO)
                ->where('fes_id', '!=', $escolaridade->fes_id)
                ->exists();

            $temPos = $funcionario->escolaridades()
                ->whereIn('fes_nivel', self::NIVEIS_POS)
                ->exists();

            if ($temPos && ! $outrosSuperiores) {
                return back()->with('error', 'Não é possível remover o último ensino superior completo enquanto houver pós-graduação cadastrada.');
            }
        }

        $escolaridade->delete();

        return back()->with('success', 'Escolaridade removida com sucesso.');
    }
}
