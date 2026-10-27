<?php

namespace App\Http\Controllers\Diario;

use App\Http\Controllers\Controller;
use App\Models\Diario\DiarioPlanoAnexo;
use App\Models\Diario\DiarioPlanoAula;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Anexos (material de apoio) do plano de aula — tela Diário › Planos de Aula.
 *
 * Gravar/remover: autor do plano ou admin (mesma regra de edição do plano), e
 * nunca em plano aprovado. Abrir: quem pode abrir o plano (autor, admin, ou
 * só-visualização da tela). A coordenação abre pelo PlanoValidacaoController,
 * que confere a lotação na escola. Os anexos não entram no PDF do plano.
 */
class PlanoAulaAnexoController extends Controller
{
    public function store(Request $request, DiarioPlanoAula $plano): RedirectResponse
    {
        $this->abortSeNaoPodeAlterar($plano, $request);

        $request->validate([
            'anexos'   => ['required', 'array', 'min:1'],
            'anexos.*' => ['file', 'mimes:' . implode(',', DiarioPlanoAnexo::EXTENSOES), 'max:' . DiarioPlanoAnexo::MAX_KB],
        ], [
            'anexos.required' => 'Selecione ao menos um arquivo.',
            'anexos.*.mimes'  => 'Formato não permitido. Envie PDF, imagem (JPG, PNG, WEBP) ou documento do Office (DOC, DOCX, XLS, XLSX, PPT, PPTX).',
            'anexos.*.max'    => 'Cada arquivo pode ter no máximo 10 MB.',
            'anexos.*.file'   => 'Envio de arquivo inválido.',
        ]);

        $arquivos = $request->file('anexos');
        $total = $plano->anexos()->count() + count($arquivos);
        if ($total > DiarioPlanoAnexo::MAX_POR_PLANO) {
            throw ValidationException::withMessages([
                'anexos' => 'O plano pode ter no máximo ' . DiarioPlanoAnexo::MAX_POR_PLANO . ' anexos.',
            ]);
        }

        foreach ($arquivos as $arquivo) {
            // Metadados ANTES de mover: store() esvazia o arquivo temporário.
            $nome    = mb_substr($arquivo->getClientOriginalName(), 0, 180);
            $mime    = $arquivo->getMimeType() ?? $arquivo->getClientMimeType();
            $tamanho = $arquivo->getSize();

            $caminho = $arquivo->store("planos-aula/{$plano->dpa_id}", DiarioPlanoAnexo::DISCO);

            $plano->anexos()->create([
                'dax_nome'    => $nome,
                'dax_caminho' => $caminho,
                'dax_mime'    => $mime,
                'dax_tamanho' => $tamanho,
                'dax_user_id' => $request->user()->id,
            ]);
        }

        return back()->with('success', count($arquivos) > 1 ? 'Anexos enviados.' : 'Anexo enviado.');
    }

    public function show(Request $request, DiarioPlanoAula $plano, DiarioPlanoAnexo $anexo): BinaryFileResponse
    {
        $user = $request->user();
        abort_unless(
            $user->isAdmin()
                || (int) $plano->dpa_user_id === (int) $user->id
                || $this->soVisualizacaoTela($request),
            403,
            'Você não tem acesso aos anexos deste plano.'
        );

        return self::entregar($plano, $anexo);
    }

    public function destroy(Request $request, DiarioPlanoAula $plano, DiarioPlanoAnexo $anexo): RedirectResponse
    {
        $this->abortSeNaoPodeAlterar($plano, $request);
        abort_unless((int) $anexo->dax_dpa_id === (int) $plano->dpa_id, 404);

        $anexo->delete(); // o model apaga o arquivo do disco

        return back()->with('success', 'Anexo removido.');
    }

    /** Entrega inline (abre no navegador; Office baixa). 404 se não é do plano ou sumiu. */
    public static function entregar(DiarioPlanoAula $plano, DiarioPlanoAnexo $anexo): BinaryFileResponse
    {
        abort_unless((int) $anexo->dax_dpa_id === (int) $plano->dpa_id, 404);
        $disco = Storage::disk(DiarioPlanoAnexo::DISCO);
        abort_unless($disco->exists($anexo->dax_caminho), 404, 'Arquivo não encontrado.');

        return response()
            ->file($disco->path($anexo->dax_caminho), ['Content-Type' => $anexo->dax_mime])
            ->setContentDisposition('inline', $anexo->dax_nome);
    }

    private function abortSeNaoPodeAlterar(DiarioPlanoAula $plano, Request $request): void
    {
        $user = $request->user();
        abort_unless(
            $user->isAdmin() || (int) $plano->dpa_user_id === (int) $user->id,
            403,
            'Só o autor do plano pode alterar os anexos.'
        );
        abort_if(
            $plano->dpa_status === DiarioPlanoAula::STATUS_APROVADO,
            403,
            'Plano aprovado: os anexos ficam apenas para consulta.'
        );
    }
}
