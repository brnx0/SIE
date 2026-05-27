<?php

namespace App\Http\Controllers\Segmento;

use App\Http\Controllers\Controller;
use App\Http\Requests\Segmento\StoreSegmentoRequest;
use App\Models\Segmento\Segmento;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SegmentoController extends Controller
{
    public function index(Request $request): Response
    {
        $segmentos = Segmento::query()
            ->when($request->input('search'), function ($q, $s) {
                $q->where(function ($q) use ($s) {
                    $q->whereRaw('seg_nome_reduzido ilike ?', ["%{$s}%"])
                      ->orWhereRaw('seg_nome_completo ilike ?', ["%{$s}%"])
                      ->orWhereRaw('seg_cd_inep ilike ?', ["%{$s}%"]);
                });
            })
            ->orderBy('seg_ordem')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('segmentos/Index', [
            'segmentos' => $segmentos,
            'filters'   => ['search' => $request->input('search', '')],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('segmentos/Create');
    }

    public function store(StoreSegmentoRequest $request): RedirectResponse
    {
        Segmento::create($request->validated());

        return to_route('segmentos.index')->with('success', 'Segmento cadastrado com sucesso.');
    }

    public function edit(Segmento $segmento): Response
    {
        return Inertia::render('segmentos/Edit', ['segmento' => $segmento]);
    }

    public function update(StoreSegmentoRequest $request, Segmento $segmento): RedirectResponse
    {
        $segmento->update($request->validated());

        return to_route('segmentos.index')->with('success', 'Segmento atualizado com sucesso.');
    }

    public function destroy(Segmento $segmento): RedirectResponse
    {
        return $this->safeDelete($segmento)
            ?? to_route('segmentos.index')->with('success', 'Segmento removido com sucesso.');
    }
}
