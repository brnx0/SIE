<?php

namespace App\Http\Controllers\Serie;

use App\Http\Controllers\Controller;
use App\Http\Requests\Serie\StoreSerieRequest;
use App\Models\Segmento\Segmento;
use App\Models\Serie\Serie;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SerieController extends Controller
{
    public function index(Request $request): Response
    {
        $series = Serie::with('segmento')
            ->when($request->input('search'), function ($q, $s) {
                $q->where(function ($q) use ($s) {
                    $q->whereRaw('ser_nome ilike ?', ["%{$s}%"])
                      ->orWhereRaw('ser_cd_referencia ilike ?', ["%{$s}%"]);
                });
            })
            ->orderBy('seg_id')
            ->orderBy('ser_ordem_no_segmento')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('series/Index', [
            'series'  => $series,
            'filters' => ['search' => $request->input('search', '')],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('series/Create', [
            'segmentos' => Segmento::orderBy('seg_ordem')->get(['seg_id', 'seg_nome_reduzido']),
        ]);
    }

    public function store(StoreSerieRequest $request): RedirectResponse
    {
        Serie::create($request->validated());

        return to_route('series.index')->with('success', 'Série cadastrada com sucesso.');
    }

    public function edit(Serie $serie): Response
    {
        return Inertia::render('series/Edit', [
            'serie'     => $serie,
            'segmentos' => Segmento::orderBy('seg_ordem')->get(['seg_id', 'seg_nome_reduzido']),
        ]);
    }

    public function update(StoreSerieRequest $request, Serie $serie): RedirectResponse
    {
        $serie->update($request->validated());

        return to_route('series.index')->with('success', 'Série atualizada com sucesso.');
    }

    public function destroy(Serie $serie): RedirectResponse
    {
        return $this->safeDelete($serie)
            ?? to_route('series.index')->with('success', 'Série removida com sucesso.');
    }
}
