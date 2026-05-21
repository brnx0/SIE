<?php

namespace App\Http\Controllers\Parametro;

use App\Http\Controllers\Controller;
use App\Http\Requests\Parametro\UpdateParametroEntidadeRequest;
use App\Models\Parametro\AnoLetivo;
use App\Models\Parametro\ParametroEntidade;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ParametroController extends Controller
{
    public function edit(): Response
    {
        $parametro = ParametroEntidade::with('municipio:mun_id,mun_nome,mun_uf')->firstOrFail();

        $anosLetivos = AnoLetivo::with([
            'createdBy:id,name',
            'updatedBy:id,name',
        ])
            ->orderByDesc('anl_ano')
            ->get();

        return Inertia::render('parametros/Edit', [
            'parametro' => $parametro,
            'anosLetivos' => $anosLetivos,
        ]);
    }

    public function update(UpdateParametroEntidadeRequest $request): RedirectResponse
    {
        $entidade = ParametroEntidade::firstOrFail();

        DB::transaction(function () use ($request, $entidade) {
            $data = $request->safe()->except(['par_logomarca', 'par_brasao']);

            if ($request->hasFile('par_logomarca')) {
                if ($entidade->par_logomarca) {
                    Storage::disk('public')->delete($entidade->par_logomarca);
                }
                $data['par_logomarca'] = $request->file('par_logomarca')->store('parametros', 'public');
            }

            if ($request->hasFile('par_brasao')) {
                if ($entidade->par_brasao) {
                    Storage::disk('public')->delete($entidade->par_brasao);
                }
                $data['par_brasao'] = $request->file('par_brasao')->store('parametros', 'public');
            }

            $entidade->update($data);
        });

        return to_route('parametros.edit')->with('success', 'Parâmetros atualizados com sucesso.');
    }
}
