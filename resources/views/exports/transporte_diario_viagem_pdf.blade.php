<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1e293b; }
    .header { padding: 20px 28px 14px; border-bottom: 2px solid #6366f1; }
    .header h1 { font-size: 18px; font-weight: 700; color: #4f46e5; }
    .header p { font-size: 10px; color: #64748b; margin-top: 3px; }
    .section { padding: 14px 28px 0; }
    .section h2 { font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #475569; margin-bottom: 8px; padding-bottom: 4px; border-bottom: 1px solid #e2e8f0; }
    .grid { width: 100%; }
    .grid td { padding: 4px 8px 4px 0; vertical-align: top; width: 25%; }
    .grid .label { font-size: 9px; text-transform: uppercase; color: #94a3b8; letter-spacing: 0.04em; }
    .grid .value { font-size: 12px; font-weight: 600; color: #1e293b; }
    table.data { width: 100%; border-collapse: collapse; margin-top: 4px; }
    table.data thead th { background: #f1f5f9; padding: 6px 10px; text-align: left; font-size: 9px; text-transform: uppercase; letter-spacing: 0.05em; color: #475569; border-bottom: 1px solid #e2e8f0; }
    table.data tbody tr { border-bottom: 1px solid #f1f5f9; }
    table.data tbody tr:nth-child(even) { background: #fafafa; }
    table.data td { padding: 6px 10px; vertical-align: middle; }
    .badge { display: inline-block; padding: 2px 8px; border-radius: 9999px; font-size: 9px; font-weight: 600; }
    .yes { background: #d1fae5; color: #065f46; }
    .no { background: #f1f5f9; color: #94a3b8; }
    .occ { border: 1px solid #fde68a; background: #fffbeb; border-radius: 8px; padding: 8px 12px; margin-bottom: 8px; }
    .occ .top { font-size: 11px; font-weight: 700; color: #92400e; }
    .occ .desc { font-size: 10px; color: #57534e; margin-top: 3px; white-space: pre-wrap; }
    .muted { color: #94a3b8; }
    .meta { padding: 14px 28px 18px; font-size: 9px; color: #94a3b8; }
</style>
</head>
<body>
@php
    use Illuminate\Support\Carbon;
    $fmtData = fn ($d) => $d ? Carbon::parse($d)->format('d/m/Y') : '—';
    $turnos = ['MATUTINO' => 'Matutino', 'VESPERTINO' => 'Vespertino', 'NOTURNO' => 'Noturno', 'INTEGRAL' => 'Integral'];
    $kmPerc = ($diario->tdb_km_inicial !== null && $diario->tdb_km_final !== null && $diario->tdb_km_final >= $diario->tdb_km_inicial)
        ? number_format($diario->tdb_km_final - $diario->tdb_km_inicial, 1, ',', '.') . ' km' : '—';
    $embarcados = collect($chamada)->where('tdp_fl_embarcou', true)->count();
@endphp
<div class="header">
    <h1>Diário de Bordo — {{ $diario->rota?->rot_nome ?? 'Registro de viagem' }}</h1>
    <p>
        {{ $fmtData($diario->tdb_dt_viagem) }}
        @if($diario->rota) · {{ $turnos[$diario->rota->rot_turno] ?? $diario->rota->rot_turno }}@endif
        · Gerado em {{ now()->format('d/m/Y \à\s H:i') }}
        @if($diario->tdb_fl_fechado) · <strong style="color:#92400e">REGISTRO FECHADO</strong>@endif
    </p>
</div>

<div class="section">
    <h2>Equipe e viagem</h2>
    <table class="grid">
        <tr>
            <td><div class="label">Veículo</div><div class="value">{{ $diario->veiculo?->vei_placa ?? '—' }}</div></td>
            <td><div class="label">Motorista</div><div class="value">{{ $diario->motorista?->fun_nome ?? '—' }}</div></td>
            <td><div class="label">Monitor</div><div class="value">{{ $diario->monitor?->fun_nome ?? '—' }}</div></td>
            <td><div class="label">Capacidade</div><div class="value">{{ $diario->veiculo?->vei_capacidade ?? '—' }}</div></td>
        </tr>
        <tr>
            <td><div class="label">Hora de saída</div><div class="value">{{ $diario->tdb_hr_saida ? substr($diario->tdb_hr_saida, 0, 5) : '—' }}</div></td>
            <td><div class="label">Hora de chegada</div><div class="value">{{ $diario->tdb_hr_chegada ? substr($diario->tdb_hr_chegada, 0, 5) : '—' }}</div></td>
            <td><div class="label">Km percorrido</div><div class="value">{{ $kmPerc }}</div></td>
            <td><div class="label">Embarcados</div><div class="value">{{ $embarcados }}/{{ count($chamada) }}</div></td>
        </tr>
    </table>
    @if($diario->tdb_obs)
        <div style="margin-top:8px"><div class="label" style="font-size:9px;text-transform:uppercase;color:#94a3b8">Observações</div><div style="font-size:11px">{{ $diario->tdb_obs }}</div></div>
    @endif
</div>

<div class="section">
    <h2>Chamada de embarque/desembarque</h2>
    <table class="data">
        <thead>
            <tr>
                <th style="width:32px;text-align:center">#</th>
                <th>Passageiro</th>
                <th>Ponto</th>
                <th style="text-align:center">Embarcou</th>
                <th style="text-align:center">Desembarcou</th>
                <th>Observação</th>
            </tr>
        </thead>
        <tbody>
            @forelse($chamada as $i => $c)
            <tr>
                <td style="text-align:center">{{ $i + 1 }}</td>
                <td>{{ $c['nome'] }} <span class="muted">({{ $c['tipo'] === 'aluno' ? 'aluno' : 'externo' }})</span></td>
                <td>{{ $c['ponto']['pto_nome'] ?? '—' }}</td>
                <td style="text-align:center"><span class="badge {{ $c['tdp_fl_embarcou'] ? 'yes' : 'no' }}">{{ $c['tdp_fl_embarcou'] ? 'Sim' : 'Não' }}</span></td>
                <td style="text-align:center"><span class="badge {{ $c['tdp_fl_desembarcou'] ? 'yes' : 'no' }}">{{ $c['tdp_fl_desembarcou'] ? 'Sim' : 'Não' }}</span></td>
                <td>{{ $c['tdp_obs'] ?? '' }}</td>
            </tr>
            @empty
            <tr><td colspan="6" style="text-align:center;padding:16px;color:#94a3b8">Nenhum passageiro na chamada.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="section">
    <h2>Ocorrências ({{ count($ocorrencias) }})</h2>
    @forelse($ocorrencias as $o)
        <div class="occ">
            <div class="top">{{ $tipos[$o->toc_tipo] ?? $o->toc_tipo }}@if($o->toc_hr) · {{ substr($o->toc_hr, 0, 5) }}@endif</div>
            <div class="desc">{{ $o->toc_descricao }}</div>
        </div>
    @empty
        <div class="muted" style="font-size:10px">Nenhuma ocorrência registrada nesta viagem.</div>
    @endforelse
</div>

<div class="meta">SIE Matrícula · Transporte Escolar · Diário de bordo</div>
</body>
</html>
