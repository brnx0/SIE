<?php

namespace App\Http\Controllers\Funcionario;

use App\Http\Controllers\Controller;
use App\Models\Funcionario\Funcionario;
use App\Models\Funcionario\FuncionarioAdmissao;
use App\Models\Funcionario\FuncionarioLotacao;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FuncionarioLotacaoController extends Controller
{
    protected function rules(Request $request): array
    {
        $vinculos = [
            'Comissionado', 'Concursado', 'Contratado CLT',
            'Contrato Temporário', 'Contrato Terceirizado', 'Função Gratificada',
        ];

        $situacoes = [
            'Aposentadoria', 'Cedido(a)', 'Exonerado',
            'Licença com Vencimento', 'Licença Médica', 'Licença sem Vencimento',
            'Óbito', 'Permuta', 'Remanejado(a)',
            'Término de Contrato', 'Transferido(a)',
        ];

        $criterios = [
            'Concurso Público',
            'Concurso público específico para o cargo de gestor escolar',
            'Exclusivamente por indicação/escolha da gestão',
            'Exclusivamente por processo eleitoral com a participação da comunidade escolar',
            'Processo seletivo qualificado e eleição com a participação da comunidade escolar',
            'Ser proprietário(a) ou sócio(a)-proprietário(a) da escola',
        ];

        $funcoesSala = [
            'Docente',
            'Auxiliar/assistente educacional',
            'Guia-Intérprete de Libras',
            'Tradutor-intérprete de Libras',
            'Monitor de atividade complementar',
            'Docente tutor (de módulo ou disciplina)',
            'Docente Titular (de módulo ou disciplina) – EAD',
            'Profissional de apoio escolar para aluno(a)s com deficiência (Lei 13.146/2015)',
        ];

        $isDocente = $this->isCargoDocente($request->input('lot_crg_id'));

        return [
            'lot_esc_id' => ['required', 'integer', 'exists:edu_escola,esc_id'],
            'lot_crg_id' => ['required', 'integer', 'exists:edu_cargo,crg_id'],
            'lot_vinculo' => ['required', 'string', Rule::in($vinculos)],
            'lot_situacao_funcional' => ['nullable', 'string', Rule::in($situacoes)],
            'lot_criterio_acesso' => ['nullable', 'string', Rule::in($criterios)],
            'lot_dt_inicio' => ['required', 'date'],
            'lot_dt_fim' => ['nullable', 'date', 'after_or_equal:lot_dt_inicio'],
            'lot_fl_ativo' => ['boolean'],
            'lot_funcoes_sala_aula' => [$isDocente ? 'required' : 'nullable', 'array'],
            'lot_funcoes_sala_aula.*' => ['string', Rule::in($funcoesSala)],
        ];
    }

    protected function isCargoDocente(?int $cargoId): bool
    {
        if (! $cargoId) {
            return false;
        }

        $cargo = \App\Models\Funcionario\Cargo::find($cargoId);
        if (! $cargo) {
            return false;
        }

        $prefixos = ['Professor', 'Docente', 'Regente', 'Prof'];
        foreach ($prefixos as $p) {
            if (str_starts_with($cargo->crg_nome, $p)) {
                return true;
            }
        }

        return false;
    }

    protected function attributes(): array
    {
        return [
            'lot_esc_id' => 'escola',
            'lot_crg_id' => 'função atual',
            'lot_vinculo' => 'vínculo',
            'lot_situacao_funcional' => 'situação funcional',
            'lot_criterio_acesso' => 'critério de acesso',
            'lot_dt_inicio' => 'data inicial',
            'lot_dt_fim' => 'data final',
            'lot_fl_ativo' => 'funcionário ativo no cargo',
            'lot_funcoes_sala_aula' => 'função em sala de aula',
        ];
    }

    public function store(Request $request, Funcionario $funcionario, FuncionarioAdmissao $admissao): RedirectResponse
    {
        $data = $request->validate($this->rules($request), [], $this->attributes());

        $admissao->lotacoes()->create($data);

        return back()->with('success', 'Lotação cadastrada com sucesso.');
    }

    public function update(Request $request, Funcionario $funcionario, FuncionarioAdmissao $admissao, FuncionarioLotacao $lotacao): RedirectResponse
    {
        $data = $request->validate($this->rules($request), [], $this->attributes());

        $lotacao->update($data);

        return back()->with('success', 'Lotação atualizada com sucesso.');
    }

    public function destroy(Funcionario $funcionario, FuncionarioAdmissao $admissao, FuncionarioLotacao $lotacao): RedirectResponse
    {
        $lotacao->delete();

        return back()->with('success', 'Lotação removida com sucesso.');
    }
}
