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
    td { padding: 7px 10px; vertical-align: top; }
    .badge { display: inline-block; padding: 2px 8px; border-radius: 9999px; font-size: 9px; font-weight: 600; background: #ede9fe; color: #5b21b6; }
    .footer { padding: 10px 24px 14px; border-top: 1px solid #e2e8f0; background: #f8fafc; }
    .footer-grid { display: flex; flex-wrap: wrap; gap: 22px; }
    .footer-item .label { font-size: 9px; text-transform: uppercase; color: #64748b; letter-spacing: 0.05em; }
    .footer-item .value { font-size: 15px; font-weight: 700; color: #1e293b; }
    .meta { font-size: 9px; color: #94a3b8; margin-top: 6px; }
</style>
</head>
<body>
@php
    use Illuminate\Support\Carbon;
    $fmt = fn ($d) => $d ? Carbon::parse($d)->format('d/m/Y') : '—';
    $ini = $filtros['dt_inicio'] ?? null;
    $fim = $filtros['dt_fim'] ?? null;
    $periodoTxt = ($ini || $fim)
        ? trim(($ini ? 'de ' . Carbon::parse($ini)->format('d/m/Y') : '') . ' ' . ($fim ? 'até ' . Carbon::parse($fim)->format('d/m/Y') : ''))
        : 'todo o período';
@endphp
<div class="header">
    <h1>Transporte Escolar — Ocorrências</h1>
    <p>Relatório gerado em {{ now()->format('d/m/Y \à\s H:i') }} · Período: {{ $periodoTxt }}</p>
</div>
<table>
    <thead>
        <tr>
            <th style="text-align:center;width:70px">Data</th>
            <th style="width:44px">Hora</th>
            <th style="width:110px">Tipo</th>
            <th>Rota</th>
            <th style="width:70px">Veículo</th>
            <th>Descrição</th>
        </tr>
    </thead>
    <tbody>
        @forelse($ocorrencias as $o)
        <tr>
            <td style="text-align:center">{{ $fmt($o['data']) }}</td>
            <td>{{ $o['toc_hr'] ?? '—' }}</td>
            <td><span class="badge">{{ $o['toc_tipo_label'] }}</span></td>
            <td>{{ $o['rota'] ?? '—' }}</td>
            <td>{{ $o['veiculo'] ?? '—' }}</td>
            <td>{{ $o['toc_descricao'] }}</td>
        </tr>
        @empty
        <tr><td colspan="6" style="text-align:center;padding:20px;color:#94a3b8">Nenhuma ocorrência no período/filtros.</td></tr>
        @endforelse
    </tbody>
</table>
<div class="footer">
    <div class="footer-grid">
        <div class="footer-item"><div class="label">Total</div><div class="value">{{ $resumo['total'] }}</div></div>
        @foreach($resumo['por_tipo'] as $t)
        <div class="footer-item"><div class="label">{{ $t['label'] }}</div><div class="value">{{ $t['total'] }}</div></div>
        @endforeach
    </div>
    <div class="meta">SIE Matrícula · Exportação automática</div>
</div>
</body>
</html>
