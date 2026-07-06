<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serve arquivos do disco "public" (logos, fotos) via PHP. No deploy com nginx
 * no HOST e storage/app em VOLUME do container, o symlink public/storage não
 * alcança os uploads — o nginx cai no index.php e esta rota entrega o arquivo
 * direto do volume. Em dev o nginx serve o estático e isto nem é acionado.
 */
class PublicStorageController extends Controller
{
    public function __invoke(Request $request, string $path): StreamedResponse
    {
        $disk = Storage::disk('public');
        abort_unless($disk->exists($path), 404);

        return $disk->response($path);
    }
}
