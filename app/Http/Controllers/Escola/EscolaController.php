<?php

namespace App\Http\Controllers\Escola;

use App\Http\Controllers\Controller;
use App\Http\Requests\Escola\StoreEscolaRequest;
use App\Http\Requests\Escola\UpdateEscolaRequest;
use App\Models\Escola\Escola;
use App\Models\Parametro\AnoLetivo;
use App\Models\Segmento\Segmento;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class EscolaController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Escola::query()
            ->with([
                'municipio:mun_id,mun_nome,mun_uf',
                'bairro:bai_id,bai_nome',
                'gerencia:ger_id,ger_nome,ger_sigla',
            ])
            ->orderBy('esc_nome');

        if ($search = $request->string('search')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('esc_nome', 'ilike', "%{$search}%")
                    ->orWhere('esc_apelido', 'ilike', "%{$search}%")
                    ->orWhere('esc_cd_inep', 'like', "%{$search}%")
                    ->orWhere('esc_cnpj', 'like', "%{$search}%");
            });
        }

        return Inertia::render('escolas/Index', [
            'escolas' => $query->paginate(15)->withQueryString(),
            'filters' => ['search' => $search],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('escolas/Create');
    }

    public function store(StoreEscolaRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $data = $request->safe()->except(['esc_logo']);

            if ($request->hasFile('esc_logo')) {
                $data['esc_logo'] = $request->file('esc_logo')->store('escolas', 'public');
            }

            Escola::create($data);
        });

        return to_route('escolas.index')->with('success', 'Escola cadastrada com sucesso.');
    }

    public function edit(Escola $escola): Response
    {
        $escola->load([
            'municipio:mun_id,mun_nome,mun_uf',
            'bairro:bai_id,bai_nome,bai_mun_id',
            'gerencia:ger_id,ger_nome,ger_sigla,ger_uf',
        ]);

        $escolaSegmentos = $escola->segmentos()
            ->with([
                'segmento:seg_id,seg_nome_reduzido,seg_nome_completo',
                'anoLetivoInicio:anl_id,anl_ano,anl_fl_em_exercicio',
                'anoLetivoFim:anl_id,anl_ano,anl_fl_em_exercicio',
                'serieInicio:ser_id,ser_nome',
                'serieFim:ser_id,ser_nome',
            ])
            ->orderBy('anl_id_inicio', 'desc')
            ->get();

        return Inertia::render('escolas/Edit', [
            'escola'           => $escola,
            'escolaSegmentos'  => $escolaSegmentos,
            'segmentos'        => Segmento::where('seg_fl_ativo', true)->orderBy('seg_ordem')->get(['seg_id', 'seg_nome_reduzido']),
            'anosLetivos'      => AnoLetivo::orderBy('anl_ano', 'desc')->get(['anl_id', 'anl_ano', 'anl_fl_em_exercicio']),
        ]);
    }

    public function update(UpdateEscolaRequest $request, Escola $escola): RedirectResponse
    {
        DB::transaction(function () use ($request, $escola) {
            $data = $request->safe()->except(['esc_logo']);

            if ($request->hasFile('esc_logo')) {
                if ($escola->esc_logo) {
                    Storage::disk('public')->delete($escola->esc_logo);
                }
                $data['esc_logo'] = $request->file('esc_logo')->store('escolas', 'public');
            }

            $escola->update($data);
        });

        return to_route('escolas.index')->with('success', 'Escola atualizada com sucesso.');
    }

    public function destroy(Escola $escola): RedirectResponse
    {
        if ($escola->esc_logo) {
            Storage::disk('public')->delete($escola->esc_logo);
        }
        $escola->delete();

        return to_route('escolas.index')->with('success', 'Escola removida com sucesso.');
    }
}
