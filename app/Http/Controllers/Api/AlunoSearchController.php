<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Aluno\Aluno;
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

        $alunos = Aluno::query()
            ->with('municipioNascimento:mun_id,mun_nome,mun_uf,mun_codigo_ibge')
            ->where('aln_fl_ativo', true)
            ->where(function ($query) use ($q) {
                $query->whereRaw('aln_nome ilike ?', ["%{$q}%"])
                    ->orWhere('aln_nr_matricula', 'like', "%{$q}%")
                    ->orWhere('aln_cpf', 'like', "%{$q}%");
            })
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
            'aln_cpf'         => $a->aln_cpf,
            'municipio_nascimento' => $a->municipioNascimento,
        ]));
    }
}
