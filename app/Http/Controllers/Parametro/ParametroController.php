<?php

namespace App\Http\Controllers\Parametro;

use App\Http\Controllers\Controller;
use App\Http\Requests\Parametro\UpdateParametroEntidadeRequest;
use App\Models\Parametro\AnoLetivo;
use App\Models\Parametro\GradeHorario;
use App\Models\Parametro\ParametroEntidade;
use App\Models\Parametro\Unidade;
use App\Models\Segmento\Segmento;
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

        $unidades = Unidade::with('anoLetivo:anl_id,anl_ano')
            ->orderBy('uni_anl_id')
            ->orderBy('uni_numero')
            ->get();

        $segmentos     = Segmento::where('seg_fl_ativo', true)->orderBy('seg_ordem')->get(['seg_id', 'seg_nome_reduzido', 'seg_nome_completo']);
        $gradeHorarios = GradeHorario::with('segmento:seg_id,seg_nome_reduzido')
            ->orderBy('grh_seg_id')
            ->orderBy('grh_turno')
            ->orderBy('grh_ordem')
            ->get();

        return Inertia::render('parametros/Edit', [
            'parametro'     => $parametro,
            'anosLetivos'   => $anosLetivos,
            'unidades'      => $unidades,
            'segmentos'     => $segmentos,
            'gradeHorarios' => $gradeHorarios,
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

        ParametroEntidade::clearCache();

        return to_route('parametros.edit')->with('success', 'Parâmetros atualizados com sucesso.');
    }
}
