<?php

namespace App\Http\Controllers\Parametro;

use App\Http\Controllers\Controller;
use App\Http\Requests\Parametro\StoreGradeDisciplinarRequest;
use App\Models\Parametro\GradeDisciplinar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GradeDisciplinarController extends Controller
{
    /**
     * Lista lazy via API: GET /api/grade-disciplinar?anl_id&seg_id&ser_id
     */
    public function index(Request $request): JsonResponse
    {
        $anlId = (int) $request->input('anl_id');
        $segId = (int) $request->input('seg_id');
        $serId = (int) $request->input('ser_id');

        if (! $anlId || ! $segId || ! $serId) {
            return response()->json([]);
        }

        $grade = GradeDisciplinar::with('disciplina:dis_id,dis_nome,dis_sigla')
            ->where('grd_anl_id', $anlId)
            ->where('grd_seg_id', $segId)
            ->where('grd_ser_id', $serId)
            ->orderBy('grd_ordem')
            ->get();

        return response()->json($grade);
    }

    public function store(StoreGradeDisciplinarRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['grd_fl_ativo'] = $request->boolean('grd_fl_ativo', true);

        // Ordem auto-atribuída = próximo número na combinação
        $data['grd_ordem'] = GradeDisciplinar::where('grd_anl_id', $data['grd_anl_id'])
            ->where('grd_seg_id', $data['grd_seg_id'])
            ->where('grd_ser_id', $data['grd_ser_id'])
            ->max('grd_ordem') + 1;

        GradeDisciplinar::create($data);

        return back()->with('success', 'Disciplina adicionada à grade.');
    }

    public function update(StoreGradeDisciplinarRequest $request, GradeDisciplinar $grade): RedirectResponse
    {
        $data = $request->validated();
        $data['grd_fl_ativo'] = $request->boolean('grd_fl_ativo', $grade->grd_fl_ativo);

        // Ordem não é alterada aqui — endpoint dedicado
        unset($data['grd_ordem']);

        $grade->update($data);

        return back()->with('success', 'Disciplina atualizada.');
    }

    public function destroy(GradeDisciplinar $grade): RedirectResponse
    {
        return $this->safeDelete($grade)
            ?? back()->with('success', 'Disciplina removida da grade.');
    }

    /**
     * Reordenação: PATCH /parametros/grade-disciplinar/{grade}/ordem
     * Body: direcao = 'up' | 'down' — faz swap com o vizinho na mesma combinação.
     */
    public function reordenar(Request $request, GradeDisciplinar $grade): RedirectResponse
    {
        $direcao = $request->input('direcao');
        if (! in_array($direcao, ['up', 'down'], true)) {
            return back()->with('error', 'Direção inválida.');
        }

        $op = $direcao === 'up' ? '<' : '>';
        $sort = $direcao === 'up' ? 'desc' : 'asc';

        $vizinho = GradeDisciplinar::where('grd_anl_id', $grade->grd_anl_id)
            ->where('grd_seg_id', $grade->grd_seg_id)
            ->where('grd_ser_id', $grade->grd_ser_id)
            ->where('grd_ordem', $op, $grade->grd_ordem)
            ->orderBy('grd_ordem', $sort)
            ->first();

        if (! $vizinho) {
            return back();
        }

        DB::transaction(function () use ($grade, $vizinho) {
            $tmp = $grade->grd_ordem;
            $grade->update(['grd_ordem' => $vizinho->grd_ordem]);
            $vizinho->update(['grd_ordem' => $tmp]);
        });

        return back();
    }

    /**
     * Clona grade de outro ano letivo para o ano atual.
     * Body: origem_anl_id, destino_anl_id, seg_id, ser_id
     */
    public function clonar(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'origem_anl_id'  => ['required', 'integer', 'exists:cfg_ano_letivo,anl_id'],
            'destino_anl_id' => ['required', 'integer', 'exists:cfg_ano_letivo,anl_id', 'different:origem_anl_id'],
            'seg_id'         => ['required', 'integer', 'exists:edu_segmento,seg_id'],
            'ser_id'         => ['required', 'integer', 'exists:edu_serie,ser_id'],
        ]);

        // Destino já preenchido? Não sobrescreve
        $jaExiste = GradeDisciplinar::where('grd_anl_id', $data['destino_anl_id'])
            ->where('grd_seg_id', $data['seg_id'])
            ->where('grd_ser_id', $data['ser_id'])
            ->exists();

        if ($jaExiste) {
            return back()->with('error', 'Já existem disciplinas para o ano de destino — clone bloqueado.');
        }

        $origem = GradeDisciplinar::where('grd_anl_id', $data['origem_anl_id'])
            ->where('grd_seg_id', $data['seg_id'])
            ->where('grd_ser_id', $data['ser_id'])
            ->orderBy('grd_ordem')
            ->get();

        if ($origem->isEmpty()) {
            return back()->with('error', 'Ano de origem não possui disciplinas cadastradas.');
        }

        DB::transaction(function () use ($origem, $data) {
            foreach ($origem as $linha) {
                GradeDisciplinar::create([
                    'grd_anl_id'           => $data['destino_anl_id'],
                    'grd_seg_id'           => $linha->grd_seg_id,
                    'grd_ser_id'           => $linha->grd_ser_id,
                    'grd_dis_id'           => $linha->grd_dis_id,
                    'grd_ordem'            => $linha->grd_ordem,
                    'grd_nome_alternativo' => $linha->grd_nome_alternativo,
                    'grd_fl_ativo'         => $linha->grd_fl_ativo,
                ]);
            }
        });

        return back()->with('success', "{$origem->count()} disciplina(s) clonada(s) do ano anterior.");
    }
}
