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
    td { padding: 6px 10px; }
    .badge { display: inline-block; padding: 2px 8px; border-radius: 9999px; font-size: 9px; font-weight: 600; }
    .badge-entrada { background: #d1fae5; color: #065f46; }
    .badge-transferencia { background: #e0f2fe; color: #075985; }
    .badge-retirada { background: #fef3c7; color: #92400e; }
    .badge-estorno { background: #ffe4e6; color: #9f1239; }
    .badge-outro { background: #f1f5f9; color: #475569; }
    .entra { color: #059669; font-weight: 700; }
    .sai { color: #e11d48; font-weight: 700; }
    .footer { padding: 10px 24px 14px; border-top: 1px solid #e2e8f0; background: #f8fafc; }
    .footer-grid { display: flex; gap: 32px; }
    .footer-item .label { font-size: 9px; text-transform: uppercase; color: #64748b; letter-spacing: 0.05em; }
    .footer-item .value { font-size: 16px; font-weight: 700; color: #1e293b; }
    .footer-item .value-entra { color: #059669; }
    .footer-item .value-sai { color: #e11d48; }
</style>
</head>
<body>
@php
    $num = fn ($v) => rtrim(rtrim(number_format((float) $v, 4, ',', '.'), '0'), ',');
    $classe = fn ($t) => match ($t) {
        'ENTRADA'       => 'badge-entrada',
        'TRANSFERENCIA' => 'badge-transferencia',
        'RETIRADA'      => 'badge-retirada',
        'ESTORNO'       => 'badge-estorno',
        default         => 'badge-outro',
    };

    // O tipo é gravado sem acento; o relatório imprime o rótulo de gente.
    $rotulo = fn ($t) => match ($t) {
        'ENTRADA'       => 'Entrada',
        'TRANSFERENCIA' => 'Transferência',
        'RETIRADA'      => 'Retirada',
        'CONSUMO'       => 'Consumo',
        'AJUSTE'        => 'Ajuste',
        'ESTORNO'       => 'Estorno',
        default         => $t,
    };
@endphp
<div class="header">
    <h1>Movimentação de Estoque — {{ $escopo }}</h1>
    <p>Período: {{ $periodo }} · Relatório gerado em {{ now()->format('d/m/Y \à\s H:i') }}</p>
</div>
<table>
    <thead>
        <tr>
            <th style="width:65px">Data</th>
            <th style="width:70px">Tipo</th>
            <th>Item</th>
            <th style="width:90px">Lote</th>
            <th style="width:70px">Validade</th>
            <th style="text-align:right;width:95px">Quantidade</th>
            <th style="text-align:right;width:80px">Valor</th>
            <th>Origem / Responsável</th>
        </tr>
    </thead>
    <tbody>
        @forelse($movimentos as $m)
        <tr>
            <td>{{ $m->mov_dt->format('d/m/Y') }}</td>
            <td><span class="badge {{ $classe($m->mov_tipo) }}">{{ $rotulo($m->mov_tipo) }}</span></td>
            <td>
                <strong>{{ $m->item?->itm_nome ?? '—' }}</strong>
                @if($m->escola)<br><span style="font-size:9px;color:#64748b">{{ $m->escola->esc_nome }}</span>@endif
            </td>
            <td>{{ $m->lote?->lot_cd_lote ?? '—' }}</td>
            <td>{{ $m->lote?->lot_dt_validade?->format('d/m/Y') ?? '—' }}</td>
            <td style="text-align:right">
                <span class="{{ $m->sinal() > 0 ? 'entra' : 'sai' }}">
                    {{ $m->sinal() > 0 ? '+' : '−' }}{{ $num($m->mov_qtd_base) }} {{ $m->item?->unidadeMedida?->umd_sigla }}
                </span>
                @if($m->unidadeMedida && $m->item && $m->mov_umd_id !== $m->item->itm_umd_id)
                    <br><span style="font-size:9px;color:#64748b">lançado: {{ $num($m->mov_qtd) }} {{ $m->unidadeMedida->umd_sigla }}</span>
                @endif
            </td>
            <td style="text-align:right">{{ $m->mov_vl_total !== null ? 'R$ '.number_format((float) $m->mov_vl_total, 2, ',', '.') : '—' }}</td>
            <td style="font-size:9px">
                {{ $m->fornecedor?->frn_razao_social ?? '' }}
                {{ $m->tipoRetirada?->tpr_nome ?? '' }}
                @if($m->usuario)<br>{{ $m->usuario->name }}@endif
                @if($m->mov_obs)<br><em>{{ $m->mov_obs }}</em>@endif
            </td>
        </tr>
        @empty
        <tr><td colspan="8" style="text-align:center;padding:20px;color:#94a3b8">Nenhum movimento no período.</td></tr>
        @endforelse
    </tbody>
</table>
<div class="footer">
    <div class="footer-grid">
        {{-- Contagem, não soma de quantidade: itens têm unidades diferentes e
             somar 60 KG com 3 UN daria um número sem significado. --}}
        <div class="footer-item"><div class="label">Movimentos</div><div class="value">{{ $total }}</div></div>
        <div class="footer-item"><div class="label">Entradas</div><div class="value value-entra">{{ $qtdEntradas }}</div></div>
        <div class="footer-item"><div class="label">Saídas / retiradas</div><div class="value value-sai">{{ $qtdSaidas }}</div></div>
        <div class="footer-item"><div class="label">Valor das entradas</div><div class="value">R$ {{ number_format($valorEntradas, 2, ',', '.') }}</div></div>
    </div>
</div>
</body>
</html>
