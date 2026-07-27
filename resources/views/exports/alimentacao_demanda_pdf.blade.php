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
    .meta { padding: 10px 24px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; font-size: 10px; color: #475569; }
    table { width: 100%; border-collapse: collapse; }
    thead th { background: #f1f5f9; padding: 7px 10px; text-align: left; font-size: 9px; text-transform: uppercase; letter-spacing: 0.05em; color: #475569; border-bottom: 1px solid #e2e8f0; }
    tbody tr { border-bottom: 1px solid #f1f5f9; }
    tbody tr:nth-child(even) { background: #fafafa; }
    td { padding: 7px 10px; }
    .badge { display: inline-block; padding: 2px 8px; border-radius: 9999px; font-size: 9px; font-weight: 600; }
    .badge-fechada { background: #d1fae5; color: #065f46; }
    .badge-rascunho { background: #e0f2fe; color: #075985; }
    .ajustado { color: #b45309; font-weight: 700; }
    .just { font-size: 9px; color: #92400e; font-style: italic; }
    .footer { padding: 10px 24px 14px; border-top: 1px solid #e2e8f0; background: #f8fafc; }
    .footer-grid { display: flex; gap: 32px; }
    .footer-item .label { font-size: 9px; text-transform: uppercase; color: #64748b; letter-spacing: 0.05em; }
    .footer-item .value { font-size: 16px; font-weight: 700; color: #1e293b; }
</style>
</head>
<body>
@php
    $num = fn ($v) => rtrim(rtrim(number_format((float) $v, 4, ',', '.'), '0'), ',');
    $ajustados = $itens->filter(fn ($i) => $i->ajustada())->count();
@endphp
<div class="header">
    <h1>Demanda de Merenda — {{ $demanda->nomeMes() }}/{{ $demanda->dmd_ano }}</h1>
    <p>
        {{ $demanda->escola?->esc_nome }}
        · <span class="badge {{ $demanda->dmd_situacao === 'FECHADA' ? 'badge-fechada' : 'badge-rascunho' }}">{{ $demanda->dmd_situacao }}</span>
        · Gerado em {{ now()->format('d/m/Y \à\s H:i') }}
    </p>
</div>
<div class="meta">
    Cardápio: <strong>{{ $demanda->cardapio?->crd_nome ?? '—' }}</strong>
    · Dias letivos no mês: <strong>{{ $demanda->dmd_dias_letivos }}</strong>
    · Ano letivo: <strong>{{ $demanda->anoLetivo?->anl_ano ?? '—' }}</strong>
    @if($demanda->dmd_obs) · {{ $demanda->dmd_obs }} @endif
</div>
<table>
    <thead>
        <tr>
            <th style="width:70px">Código</th>
            <th>Item</th>
            <th style="text-align:right;width:100px">Calculado</th>
            <th style="text-align:right;width:100px">Ajustado</th>
            <th style="text-align:right;width:110px">A comprar</th>
            <th style="text-align:center;width:50px">Unid.</th>
        </tr>
    </thead>
    <tbody>
        @forelse($itens as $i)
        <tr>
            <td>{{ $i->item?->itm_cd_item ?? '—' }}</td>
            <td>
                <strong>{{ $i->item?->itm_nome ?? '—' }}</strong>
                @if($i->dmi_justificativa)<br><span class="just">{{ $i->dmi_justificativa }}</span>@endif
            </td>
            <td style="text-align:right">{{ $num($i->dmi_qtd_calculada) }}</td>
            <td style="text-align:right" class="{{ $i->ajustada() ? 'ajustado' : '' }}">
                {{ $i->ajustada() ? $num($i->dmi_qtd_ajustada) : '—' }}
            </td>
            <td style="text-align:right"><strong>{{ $num($i->qtdFinal()) }}</strong></td>
            <td style="text-align:center">{{ $i->unidadeMedida?->umd_sigla ?? '' }}</td>
        </tr>
        @empty
        <tr><td colspan="6" style="text-align:center;padding:20px;color:#94a3b8">Demanda sem itens.</td></tr>
        @endforelse
    </tbody>
</table>
<div class="footer">
    <div class="footer-grid">
        {{-- Contagem, não soma: os itens têm unidades diferentes (KG, L, UN). --}}
        <div class="footer-item"><div class="label">Itens</div><div class="value">{{ $itens->count() }}</div></div>
        <div class="footer-item"><div class="label">Ajustados manualmente</div><div class="value">{{ $ajustados }}</div></div>
        <div class="footer-item"><div class="label">Dias letivos</div><div class="value">{{ $demanda->dmd_dias_letivos }}</div></div>
    </div>
</div>
</body>
</html>
