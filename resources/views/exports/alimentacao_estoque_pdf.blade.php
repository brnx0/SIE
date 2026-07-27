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
    td { padding: 7px 10px; }
    .badge { display: inline-block; padding: 2px 8px; border-radius: 9999px; font-size: 9px; font-weight: 600; }
    .badge-vencido { background: #ffe4e6; color: #9f1239; }
    .badge-vencendo { background: #fef3c7; color: #92400e; }
    .footer { padding: 10px 24px 14px; border-top: 1px solid #e2e8f0; background: #f8fafc; }
    .footer-grid { display: flex; gap: 32px; }
    .footer-item .label { font-size: 9px; text-transform: uppercase; color: #64748b; letter-spacing: 0.05em; }
    .footer-item .value { font-size: 16px; font-weight: 700; color: #1e293b; }
</style>
</head>
<body>
@php
    $num = fn ($v) => rtrim(rtrim(number_format((float) $v, 4, ',', '.'), '0'), ',');
@endphp
<div class="header">
    <h1>Saldo de Estoque — {{ $escopo }}</h1>
    <p>Relatório gerado em {{ now()->format('d/m/Y \à\s H:i') }}@if($search) · Filtro: "{{ $search }}"@endif</p>
</div>
<table>
    <thead>
        <tr>
            <th style="width:70px">Código</th>
            <th>Item</th>
            <th>Categoria</th>
            <th>Lote</th>
            <th>Marca</th>
            <th style="width:110px">Validade</th>
            <th style="text-align:right;width:90px">Saldo</th>
            <th style="text-align:right;width:80px">Custo médio</th>
            <th style="text-align:right;width:90px">Valor</th>
        </tr>
    </thead>
    <tbody>
        @forelse($saldos as $s)
        @php
            $validade = $s->lote?->lot_dt_validade;
            $dias = $s->lote?->diasParaVencer();
        @endphp
        <tr>
            <td>{{ $s->item?->itm_cd_item ?? '—' }}</td>
            <td><strong>{{ $s->item?->itm_nome ?? '—' }}</strong></td>
            <td>{{ $s->item?->categoria?->cat_nome ?? '—' }}</td>
            <td>{{ $s->lote?->lot_cd_lote ?? '—' }}</td>
            <td>{{ $s->lote?->marca?->mrc_nome ?? '—' }}</td>
            <td>
                @if(! $validade)
                    —
                @elseif($s->lote->vencido())
                    <span class="badge badge-vencido">Vencido {{ $validade->format('d/m/Y') }}</span>
                @elseif($dias !== null && $dias <= 30)
                    <span class="badge badge-vencendo">{{ $validade->format('d/m/Y') }} · {{ $dias }}d</span>
                @else
                    {{ $validade->format('d/m/Y') }}
                @endif
            </td>
            <td style="text-align:right">{{ $num($s->sld_qtd_base) }} {{ $s->item?->unidadeMedida?->umd_sigla }}</td>
            <td style="text-align:right">R$ {{ number_format((float) $s->sld_vl_custo_medio, 4, ',', '.') }}</td>
            <td style="text-align:right">R$ {{ number_format($s->valorTotal(), 2, ',', '.') }}</td>
        </tr>
        @empty
        <tr><td colspan="9" style="text-align:center;padding:20px;color:#94a3b8">Nenhum item com saldo.</td></tr>
        @endforelse
    </tbody>
</table>
<div class="footer">
    <div class="footer-grid">
        <div class="footer-item"><div class="label">Linhas (item × lote)</div><div class="value">{{ $total }}</div></div>
        <div class="footer-item"><div class="label">Valor do estoque</div><div class="value">R$ {{ number_format($valorTotal, 2, ',', '.') }}</div></div>
    </div>
</div>
</body>
</html>
