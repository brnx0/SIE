<?php

namespace App\Http\Controllers;

use App\Models\Aluno\Aluno;
use App\Models\Funcionario\Funcionario;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serve fotos via PHP em vez do link simbólico /storage.
 *
 * Evita 403/404 em ambientes onde o nginx não segue o symlink public/storage
 * (disable_symlinks) ou não tem permissão nos arquivos gravados pelo php-fpm.
 * O arquivo é lido do disco "public" e devolvido diretamente.
 */
class MidiaController extends Controller
{
    public function alunoFoto(Aluno $aluno): BinaryFileResponse
    {
        return $this->servir($aluno->aln_foto);
    }

    public function funcionarioFoto(Funcionario $funcionario): BinaryFileResponse
    {
        return $this->servir($funcionario->fun_foto);
    }

    private function servir(?string $caminho): BinaryFileResponse
    {
        abort_if(! $caminho || ! Storage::disk('public')->exists($caminho), 404);

        return response()
            ->file(Storage::disk('public')->path($caminho))
            ->setMaxAge(86400)      // cache no navegador por 1 dia
            ->setPublic();
    }
}
