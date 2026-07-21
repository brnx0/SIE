<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Alimentacao\Anexo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Anexos (PDF/imagem) de um registro, reaproveitado por contrato e visita.
 *
 * O dono é resolvido pela rota aninhada (ex.: contratos/{id}/anexos), então o
 * middleware `tela` já governa a permissão pela tela dona. A validação (tipo e
 * tamanho) é feita aqui, no servidor — a do cliente é só conveniência.
 */
trait ComAnexos
{
    /** Extensões/MIME aceitos e teto de 10 MB por arquivo (10240 KB). */
    protected function regrasAnexo(): array
    {
        return [
            'anexos'   => ['required', 'array', 'min:1'],
            'anexos.*' => ['file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
        ];
    }

    /** Valida e grava um ou mais arquivos ligados a `$dono`, na pasta informada. */
    protected function guardarAnexos(Request $request, Model $dono, string $pasta): void
    {
        $request->validate($this->regrasAnexo(), [
            'anexos.required'  => 'Selecione ao menos um arquivo.',
            'anexos.*.mimes'   => 'Cada anexo deve ser PDF ou imagem (JPG, PNG, WEBP).',
            'anexos.*.max'     => 'Cada arquivo pode ter no máximo 10 MB.',
            'anexos.*.file'    => 'Envio de arquivo inválido.',
        ], ['anexos.*' => 'arquivo']);

        foreach ($request->file('anexos') as $arquivo) {
            // Lê os metadados ANTES de mover: store() esvazia o arquivo temporário.
            $nome    = mb_substr($arquivo->getClientOriginalName(), 0, 180);
            $mime    = $arquivo->getMimeType() ?? $arquivo->getClientMimeType();
            $tamanho = $arquivo->getSize();

            $caminho = $arquivo->store("anexos/{$pasta}", 'public');

            $dono->anexos()->create([
                'anx_nome'    => $nome,
                'anx_caminho' => $caminho,
                'anx_mime'    => $mime,
                'anx_tamanho' => $tamanho,
                'anx_usr_id'  => $request->user()?->id,
            ]);
        }
    }

    /** Entrega o arquivo (inline) — 404 se o anexo não for do dono ou sumiu do disco. */
    protected function baixarAnexoDe(Model $dono, Anexo $anexo): BinaryFileResponse
    {
        abort_unless($this->anexoPertence($anexo, $dono), 404);
        abort_unless(Storage::disk('public')->exists($anexo->anx_caminho), 404);

        return response()
            ->file(Storage::disk('public')->path($anexo->anx_caminho))
            ->setContentDisposition('inline', $anexo->anx_nome);
    }

    protected function excluirAnexoDe(Model $dono, Anexo $anexo): void
    {
        abort_unless($this->anexoPertence($anexo, $dono), 404);

        Storage::disk('public')->delete($anexo->anx_caminho);
        $anexo->delete();
    }

    /** Anexos do dono já formatados para a tela. */
    protected function anexosPara(Model $dono): array
    {
        return $dono->anexos()
            ->with('usuario:id,name')
            ->orderByDesc('anx_created_at')
            ->get()
            ->map(fn (Anexo $a) => [
                'anx_id'      => $a->anx_id,
                'anx_nome'    => $a->anx_nome,
                'anx_mime'    => $a->anx_mime,
                'anx_tamanho' => (int) $a->anx_tamanho,
                'eh_imagem'   => $a->ehImagem(),
                'usuario'     => $a->usuario?->name,
                'criado_em'   => $a->anx_created_at?->toDateTimeString(),
            ])
            ->all();
    }

    private function anexoPertence(Anexo $anexo, Model $dono): bool
    {
        return $anexo->anx_anexavel_type === $dono->getMorphClass()
            && (int) $anexo->anx_anexavel_id === (int) $dono->getKey();
    }
}
