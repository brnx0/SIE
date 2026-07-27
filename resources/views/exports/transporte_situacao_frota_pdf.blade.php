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
    .st-vencido { background: #ffe4e6; color: #9f1239; }
    .st-a_vencer { background: #fef3c7; color: #92400e; }
    .st-em_dia { background: #d1fae5; color: #065f46; }
    .st-sem_data { background: #f1f5f9; color: #64748b; }
    .footer { padding: 10px 24px 14px; border-top: 1px solid #e2e8f0; background: #f8fafc; }
    .footer-grid { display: flex; gap: 28px; }
    .footer-item .label { font-size: 9px; text-transform: uppercase; color: #64748b; letter-spacing: 0.05em; }
    .footer-item .value { font-size: 16px; font-weight: 700; color: #1e293b; }
    .meta { font-size: 9px; color: #94a3b8; margin-top: 6px; }
</style>
</head>
<body>
@php
    use Illuminate\Support\Carbon;
    $fmt = fn ($d) => $d ? Carbon::parse($d)->format('d/m/Y') : '—';
    $rot = ['vencido' => 'Vencido', 'a_vencer' => 'A vencer', 'em_dia' => 'Em dia', 'sem_data' => 'Sem data'];
    $cell = function ($doc) use ($fmt, $rot) {
        return '<span class="badge st-' . $doc['status'] . '" title="' . ($rot[$doc['status']] ?? '') . '">' . $fmt($doc['data']) . '</span>';
    };
@endphp
<div class="header">
    <h1>Transporte Escolar — Situação da Frota</h1>
    <p>
        Relatório gerado em {{ now()->format('d/m/Y \à\s H:i') }}
        · Janela de vencimento: {{ $filtros['dias'] }} dias
        · {{ $filtros['incluir_inativos'] ? 'Inclui inativos' : 'Somente ativos' }}
    </p>
</div>
<table>
    <thead>
        <tr>
            <th>Placa</th>
            <th>Tipo</th>
            <th style="text-align:center">Licenciamento</th>
            <th style="text-align:center">Seguro</th>
            <th style="text-align:center">Vistoria</th>
            <th style="text-align:center">Situação</th>
        </tr>
    </thead>
    <tbody>
        @forelse($veiculos as $v)
        <tr>
            <td><strong>{{ $v['vei_placa'] }}</strong>@if(! $v['vei_fl_ativo']) <span class="badge st-sem_data">inativo</span>@endif</td>
            <td>{{ $v['vei_tipo'] }}</td>
            <td style="text-align:center">{!! $cell($v['docs']['licenciamento']) !!}</td>
            <td style="text-align:center">{!! $cell($v['docs']['seguro']) !!}</td>
            <td style="text-align:center">{!! $cell($v['docs']['vistoria']) !!}</td>
            <td style="text-align:center"><span class="badge st-{{ $v['situacao'] }}">{{ $rot[$v['situacao']] ?? $v['situacao'] }}</span></td>
        </tr>
        @empty
        <tr><td colspan="6" style="text-align:center;padding:20px;color:#94a3b8">Nenhum veículo encontrado.</td></tr>
        @endforelse
    </tbody>
</table>
<div class="footer">
    <div class="footer-grid">
        <div class="footer-item"><div class="label">Total</div><div class="value">{{ $resumo['total'] }}</div></div>
        <div class="footer-item"><div class="label">Vencidos</div><div class="value" style="color:#9f1239">{{ $resumo['vencido'] }}</div></div>
        <div class="footer-item"><div class="label">A vencer</div><div class="value" style="color:#b45309">{{ $resumo['a_vencer'] }}</div></div>
        <div class="footer-item"><div class="label">Em dia</div><div class="value" style="color:#059669">{{ $resumo['em_dia'] }}</div></div>
        <div class="footer-item"><div class="label">Sem data</div><div class="value" style="color:#94a3b8">{{ $resumo['sem_data'] }}</div></div>
    </div>
    <div class="meta">SIE Matrícula · Exportação automática</div>
</div>
</body>
</html>
