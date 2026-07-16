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
    .prep { padding: 10px 24px; border-bottom: 1px solid #e2e8f0; }
    .prep-nome { font-size: 12px; font-weight: 700; }
    .prep-meta { font-size: 10px; color: #64748b; margin-top: 2px; }
    .badge { display: inline-block; padding: 2px 8px; border-radius: 9999px; font-size: 9px; font-weight: 600; }
    .badge-confirmada { background: #d1fae5; color: #065f46; }
    .badge-rascunho { background: #e0f2fe; color: #075985; }
    .badge-cancelada { background: #f1f5f9; color: #475569; }
    table.itens { width: 100%; border-collapse: collapse; margin-top: 6px; }
    table.itens thead th { background: #f1f5f9; padding: 5px 8px; text-align: left; font-size: 9px; text-transform: uppercase; color: #475569; }
    table.itens td { padding: 5px 8px; border-bottom: 1px solid #f8fafc; }
    .acima { color: #e11d48; font-weight: 700; }
    .abaixo { color: #0284c7; font-weight: 700; }
    .vazio { font-size: 10px; color: #94a3b8; font-style: italic; margin-top: 4px; }
    .footer { padding: 10px 24px 14px; border-top: 1px solid #e2e8f0; background: #f8fafc; }
    .footer-grid { display: flex; gap: 32px; }
    .footer-item .label { font-size: 9px; text-transform: uppercase; color: #64748b; letter-spacing: 0.05em; }
    .footer-item .value { font-size: 16px; font-weight: 700; color: #1e293b; }
</style>
</head>
<body>
@php
    $num = fn ($v) => rtrim(rtrim(number_format((float) $v, 4, ',', '.'), '0'), ',');
    $classe = fn ($s) => match ($s) {
        'CONFIRMADA' => 'badge-confirmada',
        'RASCUNHO'   => 'badge-rascunho',
        default      => 'badge-cancelada',
    };
@endphp
<div class="header">
    <h1>Controle Diário das Preparações — {{ $escopo }}</h1>
    <p>Período: {{ $periodo }} · Relatório gerado em {{ now()->format('d/m/Y \à\s H:i') }}</p>
</div>

@forelse($preparacoes as $p)
<div class="prep">
    <div>
        <span class="prep-nome">{{ $p->prd_dt->format('d/m/Y') }} — {{ $p->prato?->prt_nome ?? '—' }}</span>
        <span class="badge {{ $classe($p->prd_situacao) }}" style="margin-left:6px">{{ $p->prd_situacao }}</span>
    </div>
    <div class="prep-meta">
        {{ $p->prd_turno }} · {{ $p->prd_refeicao }} · {{ $p->prd_qtd_servida }} porções
        @if($p->escola) · {{ $p->escola->esc_nome }} @endif
        @if($p->funcionario) · Preparado por {{ $p->funcionario->fun_nome }} @endif
        @if($p->prd_obs) · {{ $p->prd_obs }} @endif
    </div>

    @if($p->itens->count())
    <table class="itens">
        <thead>
            <tr>
                <th>Item</th>
                <th style="text-align:right;width:100px">Previsto</th>
                <th style="text-align:right;width:100px">Utilizado</th>
                <th style="text-align:right;width:100px">Desvio</th>
                <th style="width:50px">Unid.</th>
            </tr>
        </thead>
        <tbody>
            @foreach($p->itens as $i)
            @php $desvio = $i->desvio(); @endphp
            <tr>
                <td>{{ $i->item?->itm_nome ?? '—' }}</td>
                <td style="text-align:right">{{ $num($i->pdi_qtd_prevista) }}</td>
                <td style="text-align:right">{{ $num($i->pdi_qtd_utilizada) }}</td>
                <td style="text-align:right">
                    @if(abs($desvio) > 0.0001)
                        <span class="{{ $desvio > 0 ? 'acima' : 'abaixo' }}">{{ $desvio > 0 ? '+' : '' }}{{ $num($desvio) }}</span>
                    @else
                        —
                    @endif
                </td>
                <td>{{ $i->unidadeMedida?->umd_sigla ?? '' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @else
    <div class="vazio">Preparação sem itens.</div>
    @endif
</div>
@empty
<div style="text-align:center;padding:20px;color:#94a3b8">Nenhuma preparação no período.</div>
@endforelse

<div class="footer">
    <div class="footer-grid">
        <div class="footer-item"><div class="label">Preparações</div><div class="value">{{ $total }}</div></div>
        {{-- Porções são somáveis (é contagem de pessoas servidas); quantidade de
             item não é, porque mistura unidades diferentes. --}}
        <div class="footer-item"><div class="label">Porções servidas (confirmadas)</div><div class="value">{{ $porcoes }}</div></div>
    </div>
</div>
</body>
</html>
