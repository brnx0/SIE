<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1e293b; }
    .header { padding-bottom: 12px; border-bottom: 2px solid #6366f1; margin-bottom: 14px; }
    .header .entidade { font-size: 13px; font-weight: 700; color: #1e293b; }
    .header .endereco { font-size: 9px; color: #64748b; margin-top: 1px; }
    .header h1 { font-size: 17px; font-weight: 700; color: #4f46e5; margin-top: 8px; }
    .meta { margin-bottom: 14px; }
    .meta table { width: 100%; }
    .meta td { padding: 3px 0; font-size: 11px; vertical-align: top; }
    .meta .label { font-size: 9px; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; width: 120px; }
    table.itens { width: 100%; border-collapse: collapse; }
    table.itens thead th { background: #f1f5f9; padding: 7px 9px; text-align: left; font-size: 9px; text-transform: uppercase; letter-spacing: 0.05em; color: #475569; border-bottom: 1px solid #e2e8f0; }
    table.itens tbody tr { border-bottom: 1px solid #f1f5f9; }
    table.itens tbody tr:nth-child(even) { background: #fafafa; }
    table.itens td { padding: 6px 9px; }
    .num { text-align: right; }
    .total-row td { border-top: 2px solid #e2e8f0; font-weight: 700; padding-top: 8px; }
    .obs { margin-top: 14px; font-size: 10px; color: #475569; }
    .obs .label { font-size: 9px; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; }
    .assinaturas { margin-top: 48px; width: 100%; }
    .assinaturas td { width: 50%; padding: 0 18px; text-align: center; font-size: 10px; color: #475569; }
    .assinaturas .linha { border-top: 1px solid #1e293b; padding-top: 4px; }
    .rodape { margin-top: 26px; font-size: 8px; color: #94a3b8; text-align: center; }
</style>
</head>
<body>
@php
    $num = fn ($v) => rtrim(rtrim(number_format((float) $v, 4, ',', '.'), '0'), ',');
@endphp
<div class="header">
    @if($nomeEntidade)<div class="entidade">{{ $nomeEntidade }}</div>@endif
    @if($endereco)<div class="endereco">{{ $endereco }}</div>@endif
    <h1>Guia de Remessa — Transferência de Estoque</h1>
</div>

<div class="meta">
    <table>
        <tr>
            <td class="label">Escola de destino</td>
            <td><strong>{{ $escolaNome }}</strong></td>
            <td class="label">Protocolo</td>
            <td>{{ $protocolo }}</td>
        </tr>
        <tr>
            <td class="label">Data</td>
            <td>{{ $data ?? '—' }}</td>
            <td class="label">Responsável (central)</td>
            <td>{{ $usuario ?? '—' }}</td>
        </tr>
    </table>
</div>

<table class="itens">
    <thead>
        <tr>
            <th style="width:70px">Código</th>
            <th>Item</th>
            <th style="width:90px">Lote</th>
            <th style="width:70px">Validade</th>
            <th style="width:90px">Marca</th>
            <th class="num" style="width:95px">Quantidade</th>
        </tr>
    </thead>
    <tbody>
        @foreach($movimentos as $m)
        <tr>
            <td>{{ $m->item?->itm_cd_item ?? '—' }}</td>
            <td><strong>{{ $m->item?->itm_nome ?? '—' }}</strong></td>
            <td>{{ $m->lote?->lot_cd_lote ?? '—' }}</td>
            <td>{{ $m->lote?->lot_dt_validade?->format('d/m/Y') ?? '—' }}</td>
            <td>{{ $m->lote?->marca?->mrc_nome ?? '—' }}</td>
            <td class="num">{{ $num($m->mov_qtd_base) }} {{ $m->item?->unidadeMedida?->umd_sigla }}</td>
        </tr>
        @endforeach
        <tr class="total-row">
            <td colspan="5">Total de itens: {{ $movimentos->count() }}</td>
            <td class="num">R$ {{ number_format($valorTotal, 2, ',', '.') }}</td>
        </tr>
    </tbody>
</table>

@if($obs)
<div class="obs">
    <span class="label">Observação:</span> {{ $obs }}
</div>
@endif

<table class="assinaturas">
    <tr>
        <td><div class="linha">Responsável pela entrega (central)</div></td>
        <td><div class="linha">Recebido por (escola) — nome, data e assinatura</div></td>
    </tr>
</table>

<div class="rodape">
    Guia gerada em {{ now()->format('d/m/Y \à\s H:i') }} · Documento de conferência do recebimento na unidade escolar.
</div>
</body>
</html>
