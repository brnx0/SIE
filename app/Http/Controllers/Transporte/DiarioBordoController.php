<?php

namespace App\Http\Controllers\Transporte;

use App\Http\Controllers\Controller;
use App\Http\Requests\Transporte\StoreDiarioBordoRequest;
use App\Models\Transporte\DiarioBordo;
use App\Models\Transporte\Ocorrencia;
use App\Models\Transporte\Rota;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class DiarioBordoController extends Controller
{
    public function index(Request $request): Response
    {
        $perPage = in_array((int) $request->input('per_page'), [10, 25, 50])
            ? (int) $request->input('per_page')
            : 10;

        $diarios = DiarioBordo::query()
            ->with([
                'rota:rot_id,rot_nome,rot_turno',
                'veiculo:vei_id,vei_placa,vei_capacidade',
                'motorista:mot_id,mot_nome',
            ])
            ->withCount([
                'passageiros',
                'passageiros as embarcados_count' => fn ($q) => $q->where('tdp_fl_embarcou', true),
                'ocorrencias',
            ])
            ->when($request->integer('rot_id'), fn ($q, $v) => $q->where('tdb_rot_id', $v))
            ->when($request->integer('vei_id'), fn ($q, $v) => $q->where('tdb_vei_id', $v))
            ->when($request->integer('mot_id'), fn ($q, $v) => $q->where('tdb_mot_id', $v))
            ->when($request->date('dt_inicio'), fn ($q, $v) => $q->whereDate('tdb_dt_viagem', '>=', $v))
            ->when($request->date('dt_fim'), fn ($q, $v) => $q->whereDate('tdb_dt_viagem', '<=', $v))
            ->orderByDesc('tdb_dt_viagem')
            ->orderByDesc('tdb_id')
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('transporte/diario-bordo/Index', [
            'diarios' => $diarios,
            'filters' => array_merge(
                $request->only(['rot_id', 'vei_id', 'mot_id', 'dt_inicio', 'dt_fim']),
                ['per_page' => $perPage],
            ),
        ]);
    }

    /**
     * Abre o registro do dia para a rota (N7): cria com defaults da rota +
     * snapshot da chamada, ou redireciona ao registro já existente.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'tdb_rot_id'    => ['required', 'integer', 'exists:edu_transporte_rota,rot_id'],
            'tdb_dt_viagem' => ['required', 'date'],
        ], [], [
            'tdb_rot_id'    => 'rota',
            'tdb_dt_viagem' => 'data da viagem',
        ]);

        $existente = DiarioBordo::where('tdb_rot_id', $data['tdb_rot_id'])
            ->whereDate('tdb_dt_viagem', $data['tdb_dt_viagem'])
            ->first();

        if ($existente) {
            return to_route('transporte.diario-bordo.edit', $existente)
                ->with('success', 'Já existia um registro para esta rota/data — aberto para edição.');
        }

        $rota = Rota::findOrFail($data['tdb_rot_id']);

        $diario = DB::transaction(function () use ($rota, $data) {
            $diario = DiarioBordo::create([
                'tdb_rot_id'    => $rota->rot_id,
                'tdb_dt_viagem' => $data['tdb_dt_viagem'],
                'tdb_vei_id'    => $rota->rot_vei_id,
                'tdb_mot_id'    => $rota->rot_mot_id,
                'tdb_tmo_id'    => $rota->rot_tmo_id,
            ]);

            $this->snapshotChamada($diario);

            return $diario;
        });

        return to_route('transporte.diario-bordo.edit', $diario)
            ->with('success', 'Registro de viagem criado com a chamada dos passageiros ativos.');
    }

    public function edit(DiarioBordo $diario): Response
    {
        $diario->load([
            'rota:rot_id,rot_nome,rot_turno,rot_anl_id,rot_vei_id,rot_mot_id,rot_tmo_id',
            'rota.anoLetivo:anl_id,anl_ano',
            'veiculo:vei_id,vei_placa,vei_capacidade',
            'motorista:mot_id,mot_nome',
            'monitor:tmo_id,tmo_nome',
            'passageiros.passageiro.ponto:pto_id,pto_nome,pto_ordem',
            'passageiros.passageiro.aluno:aln_id,aln_nome',
            'passageiros.passageiro.usuarioExterno:tue_id,tue_nome',
            'ocorrencias',
        ]);

        // Passageiros da rota ainda não presentes na chamada (para "Sincronizar").
        $pasNaChamada = $diario->passageiros->pluck('tdp_pas_id')->all();
        $pendentesSync = $diario->rota
            ? $this->passageirosAtivosQuery($diario->rota->rot_id, $diario->tdb_dt_viagem)
                ->whereNotIn('pas_id', $pasNaChamada)
                ->count()
            : 0;

        return Inertia::render('transporte/diario-bordo/Edit', [
            'diario'   => [
                'tdb_id'         => $diario->tdb_id,
                'tdb_rot_id'     => $diario->tdb_rot_id,
                'tdb_dt_viagem'  => $diario->tdb_dt_viagem instanceof \DateTimeInterface ? $diario->tdb_dt_viagem->format('Y-m-d') : (string) $diario->tdb_dt_viagem,
                'tdb_vei_id'     => $diario->tdb_vei_id,
                'tdb_mot_id'     => $diario->tdb_mot_id,
                'tdb_tmo_id'     => $diario->tdb_tmo_id,
                'tdb_hr_saida'   => $diario->tdb_hr_saida ? substr($diario->tdb_hr_saida, 0, 5) : null,
                'tdb_hr_chegada' => $diario->tdb_hr_chegada ? substr($diario->tdb_hr_chegada, 0, 5) : null,
                'tdb_km_inicial' => $diario->tdb_km_inicial,
                'tdb_km_final'   => $diario->tdb_km_final,
                'tdb_obs'        => $diario->tdb_obs,
                'rota'           => $diario->rota ? [
                    'rot_id'    => $diario->rota->rot_id,
                    'rot_nome'  => $diario->rota->rot_nome,
                    'rot_turno' => $diario->rota->rot_turno,
                    'anl_ano'   => $diario->rota->anoLetivo?->anl_ano,
                ] : null,
                'veiculo'   => $diario->veiculo ? ['vei_id' => $diario->veiculo->vei_id, 'vei_placa' => $diario->veiculo->vei_placa, 'vei_capacidade' => $diario->veiculo->vei_capacidade] : null,
                'motorista' => $diario->motorista ? ['mot_id' => $diario->motorista->mot_id, 'mot_nome' => $diario->motorista->mot_nome] : null,
                'monitor'   => $diario->monitor ? ['tmo_id' => $diario->monitor->tmo_id, 'tmo_nome' => $diario->monitor->tmo_nome] : null,
            ],
            'chamada'       => $this->chamadaSerializada($diario),
            'ocorrencias'   => $diario->ocorrencias->map(fn ($o) => [
                'toc_id'        => $o->toc_id,
                'toc_tipo'      => $o->toc_tipo,
                'toc_hr'        => $o->toc_hr ? substr($o->toc_hr, 0, 5) : null,
                'toc_descricao' => $o->toc_descricao,
            ]),
            'pendentesSync' => $pendentesSync,
            'tiposOcorrencia' => Ocorrencia::TIPOS,
        ]);
    }

    public function update(StoreDiarioBordoRequest $request, DiarioBordo $diario): RedirectResponse
    {
        $diario->update($request->validated());

        return to_route('transporte.diario-bordo.edit', $diario)->with('success', 'Registro atualizado com sucesso.');
    }

    public function destroy(DiarioBordo $diario): RedirectResponse
    {
        DB::transaction(function () use ($diario) {
            $diario->passageiros()->delete();  // chamada (hard delete)
            $diario->ocorrencias()->delete();  // ocorrências (hard delete)
            $diario->forceDelete();
        });

        return to_route('transporte.diario-bordo.index')->with('success', 'Registro removido com sucesso.');
    }

    /**
     * Grava a chamada de embarque/desembarque (N9: desembarque exige embarque).
     */
    public function chamada(Request $request, DiarioBordo $diario): RedirectResponse
    {
        $data = $request->validate([
            'itens'                     => ['required', 'array'],
            'itens.*.tdp_id'            => ['required', 'integer'],
            'itens.*.fl_embarcou'       => ['boolean'],
            'itens.*.fl_desembarcou'    => ['boolean'],
            'itens.*.hr_embarque'       => ['nullable', 'date_format:H:i'],
            'itens.*.hr_desembarque'    => ['nullable', 'date_format:H:i'],
            'itens.*.obs'               => ['nullable', 'string', 'max:200'],
        ]);

        $idsValidos = $diario->passageiros()->pluck('tdp_id')->all();

        DB::transaction(function () use ($data, $diario, $idsValidos) {
            foreach ($data['itens'] as $item) {
                if (! in_array((int) $item['tdp_id'], $idsValidos, true)) {
                    continue;
                }

                $embarcou    = (bool) ($item['fl_embarcou'] ?? false);
                $desembarcou = (bool) ($item['fl_desembarcou'] ?? false);
                // N9: sem embarque não há desembarque.
                if (! $embarcou) {
                    $desembarcou = false;
                }

                $diario->passageiros()->where('tdp_id', $item['tdp_id'])->update([
                    'tdp_fl_embarcou'    => $embarcou,
                    'tdp_fl_desembarcou' => $desembarcou,
                    'tdp_hr_embarque'    => $item['hr_embarque'] ?? null,
                    'tdp_hr_desembarque' => $item['hr_desembarque'] ?? null,
                    'tdp_obs'            => $item['obs'] ?? null,
                ]);
            }
        });

        return back()->with('success', 'Chamada gravada com sucesso.');
    }

    /**
     * Adiciona à chamada os passageiros vinculados à rota depois da criação do registro.
     */
    public function sincronizarChamada(DiarioBordo $diario): RedirectResponse
    {
        $adicionados = $this->snapshotChamada($diario);

        return back()->with('success', $adicionados > 0
            ? "{$adicionados} passageiro(s) adicionado(s) à chamada."
            : 'A chamada já está sincronizada com os passageiros da rota.');
    }

    public function storeOcorrencia(Request $request, DiarioBordo $diario): RedirectResponse
    {
        $data = $request->validate([
            'toc_tipo'      => ['required', 'string', 'in:' . implode(',', array_keys(Ocorrencia::TIPOS))],
            'toc_hr'        => ['nullable', 'date_format:H:i'],
            'toc_descricao' => ['required', 'string', 'max:2000'],
        ], [], [
            'toc_tipo'      => 'tipo',
            'toc_hr'        => 'hora',
            'toc_descricao' => 'descrição',
        ]);

        $diario->ocorrencias()->create([
            'toc_tipo'      => $data['toc_tipo'],
            'toc_hr'        => $data['toc_hr'] ?? null,
            'toc_descricao' => $data['toc_descricao'],
        ]);

        return back()->with('success', 'Ocorrência registrada.');
    }

    public function destroyOcorrencia(DiarioBordo $diario, Ocorrencia $ocorrencia): RedirectResponse
    {
        abort_unless($ocorrencia->toc_tdb_id === $diario->tdb_id, 404);

        $ocorrencia->delete();

        return back()->with('success', 'Ocorrência removida.');
    }

    /**
     * Cria linhas de chamada para os passageiros ativos da rota que ainda não
     * estão no diário. Retorna a quantidade criada. Idempotente (unique tdb+pas).
     */
    private function snapshotChamada(DiarioBordo $diario): int
    {
        $existentes = $diario->passageiros()->pluck('tdp_pas_id')->all();

        $novos = $this->passageirosAtivosQuery($diario->tdb_rot_id, $diario->tdb_dt_viagem)
            ->whereNotIn('pas_id', $existentes)
            ->pluck('pas_id');

        foreach ($novos as $pasId) {
            $diario->passageiros()->create([
                'tdp_pas_id'      => $pasId,
                'tdp_fl_embarcou' => false,
                'tdp_fl_desembarcou' => false,
            ]);
        }

        return $novos->count();
    }

    /**
     * Passageiros da rota ativos na data (período pas_dt_inicio..pas_dt_fim).
     */
    private function passageirosAtivosQuery(int $rotId, $dtViagem)
    {
        $dt = $dtViagem instanceof \DateTimeInterface ? $dtViagem->format('Y-m-d') : (string) $dtViagem;

        return \App\Models\Transporte\Passageiro::query()
            ->where('pas_rot_id', $rotId)
            ->where(fn ($q) => $q->whereNull('pas_dt_inicio')->orWhereDate('pas_dt_inicio', '<=', $dt))
            ->where(fn ($q) => $q->whereNull('pas_dt_fim')->orWhereDate('pas_dt_fim', '>=', $dt));
    }

    private function chamadaSerializada(DiarioBordo $diario)
    {
        return $diario->passageiros
            ->sortBy(fn ($tdp) => [$tdp->passageiro?->ponto?->pto_ordem ?? 999, $tdp->tdp_id])
            ->values()
            ->map(fn ($tdp) => [
                'tdp_id'             => $tdp->tdp_id,
                'tdp_pas_id'         => $tdp->tdp_pas_id,
                'tdp_fl_embarcou'    => $tdp->tdp_fl_embarcou,
                'tdp_fl_desembarcou' => $tdp->tdp_fl_desembarcou,
                'tdp_hr_embarque'    => $tdp->tdp_hr_embarque ? substr($tdp->tdp_hr_embarque, 0, 5) : null,
                'tdp_hr_desembarque' => $tdp->tdp_hr_desembarque ? substr($tdp->tdp_hr_desembarque, 0, 5) : null,
                'tdp_obs'            => $tdp->tdp_obs,
                'nome'               => $tdp->passageiro?->aluno?->aln_nome
                    ?? $tdp->passageiro?->usuarioExterno?->tue_nome
                    ?? '—',
                'tipo'               => $tdp->passageiro?->aluno ? 'aluno' : 'externo',
                'ponto'              => $tdp->passageiro?->ponto ? [
                    'pto_id'    => $tdp->passageiro->ponto->pto_id,
                    'pto_nome'  => $tdp->passageiro->ponto->pto_nome,
                    'pto_ordem' => $tdp->passageiro->ponto->pto_ordem,
                ] : null,
            ]);
    }
}
