<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1e293b; }
    .header { padding: 18px 24px 12px; border-bottom: 2px solid #6366f1; }
    .header h1 { font-size: 18px; font-weight: 700; color: #4f46e5; }
    .header p { font-size: 10px; color: #64748b; margin-top: 2px; }
    table { width: 100%; border-collapse: collapse; }
    thead th { background: #f1f5f9; padding: 7px 10px; text-align: left; font-size: 9px; text-transform: uppercase; letter-spacing: 0.05em; color: #475569; border-bottom: 1px solid #e2e8f0; }
    tbody tr { border-bottom: 1px solid #f1f5f9; }
    tbody tr:nth-child(even) { background: #fafafa; }
    td { padding: 7px 10px; vertical-align: middle; }
    .badge { display: inline-block; padding: 2px 8px; border-radius: 9999px; font-size: 9px; font-weight: 600; }
    .badge-open { background: #d1fae5; color: #065f46; }
    .badge-closed { background: #fef3c7; color: #92400e; }
    .badge-warn { background: #ffe4e6; color: #9f1239; }
    .footer { padding: 10px 24px 14px; border-top: 1px solid #e2e8f0; background: #f8fafc; }
    .footer-grid { display: flex; gap: 32px; }
    .footer-item .label { font-size: 9px; text-transform: uppercase; color: #64748b; letter-spacing: 0.05em; }
    .footer-item .value { font-size: 16px; font-weight: 700; color: #1e293b; }
    .meta { font-size: 9px; color: #94a3b8; margin-top: 6px; }
</style>
</head>
<body>
@php
    $fmt = fn ($d) => $d ? \Illuminate\Support\Carbon::parse($d)->format('d/m/Y') : '—';
    $km  = function ($d) {
        if ($d->tdb_km_inicial === null || $d->tdb_km_final === null || $d->tdb_km_final < $d->tdb_km_inicial) return '—';
        return number_format($d->tdb_km_final - $d->tdb_km_inicial, 1, ',', '.') . ' km';
    };
    $periodoTxt = ($periodo['inicio'] ?? null) || ($periodo['fim'] ?? null)
        ? trim(($periodo['inicio'] ? 'de ' . \Illuminate\Support\Carbon::parse($periodo['inicio'])->format('d/m/Y') : '') . ' ' . ($periodo['fim'] ? 'até ' . \Illuminate\Support\Carbon::parse($periodo['fim'])->format('d/m/Y') : ''))
        : null;
@endphp
<div class="header">
    <h1>Transporte Escolar — Diário de Bordo</h1>
    <p>Relatório gerado em {{ now()->format('d/m/Y \à\s H:i') }}@if($periodoTxt) · Período: {{ $periodoTxt }}@endif</p>
</div>
<table>
    <thead>
        <tr>
            <th style="text-align:center">Data</th>
            <th>Rota</th>
            <th>Veículo</th>
            <th>Motorista</th>
            <th style="text-align:center">Km</th>
            <th style="text-align:center">Embarcados</th>
            <th style="text-align:center">Ocorrências</th>
            <th style="text-align:center">Situação</th>
        </tr>
    </thead>
    <tbody>
        @forelse($diarios as $d)
        <tr>
            <td style="text-align:center">{{ $fmt($d->tdb_dt_viagem) }}</td>
            <td><strong>{{ $d->rota?->rot_nome ?? '—' }}</strong></td>
            <td>{{ $d->veiculo?->vei_placa ?? '—' }}</td>
            <td>{{ $d->motorista?->fun_nome ?? '—' }}</td>
            <td style="text-align:center">{{ $km($d) }}</td>
            <td style="text-align:center">{{ $d->embarcados_count }}/{{ $d->passageiros_count }}</td>
            <td style="text-align:center"><span class="badge {{ $d->ocorrencias_count > 0 ? 'badge-warn' : '' }}">{{ $d->ocorrencias_count }}</span></td>
            <td style="text-align:center"><span class="badge {{ $d->tdb_fl_fechado ? 'badge-closed' : 'badge-open' }}">{{ $d->tdb_fl_fechado ? 'Fechado' : 'Aberto' }}</span></td>
        </tr>
        @empty
        <tr><td colspan="8" style="text-align:center;padding:20px;color:#94a3b8">Nenhum registro encontrado.</td></tr>
        @endforelse
    </tbody>
</table>
<div class="footer">
    <div class="footer-grid">
        <div class="footer-item"><div class="label">Registros</div><div class="value">{{ $total }}</div></div>
        <div class="footer-item"><div class="label">Fechados</div><div class="value">{{ $totalFechados }}</div></div>
        <div class="footer-item"><div class="label">Ocorrências</div><div class="value">{{ $totalOcorrencias }}</div></div>
    </div>
    <div class="meta">SIE Matrícula · Exportação automática</div>
</div>
</body>
</html>
