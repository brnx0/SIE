<?php

namespace App\Http\Controllers\Diario;

use App\Http\Controllers\Controller;
use App\Models\Diario\DiarioDiagnostico;
use App\Models\Parametro\TermoDiagnostico;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Diário › Cadastro › Termos da avaliação diagnóstica.
 *
 * A coordenação define o texto/sigla de cada resultado para TODA a rede. As
 * opções são fixas (DiarioDiagnostico::OPCOES) e é a chave delas que fica gravada
 * nos lançamentos — renomear não altera nenhum resultado já registrado.
 * Acesso (ver/editar) governado pela matriz de telas (/diario/termos-diagnostico).
 */
class TermoDiagnosticoController extends Controller
{
    public function index(): Response
    {
        $padrao = TermoDiagnostico::SIGLAS_PADRAO;
        $termos = TermoDiagnostico::termos();
        $meta = TermoDiagnostico::query()
            ->with('updatedBy:id,name')
            ->get()
            ->keyBy('tdg_opcao');

        return Inertia::render('diario/termos-diagnostico/Index', [
            'termos' => collect($termos)->map(fn ($t, $opcao) => [
                'opcao'            => $opcao,
                'descricao'        => $t['descricao'],
                'sigla'            => $t['sigla'],
                'descricao_padrao' => DiarioDiagnostico::OPCOES[$opcao],
                'sigla_padrao'     => $padrao[$opcao] ?? '',
                'updated_at'       => $meta->get($opcao)?->tdg_updated_at?->toIso8601String(),
                'updated_by'       => $meta->get($opcao)?->updatedBy?->name,
            ])->values(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $opcoes = array_keys(DiarioDiagnostico::OPCOES);

        // Chaves planas (descricao_autonomia, sigla_apoio...) = id do input no form,
        // para o foco no primeiro campo inválido funcionar (useErrorFocus).
        $rules = [];
        $attributes = [];
        foreach ($opcoes as $opcao) {
            $rules["descricao_{$opcao}"] = ['required', 'string', 'max:60'];
            $rules["sigla_{$opcao}"]     = ['required', 'string', 'max:5'];
            $attributes["descricao_{$opcao}"] = 'descrição';
            $attributes["sigla_{$opcao}"]     = 'sigla';
        }
        $data = $request->validate($rules, [], $attributes);

        // Termos/siglas repetidos deixariam o resultado ambíguo na legenda.
        $descricoes = [];
        $siglas = [];
        $erros = [];
        foreach ($opcoes as $opcao) {
            $d = mb_strtoupper(trim($data["descricao_{$opcao}"]), 'UTF-8');
            $s = mb_strtoupper(trim($data["sigla_{$opcao}"]), 'UTF-8');
            if (in_array($d, $descricoes, true)) $erros["descricao_{$opcao}"] = 'Descrição repetida em outro resultado.';
            if (in_array($s, $siglas, true)) $erros["sigla_{$opcao}"] = 'Sigla repetida em outro resultado.';
            $descricoes[] = $d;
            $siglas[] = $s;
        }
        if ($erros) {
            throw \Illuminate\Validation\ValidationException::withMessages($erros);
        }

        foreach ($opcoes as $i => $opcao) {
            $termo = TermoDiagnostico::firstOrNew(['tdg_opcao' => $opcao]);
            $termo->fill([
                'tdg_descricao' => trim($data["descricao_{$opcao}"]),
                'tdg_sigla'     => trim($data["sigla_{$opcao}"]),
                'tdg_ordem'     => $i + 1,
            ]);
            if ($termo->isDirty()) {
                $termo->tdg_updated_by_id = $request->user()->id;
                $termo->save();
            }
        }

        return back()->with('success', 'Termos da avaliação diagnóstica salvos.');
    }
}
