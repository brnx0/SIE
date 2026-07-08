<?php

namespace App\Services\Censo\Layouts;

use App\Services\Censo\Contracts\LayoutVersion;

/**
 * Layout de migração do Censo Escolar 2025.
 * Posições validadas contra o parser oficial (i-educar Version2025) e arquivo real.
 * Ver docs/regras/censo_layout_2025.md.
 *
 * Ano seguinte: criar Layout2026 estendendo esta e sobrescrevendo só o que mudar.
 */
class Layout2025 implements LayoutVersion
{
    public function year(): int
    {
        return 2025;
    }

    public function signature(): array
    {
        // tipo => nº de campos (assinatura do arquivo 2025)
        return [
            '00' => 56,
            '10' => 187,
            '20' => 70,
            '30' => 108,
            '40' => 7,
            '50' => 38,
            '60' => 32,
        ];
    }

    public function tiposValidos(): array
    {
        return ['00', '10', '20', '30', '40', '50', '60'];
    }

    /**
     * Mapa das colunas de interesse por registro (posição 1-based => campo).
     * O array bruto completo fica em icr_campos; aqui só o subset nomeado.
     */
    public function fieldMap(string $tipo): array
    {
        return match ($tipo) {
            '00' => [
                2 => 'inep_escola', 3 => 'situacao_func', 4 => 'inicio_ano', 5 => 'fim_ano',
                6 => 'nome', 7 => 'cep', 8 => 'ibge_municipio', 9 => 'ibge_distrito',
                10 => 'logradouro', 11 => 'numero', 12 => 'complemento', 13 => 'bairro',
                14 => 'ddd', 15 => 'telefone', 17 => 'email', 19 => 'zona',
                20 => 'localizacao_dif', 21 => 'dependencia_admin',
                40 => 'unidade_vinculada_ies', 41 => 'inep_sede',
            ],
            '10' => [
                2 => 'inep_escola',
                90 => 'salas_dentro_predio', 91 => 'salas_fora_predio',
                92 => 'salas_climatizadas', 93 => 'salas_acessibilidade',
                96 => 'computadores',
            ],
            '20' => [
                2 => 'inep_escola', 3 => 'cod_turma_sistema', 4 => 'inep_turma',
                5 => 'nome', 6 => 'mediacao', 8 => 'horario_segunda',
                14 => 'atend_escolarizacao', 15 => 'atend_complementar', 16 => 'atend_aee',
                23 => 'local_diferenciado', 24 => 'classe_especial', 26 => 'etapa_educacenso',
            ],
            '30' => [
                2 => 'inep_escola', 3 => 'cod_pessoa_sistema', 4 => 'inep_pessoa',
                5 => 'cpf', 6 => 'nome', 7 => 'nascimento', 8 => 'filiacao',
                9 => 'mae', 10 => 'pai', 11 => 'sexo', 12 => 'cor_raca',
                13 => 'povo_indigena', 14 => 'nacionalidade', 15 => 'pais',
                16 => 'municipio_nascimento', 17 => 'tem_deficiencia',
                50 => 'certidao', 51 => 'pais_residencia', 52 => 'cep',
                53 => 'municipio_residencia', 54 => 'zona_residencia',
                56 => 'escolaridade', 108 => 'email',
            ],
            '40' => [
                2 => 'inep_escola', 3 => 'cod_pessoa_sistema', 4 => 'inep_gestor',
                5 => 'cargo', 6 => 'criterio_acesso', 7 => 'especificacao_criterio',
            ],
            '50' => [
                2 => 'inep_escola', 3 => 'cod_pessoa_sistema', 4 => 'inep_docente',
                5 => 'cod_turma_sistema', 6 => 'inep_turma', 7 => 'funcao', 8 => 'tipo_vinculo',
            ],
            '60' => [
                2 => 'inep_escola', 3 => 'cod_pessoa_sistema', 4 => 'inep_aluno',
                5 => 'cod_turma_sistema', 6 => 'inep_turma', 8 => 'etapa_aluno',
                21 => 'transporte_publico', 22 => 'poder_publico_transporte',
            ],
            default => [],
        };
    }
}
