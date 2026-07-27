<?php

namespace App\Services\Censo;

use App\Models\Escola\Escola;
use App\Models\Funcionario\Funcionario;
use App\Models\Matricula\Matricula;
use App\Models\Parametro\AnoLetivo;
use App\Models\Parametro\ParametroEntidade;
use App\Models\Turma\Turma;
use Illuminate\Support\Collection;

/**
 * Motor de "críticas" do Educacenso: valida os dados PRÓPRIOS do sistema contra os
 * campos obrigatórios/condicionais do layout (Matrícula Inicial), por escola.
 *
 * NÃO gera o arquivo — apenas aponta o que impediria o envio. Cada crítica traz o
 * registro (00/10/20/30/40/60), o sujeito (escola/turma/aluno/funcionário), o campo
 * e a severidade (erro = barra o envio; alerta = revisar).
 *
 * Campos ainda sem coluna no modelo (ver memória de gaps) NÃO são validados aqui —
 * ficam listados em {@see self::pendencias()} para o usuário ter ciência.
 */
class CensoCriticasService
{
    private const ERRO = 'erro';
    private const ALERTA = 'alerta';

    /** @var array<int,array<string,mixed>> */
    private array $criticas = [];

    private bool $cpfObrigatorio = false;

    /**
     * @return array{criticas: array, resumo: array, pendencias: array}
     */
    public function gerar(Escola $escola, AnoLetivo $ano): array
    {
        $this->criticas = [];
        $this->cpfObrigatorio = (bool) optional(ParametroEntidade::first())->par_fl_cpf_obrigatorio;

        $this->validarEscola($escola);
        $this->validarCensoEscolar($escola);
        $this->validarTurmas($escola, $ano);
        $this->validarAlunos($escola, $ano);
        $this->validarFuncionarios($escola);

        return [
            'criticas'   => $this->criticas,
            'resumo'     => $this->resumo(),
            'pendencias' => $this->pendencias(),
        ];
    }

    // ----------------------------------------------------------------- Reg 00

    private function validarEscola(Escola $e): void
    {
        $reg = '00';
        $suj = $e->esc_nome ?? "Escola #{$e->esc_id}";
        $id = $e->esc_id;

        if (blank($e->esc_cd_inep)) {
            $this->add($reg, $suj, $id, 'Código INEP', 'Código INEP da escola não informado.', self::ERRO);
        }
        if (blank($e->esc_dep_administrativa)) {
            $this->add($reg, $suj, $id, 'Dependência administrativa', 'Dependência administrativa não informada.', self::ERRO);
        }
        if (blank($e->esc_situacao_func)) {
            $this->add($reg, $suj, $id, 'Situação de funcionamento', 'Situação de funcionamento não informada.', self::ERRO);
        }
        // Campo 49: obrigatório quando a escola está em atividade (situação = 1).
        if ((int) $e->esc_situacao_func === 1 && $e->esc_regulamentada_conselho === null) {
            $this->add($reg, $suj, $id, 'Regulamentação (campo 49)', 'Regulamentação/autorização no conselho não informada.', self::ERRO);
        }
        if (blank($e->esc_mun_id)) {
            $this->add($reg, $suj, $id, 'Município', 'Município da escola não informado.', self::ERRO);
        }
        if (blank($e->esc_cep)) {
            $this->add($reg, $suj, $id, 'CEP (campo 7)', 'CEP da escola não informado.', self::ERRO);
        }
        if (blank($e->esc_logradouro)) {
            $this->add($reg, $suj, $id, 'Endereço (campo 10)', 'Logradouro da escola não informado.', self::ERRO);
        }
        if (blank($e->esc_zona)) {
            $this->add($reg, $suj, $id, 'Localização/Zona (campo 19)', 'Localização/zona da escola não informada.', self::ERRO);
        }
        if ($e->esc_localizacao_dif === null) {
            $this->add($reg, $suj, $id, 'Localização diferenciada (campo 20)', 'Localização diferenciada da escola não informada.', self::ERRO);
        }
    }

    // ----------------------------------------------------------------- Reg 10

    private function validarCensoEscolar(Escola $e): void
    {
        $reg = '10';
        $suj = $e->esc_nome ?? "Escola #{$e->esc_id}";
        $id = $e->esc_id;

        // Campos 3-8: local de funcionamento (vive em edu_escola.esc_fl_local_*) —
        // ao menos um marcado. Independe do registro de caracterização abaixo.
        $temLocal = $e->esc_fl_local_predio || $e->esc_fl_local_outra_escola || $e->esc_fl_local_galpao
            || $e->esc_fl_local_socioeducativa || $e->esc_fl_local_prisional || $e->esc_fl_local_outros;
        if (! $temLocal) {
            $this->add($reg, $suj, $id, 'Local de funcionamento (3-8)', 'Nenhum local de funcionamento marcado.', self::ALERTA);
        }

        $censo = $e->censos()->first();
        if (! $censo) {
            $this->add($reg, $suj, $id, 'Caracterização/Infraestrutura', 'Escola sem registro de caracterização e infraestrutura (Reg 10).', self::ERRO);

            return;
        }

        if ($censo->cen_fl_agua_potavel === null) {
            $this->add($reg, $suj, $id, 'Água potável (campo 17)', 'Não informado se há abastecimento de água potável.', self::ALERTA);
        }
        $temAgua = $censo->cen_agu_rede_publica || $censo->cen_agu_poco_artesiano || $censo->cen_agu_cacimba
            || $censo->cen_agu_fonte_rio || $censo->cen_agu_carro_pipa || $censo->cen_agu_inexistente;
        if (! $temAgua) {
            $this->add($reg, $suj, $id, 'Abastecimento de água (18-23)', 'Nenhuma forma de abastecimento de água marcada.', self::ALERTA);
        }
        $temEnergia = $censo->cen_ene_rede_publica || $censo->cen_ene_gerador_fossil
            || $censo->cen_ene_renovavel || $censo->cen_ene_inexistente;
        if (! $temEnergia) {
            $this->add($reg, $suj, $id, 'Energia elétrica (24-27)', 'Nenhuma fonte de energia elétrica marcada.', self::ALERTA);
        }
        $temEsgoto = $censo->cen_esg_rede_publica || $censo->cen_esg_fossa_septica
            || $censo->cen_esg_fossa_rudimentar || $censo->cen_esg_inexistente;
        if (! $temEsgoto) {
            $this->add($reg, $suj, $id, 'Esgotamento sanitário (28-31)', 'Nenhuma forma de esgotamento sanitário marcada.', self::ALERTA);
        }
        $temLixo = $censo->cen_lxd_coleta || $censo->cen_lxd_queima || $censo->cen_lxd_enterra
            || $censo->cen_lxd_destinacao_licenciada || $censo->cen_lxd_outra_area;
        if (! $temLixo) {
            $this->add($reg, $suj, $id, 'Destinação do lixo (32-36)', 'Nenhuma forma de destinação do lixo marcada.', self::ALERTA);
        }
    }

    // ----------------------------------------------------------------- Reg 20

    private function validarTurmas(Escola $e, AnoLetivo $ano): void
    {
        $reg = '20';
        $turmas = Turma::query()
            ->where('tur_esc_id', $e->esc_id)
            ->where('tur_anl_id', $ano->anl_id)
            ->get(['tur_id', 'tur_nome', 'tur_seg_id', 'tur_ser_id', 'tur_mediacao']);

        foreach ($turmas as $t) {
            $suj = $t->tur_nome ?: "Turma #{$t->tur_id}";
            if (blank($t->tur_nome)) {
                $this->add($reg, $suj, $t->tur_id, 'Nome da turma (campo 5)', 'Nome da turma não informado.', self::ERRO, 'turma');
            }
            if (blank($t->tur_mediacao)) {
                $this->add($reg, $suj, $t->tur_id, 'Mediação didático-pedagógica (campo 6)', 'Tipo de mediação não informado.', self::ERRO, 'turma');
            }
            if (blank($t->tur_seg_id)) {
                $this->add($reg, $suj, $t->tur_id, 'Etapa/Segmento', 'Turma sem segmento definido (necessário p/ etapa).', self::ERRO, 'turma');
            }
            if (blank($t->tur_ser_id)) {
                $this->add($reg, $suj, $t->tur_id, 'Série', 'Turma sem série definida.', self::ALERTA, 'turma');
            }
        }
    }

    // ------------------------------------------------------------- Reg 30 aluno

    private function validarAlunos(Escola $e, AnoLetivo $ano): void
    {
        $reg = '30';
        $alunos = Matricula::query()
            ->with(['aluno'])
            ->where('tma_situacao', Matricula::SITUACAO_ATIVA)
            ->whereHas('turma', fn ($q) => $q
                ->where('tur_esc_id', $e->esc_id)
                ->where('tur_anl_id', $ano->anl_id))
            ->get()
            ->map(fn (Matricula $m) => $m->aluno)
            ->filter()
            ->unique('aln_id');

        foreach ($alunos as $a) {
            $suj = $a->aln_nome ?: "Aluno #{$a->aln_id}";
            $id = $a->aln_id;

            if (blank($a->aln_nome)) {
                $this->add($reg, $suj, $id, 'Nome', 'Nome do aluno não informado.', self::ERRO, 'aluno');
            }
            if (blank($a->aln_dt_nascimento)) {
                $this->add($reg, $suj, $id, 'Data de nascimento', 'Data de nascimento não informada.', self::ERRO, 'aluno');
            }
            if (blank($a->aln_sexo)) {
                $this->add($reg, $suj, $id, 'Sexo', 'Sexo não informado.', self::ERRO, 'aluno');
            }
            if ($a->aln_cor_raca === null) {
                $this->add($reg, $suj, $id, 'Cor/Raça', 'Cor/raça não informada.', self::ERRO, 'aluno');
            }
            if (blank($a->aln_pais_origem)) {
                $this->add($reg, $suj, $id, 'Nacionalidade (campo 14)', 'País de origem/nacionalidade não informado.', self::ERRO, 'aluno');
            }
            if (blank($a->aln_mun_id_nasc)) {
                $this->add($reg, $suj, $id, 'Naturalidade', 'Município de nascimento não informado.', self::ERRO, 'aluno');
            }
            if (blank($a->aln_filiacao_1) && blank($a->aln_filiacao_2)) {
                $this->add($reg, $suj, $id, 'Filiação (campo 8)', 'Nenhuma filiação informada.', self::ERRO, 'aluno');
            }
            if ($a->aln_zona_residencia === null) {
                $this->add($reg, $suj, $id, 'Zona de residência (54)', 'Localização/zona de residência não informada.', self::ERRO, 'aluno');
            }
            if (blank($a->aln_mun_id_resid)) {
                $this->add($reg, $suj, $id, 'Município de residência (53)', 'Município de residência não vinculado.', self::ALERTA, 'aluno');
            }
            if ($this->cpfObrigatorio && blank($a->aln_cpf)) {
                $this->add($reg, $suj, $id, 'CPF', 'CPF obrigatório não informado.', self::ERRO, 'aluno');
            }
        }
    }

    // -------------------------------------------------------- Reg 30/40 funcionário

    private function validarFuncionarios(Escola $e): void
    {
        $funcionarios = Funcionario::query()
            ->where('fun_fl_ativo', true)
            ->whereHas('admissoes.lotacoes', fn ($q) => $q
                ->where('lot_esc_id', $e->esc_id)
                ->where('lot_fl_ativo', true))
            ->with(['admissoes.lotacoes' => fn ($q) => $q
                ->where('lot_esc_id', $e->esc_id)
                ->where('lot_fl_ativo', true)])
            ->get();

        foreach ($funcionarios as $f) {
            $suj = $f->fun_nome ?: "Funcionário #{$f->fun_id}";
            $id = $f->fun_id;

            // Reg 30 — pessoa física
            if (blank($f->fun_dt_nascimento)) {
                $this->add('30', $suj, $id, 'Data de nascimento', 'Data de nascimento não informada.', self::ERRO, 'funcionario');
            }
            if (blank($f->fun_sexo)) {
                $this->add('30', $suj, $id, 'Sexo', 'Sexo não informado.', self::ERRO, 'funcionario');
            }
            if ($f->fun_cor_raca === null) {
                $this->add('30', $suj, $id, 'Cor/Raça', 'Cor/raça não informada.', self::ERRO, 'funcionario');
            }
            if (blank($f->fun_pais_origem)) {
                $this->add('30', $suj, $id, 'Nacionalidade (campo 14)', 'País de origem/nacionalidade não informado.', self::ERRO, 'funcionario');
            }
            if (blank($f->fun_mun_id_nasc)) {
                $this->add('30', $suj, $id, 'Naturalidade', 'Município de nascimento não informado.', self::ERRO, 'funcionario');
            }
            if (blank($f->fun_filiacao_1) && blank($f->fun_filiacao_2)) {
                $this->add('30', $suj, $id, 'Filiação (8-10)', 'Nenhuma filiação informada.', self::ERRO, 'funcionario');
            }
            if ($f->fun_zona_residencia === null) {
                $this->add('30', $suj, $id, 'Zona de residência (54)', 'Localização/zona de residência não informada.', self::ERRO, 'funcionario');
            }
            if ($this->cpfObrigatorio && blank($f->fun_cpf)) {
                $this->add('30', $suj, $id, 'CPF', 'CPF obrigatório não informado.', self::ERRO, 'funcionario');
            }

            // Reg 40 — lotação/cargo na escola
            foreach ($f->admissoes as $adm) {
                foreach ($adm->lotacoes as $lot) {
                    if (blank($lot->lot_crg_id)) {
                        $this->add('40', $suj, $id, 'Cargo (campo 5)', 'Lotação sem cargo definido.', self::ERRO, 'funcionario');
                    }
                    if (blank($lot->lot_criterio_acesso)) {
                        $this->add('40', $suj, $id, 'Critério de acesso (campo 6)', 'Critério de acesso ao cargo não informado.', self::ERRO, 'funcionario');
                    }
                    if (blank($lot->lot_situacao_funcional)) {
                        $this->add('40', $suj, $id, 'Situação funcional (campo 7)', 'Situação funcional não informada.', self::ERRO, 'funcionario');
                    }
                }
            }
        }
    }

    // --------------------------------------------------------------- helpers

    private function add(string $registro, string $entidade, int $entidadeId, string $campo, string $mensagem, string $severidade, string $tipo = 'escola'): void
    {
        $this->criticas[] = [
            'registro'    => $registro,
            'tipo'        => $tipo,
            'entidade'    => $entidade,
            'entidade_id' => $entidadeId,
            'campo'       => $campo,
            'mensagem'    => $mensagem,
            'severidade'  => $severidade,
        ];
    }

    /** @return array<string,array{erro:int,alerta:int}> */
    private function resumo(): array
    {
        return (new Collection($this->criticas))
            ->groupBy('registro')
            ->map(fn ($grupo) => [
                'erro'   => $grupo->where('severidade', self::ERRO)->count(),
                'alerta' => $grupo->where('severidade', self::ALERTA)->count(),
            ])
            ->all();
    }

    /**
     * Campos do layout ainda SEM validação (sem coluna no modelo). Informativo — o
     * usuário sabe que essas críticas ainda não são checadas. Ver memória de gaps.
     *
     * @return array<int,string>
     */
    private function pendencias(): array
    {
        return [
            'Reg 30 — Deficiência/TEA/Altas habilidades (campo 17, obrigatório) do funcionário: sem colunas.',
            'Reg 10 — Exame de seleção (164) e cotas (165-170): sem colunas.',
            'Reg 60 — Escolarização fora (21) e transporte por matrícula (23, 24-33): desacoplado.',
        ];
    }
}
