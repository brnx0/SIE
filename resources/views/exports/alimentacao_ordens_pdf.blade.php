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
    .ordem { padding: 12px 24px; border-bottom: 1px solid #e2e8f0; }
    .ordem-nr { font-size: 13px; font-weight: 700; }
    .ordem-meta { font-size: 10px; color: #64748b; margin-top: 2px; }
    .badge { display: inline-block; padding: 2px 8px; border-radius: 9999px; font-size: 9px; font-weight: 600; }
    .badge-emitida { background: #e0f2fe; color: #075985; }
    .badge-entregue { background: #d1fae5; color: #065f46; }
    .badge-outro { background: #f1f5f9; color: #475569; }
    table.itens { width: 100%; border-collapse: collapse; margin-top: 8px; }
    table.itens thead th { background: #f1f5f9; padding: 5px 8px; text-align: left; font-size: 9px; text-transform: uppercase; color: #475569; }
    table.itens td { padding: 5px 8px; border-bottom: 1px solid #f8fafc; }
    .vazio { font-size: 10px; color: #94a3b8; font-style: italic; margin-top: 6px; }
    .total { text-align: right; font-size: 11px; font-weight: 700; margin-top: 6px; }
    .footer { padding: 10px 24px 14px; border-top: 1px solid #e2e8f0; background: #f8fafc; }
    .footer-grid { display: flex; gap: 32px; }
    .footer-item .label { font-size: 9px; text-transform: uppercase; color: #64748b; letter-spacing: 0.05em; }
    .footer-item .value { font-size: 16px; font-weight: 700; color: #1e293b; }
</style>
</head>
<body>
<div class="header">
    <h1>Ordens de Fornecimento</h1>
    <p>Relatório gerado em {{ now()->format('d/m/Y \à\s H:i') }}@if($search) · Filtro: "{{ $search }}"@endif</p>
</div>

@forelse($ordens as $o)
<div class="ordem">
    <div>
        <span class="ordem-nr">{{ $o->ofn_nr_ordem }}</span>
        <span class="badge {{ $o->ofn_situacao === 'EMITIDA' ? 'badge-emitida' : ($o->ofn_situacao === 'ENTREGUE' ? 'badge-entregue' : 'badge-outro') }}" style="margin-left:6px">
            {{ $o->ofn_situacao }}
        </span>
    </div>
    <div class="ordem-meta">
        Contrato {{ $o->contrato?->ctr_nr_contrato }}/{{ $o->contrato?->ctr_ano }}
        · {{ $o->contrato?->fornecedor?->frn_razao_social ?? '—' }}
        @if($o->empenho) · Empenho {{ $o->empenho->emp_nr_empenho }} @endif
        · Emissão {{ $o->ofn_dt_emissao->format('d/m/Y') }}
        @if($o->ofn_dt_entrega_prevista) · Entrega prevista {{ $o->ofn_dt_entrega_prevista->format('d/m/Y') }} @endif
        · Entregar em: {{ \App\Models\Alimentacao\OrdemFornecimento::LOCAL_ENTREGA }}
    </div>

    @if($o->itens->count())
    <table class="itens">
        <thead>
            <tr>
                <th>Item</th>
                <th style="text-align:right;width:90px">Quantidade</th>
                <th style="text-align:right;width:90px">Vl. unitário</th>
                <th style="text-align:right;width:90px">Vl. total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($o->itens as $i)
            <tr>
                <td>{{ $i->item?->itm_nome ?? '—' }}</td>
                <td style="text-align:right">{{ rtrim(rtrim(number_format((float) $i->ofi_qtd, 4, ',', '.'), '0'), ',') }} {{ $i->unidadeMedida?->umd_sigla }}</td>
                <td style="text-align:right">R$ {{ number_format((float) $i->ofi_vl_unitario, 4, ',', '.') }}</td>
                <td style="text-align:right">R$ {{ number_format($i->valorTotal(), 2, ',', '.') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    <div class="total">Total da ordem: R$ {{ number_format($o->valorTotal(), 2, ',', '.') }}</div>
    @else
    <div class="vazio">Nenhum item pedido nesta ordem.</div>
    @endif
</div>
@empty
<div style="text-align:center;padding:20px;color:#94a3b8">Nenhum registro encontrado.</div>
@endforelse

<div class="footer">
    <div class="footer-grid">
        <div class="footer-item"><div class="label">Ordens</div><div class="value">{{ $total }}</div></div>
        <div class="footer-item"><div class="label">Valor total</div><div class="value">R$ {{ number_format($valorTotal, 2, ',', '.') }}</div></div>
    </div>
</div>
</body>
</html>
