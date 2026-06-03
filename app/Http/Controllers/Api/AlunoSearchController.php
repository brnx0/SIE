<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Aluno\Aluno;
use App\Models\Matricula\Matricula;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AlunoSearchController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $q = $request->string('q')->toString();

        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        $anlId = $request->integer('anl_id') ?: null;

        $alunos = Aluno::query()
            ->with([
                'municipioNascimento:mun_id,mun_nome,mun_uf,mun_codigo_ibge',
                'saude',
            ])
            ->where('aln_fl_ativo', true)
            ->where(function ($query) use ($q) {
                $query->whereRaw('aln_nome ilike ?', ["%{$q}%"])
                    ->orWhere('aln_nr_matricula', 'like', "%{$q}%")
                    ->orWhere('aln_cpf', 'like', "%{$q}%");
            })
            ->when($anlId, fn ($query) =>
                $query->whereHas('matriculas', fn ($m) =>
                    $m->where('tma_situacao', Matricula::SITUACAO_ATIVA)
                      ->whereHas('turma', fn ($t) => $t->where('tur_anl_id', $anlId))
                )
            )
            ->orderBy('aln_nome')
            ->limit(20)
            ->get([
                'aln_id',
                'aln_nome',
                'aln_dt_nascimento',
                'aln_sexo',
                'aln_cor_raca',
                'aln_pais_origem',
                'aln_mun_id_nasc',
                'aln_nr_certidao',
                'aln_nis',
                'aln_filiacao_1',
                'aln_filiacao_1_tipo',
                'aln_filiacao_2',
                'aln_filiacao_2_tipo',
                'aln_cep',
                'aln_logradouro',
                'aln_numero',
                'aln_complemento',
                'aln_bairro',
                'aln_cidade',
                'aln_uf',
                'aln_telefone',
                'aln_email',
                'aln_nr_matricula',
                'aln_cpf',
                'aln_fl_trouxe_transferencia',
                'aln_fl_trouxe_rg',
                'aln_fl_trouxe_reg_nascimento',
                'aln_fl_bolsa_familia',
                'aln_fl_recebe_merenda',
                'aln_fl_usa_transporte',
                'aln_fl_usa_biblioteca',
                'aln_fl_indigena',
                'aln_fl_creche',
            ]);

        return response()->json($alunos->map(fn ($a) => [
            'aln_id'          => $a->aln_id,
            'aln_nome'        => $a->aln_nome,
            'aln_dt_nascimento' => $a->aln_dt_nascimento?->format('Y-m-d'),
            'aln_sexo'        => $a->aln_sexo,
            'aln_cor_raca'    => $a->aln_cor_raca,
            'aln_pais_origem' => $a->aln_pais_origem,
            'aln_mun_id_nasc' => $a->aln_mun_id_nasc,
            'aln_nr_certidao' => $a->aln_nr_certidao,
            'aln_nis'         => $a->aln_nis,
            'aln_filiacao_1'  => $a->aln_filiacao_1,
            'aln_filiacao_1_tipo' => $a->aln_filiacao_1_tipo,
            'aln_filiacao_2'  => $a->aln_filiacao_2,
            'aln_filiacao_2_tipo' => $a->aln_filiacao_2_tipo,
            'aln_cep'         => $a->aln_cep,
            'aln_logradouro'  => $a->aln_logradouro,
            'aln_numero'      => $a->aln_numero,
            'aln_complemento' => $a->aln_complemento,
            'aln_bairro'      => $a->aln_bairro,
            'aln_cidade'      => $a->aln_cidade,
            'aln_uf'          => $a->aln_uf,
            'aln_telefone'    => $a->aln_telefone,
            'aln_email'       => $a->aln_email,
            'aln_nr_matricula'  => $a->aln_nr_matricula,
            'aln_cpf'              => $a->aln_cpf,
            'aln_fl_trouxe_transferencia'  => $a->aln_fl_trouxe_transferencia,
            'aln_fl_trouxe_rg'             => $a->aln_fl_trouxe_rg,
            'aln_fl_trouxe_reg_nascimento' => $a->aln_fl_trouxe_reg_nascimento,
            'aln_fl_bolsa_familia'         => $a->aln_fl_bolsa_familia,
            'aln_fl_recebe_merenda'        => $a->aln_fl_recebe_merenda,
            'aln_fl_usa_transporte'        => $a->aln_fl_usa_transporte,
            'aln_fl_usa_biblioteca'        => $a->aln_fl_usa_biblioteca,
            'aln_fl_indigena'              => $a->aln_fl_indigena,
            'aln_fl_creche'                => $a->aln_fl_creche,
            'municipio_nascimento' => $a->municipioNascimento,
            'saude'                => $a->saude,
        ]));
    }
}
