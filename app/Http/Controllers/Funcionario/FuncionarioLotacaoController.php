<?php

namespace App\Http\Controllers\Funcionario;

use App\Http\Controllers\Controller;
use App\Models\Funcionario\Funcionario;
use App\Models\Funcionario\FuncionarioAdmissao;
use App\Models\Funcionario\FuncionarioLotacao;
use App\Models\Turma\Turma;
use App\Models\Turma\TurmaHorario;
use App\Models\Turma\TurmaProfessor;
use App\Models\Turma\TurmaProfessorApoio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FuncionarioLotacaoController extends Controller
{
    protected function rules(Request $request, ?FuncionarioAdmissao $admissao = null): array
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
            'lot_dt_inicio' => ['required', 'date', ...($admissao ? ['after_or_equal:' . $admissao->adm_dt_admissao->format('Y-m-d')] : [])],
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

        // Cargos com prefixo docente
        $prefixos = ['Professor', 'Docente', 'Regente', 'Prof'];
        foreach ($prefixos as $p) {
            if (str_starts_with($cargo->crg_nome, $p)) {
                return true;
            }
        }

        // Demais cargos que atuam em sala de aula
        $exatos = [
            // Inclusão
            'Auxiliar de Desenvolvimento Infantil',
            'Auxiliar de Desenvolvimento Infantil – PNE',
            'Cuidador(a) de Educando com Necessidades Especiais',
            'Tradutor(a) Intérprete de LIBRAS',
            'Mediador(a)',
            'Estimulador(a)',
            'Educação Especial – Trabalho Diferenciado',

            // Educação infantil
            'Educadora de Desenvolvimento Infantil em Creche',
            'Monitor(a) de Creche',
            'Auxiliar de Creche',

            // Apoio pedagógico em sala
            'Assistente de Alfabetização',
            'Auxiliar de Ensino',
            'Auxiliar de Classe',
            'Monitor(a) Docente de Atividades',
            'Monitor(a) de Laboratório',
            'Instrutor(a)',
            'Instrutor(a) de Dança',
            'Instrutor(a) de Fanfarra',
            'Instrutor(a) de Música',
            'Instrutor(a) Profissionalizante',
            'Reforço Escolar',
            'Estagiário(a)',
            'Monitor(a)',
        ];

        return in_array($cargo->crg_nome, $exatos, true);
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
        $data = $request->validate($this->rules($request, $admissao), [
            'lot_dt_inicio.after_or_equal' => 'A data inicial do vínculo não pode ser anterior à data de admissão (' . $admissao->adm_dt_admissao->format('d/m/Y') . ').',
        ], $this->attributes());

        $admissao->lotacoes()->create($data);

        return back()->with('success', 'Lotação cadastrada com sucesso.');
    }

    public function update(Request $request, Funcionario $funcionario, FuncionarioAdmissao $admissao, FuncionarioLotacao $lotacao): RedirectResponse
    {
        $data = $request->validate($this->rules($request, $admissao), [
            'lot_dt_inicio.after_or_equal' => 'A data inicial do vínculo não pode ser anterior à data de admissão (' . $admissao->adm_dt_admissao->format('d/m/Y') . ').',
        ], $this->attributes());

        $lotacao->update($data);

        return back()->with('success', 'Lotação atualizada com sucesso.');
    }

    public function destroy(Funcionario $funcionario, FuncionarioAdmissao $admissao, FuncionarioLotacao $lotacao): RedirectResponse
    {
        $turmasEscola = Turma::where('tur_esc_id', $lotacao->lot_esc_id)->pluck('tur_id');

        $temAlocacao = TurmaProfessor::where('tup_fun_id', $funcionario->fun_id)
                ->whereIn('tup_tur_id', $turmasEscola)->exists()
            || TurmaProfessorApoio::where('tpa_fun_id', $funcionario->fun_id)
                ->whereIn('tpa_tur_id', $turmasEscola)->exists()
            || TurmaHorario::where('trh_fun_id', $funcionario->fun_id)
                ->whereIn('trh_tur_id', $turmasEscola)->exists();

        if ($temAlocacao) {
            return back()->with('error', 'Não é possível excluir: professor possui alocação em turma desta escola. Informe a data fim no registro para inativar.');
        }

        $lotacao->delete();

        return back()->with('success', 'Lotação removida com sucesso.');
    }
}
