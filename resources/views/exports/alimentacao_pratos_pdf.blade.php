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
    .prato { padding: 12px 24px; border-bottom: 1px solid #e2e8f0; }
    .prato-nome { font-size: 13px; font-weight: 700; color: #1e293b; }
    .prato-desc { font-size: 10px; color: #64748b; margin-top: 2px; }
    .badge { display: inline-block; padding: 2px 8px; border-radius: 9999px; font-size: 9px; font-weight: 600; }
    .badge-active { background: #d1fae5; color: #065f46; }
    .badge-inactive { background: #f1f5f9; color: #475569; }
    table.ficha { width: 100%; border-collapse: collapse; margin-top: 8px; }
    table.ficha thead th { background: #f1f5f9; padding: 5px 8px; text-align: left; font-size: 9px; text-transform: uppercase; color: #475569; }
    table.ficha td { padding: 5px 8px; border-bottom: 1px solid #f8fafc; }
    .vazio { font-size: 10px; color: #94a3b8; font-style: italic; margin-top: 6px; }
    .footer { padding: 10px 24px 14px; border-top: 1px solid #e2e8f0; background: #f8fafc; }
    .footer-grid { display: flex; gap: 32px; }
    .footer-item .label { font-size: 9px; text-transform: uppercase; color: #64748b; letter-spacing: 0.05em; }
    .footer-item .value { font-size: 16px; font-weight: 700; color: #1e293b; }
    .footer-item .value-active { color: #059669; }
    .footer-item .value-inactive { color: #94a3b8; }
</style>
</head>
<body>
<div class="header">
    <h1>Pratos e Fichas Técnicas</h1>
    <p>Relatório gerado em {{ now()->format('d/m/Y \à\s H:i') }}@if($search) · Filtro: "{{ $search }}"@endif</p>
</div>

@forelse($pratos as $p)
<div class="prato">
    <div>
        <span class="prato-nome">{{ $p->prt_nome }}</span>
        <span class="badge {{ $p->prt_fl_ativo ? 'badge-active' : 'badge-inactive' }}" style="margin-left:6px">
            {{ $p->prt_fl_ativo ? 'Ativo' : 'Inativo' }}
        </span>
    </div>
    @if($p->prt_descricao)<div class="prato-desc">{{ $p->prt_descricao }}</div>@endif

    @if($p->itens->count())
    <table class="ficha">
        <thead>
            <tr>
                <th>Item</th>
                <th style="text-align:right;width:140px">Quantidade por aluno</th>
            </tr>
        </thead>
        <tbody>
            @foreach($p->itens as $i)
            <tr>
                <td>{{ $i->item?->itm_nome ?? '—' }}</td>
                <td style="text-align:right">
                    {{ rtrim(rtrim(number_format((float) $i->pri_qtd_per_capita, 4, ',', '.'), '0'), ',') }}
                    {{ $i->unidadeMedida?->umd_sigla }}
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @else
    <div class="vazio">Ficha técnica não informada — este prato ainda não calcula demanda nem baixa estoque.</div>
    @endif

    @if($p->temTabelaNutricional())
    @php
        $num = fn ($v) => $v === null ? '—' : rtrim(rtrim(number_format((float) $v, 2, ',', '.'), '0'), ',');
    @endphp
    <table class="ficha" style="margin-top:10px">
        <thead>
            <tr>
                <th colspan="2">
                    Tabela nutricional
                    @if($p->prt_nut_porcao)
                        — porção de {{ $num($p->prt_nut_porcao) }} {{ $p->porcaoUnidadeMedida?->umd_sigla }}
                    @endif
                </th>
            </tr>
        </thead>
        <tbody>
            <tr><td>Valor energético</td><td style="text-align:right">{{ $num($p->prt_nut_energia_kcal) }} kcal</td></tr>
            <tr><td>Carboidratos</td><td style="text-align:right">{{ $num($p->prt_nut_carboidrato_g) }} g</td></tr>
            <tr><td style="padding-left:20px">dos quais açúcares</td><td style="text-align:right">{{ $num($p->prt_nut_acucares_g) }} g</td></tr>
            <tr><td>Proteínas</td><td style="text-align:right">{{ $num($p->prt_nut_proteina_g) }} g</td></tr>
            <tr><td>Gorduras totais</td><td style="text-align:right">{{ $num($p->prt_nut_gordura_total_g) }} g</td></tr>
            <tr><td style="padding-left:20px">das quais saturadas</td><td style="text-align:right">{{ $num($p->prt_nut_gordura_saturada_g) }} g</td></tr>
            <tr><td style="padding-left:20px">das quais trans</td><td style="text-align:right">{{ $num($p->prt_nut_gordura_trans_g) }} g</td></tr>
            <tr><td>Fibra alimentar</td><td style="text-align:right">{{ $num($p->prt_nut_fibra_g) }} g</td></tr>
            <tr><td>Sódio</td><td style="text-align:right">{{ $num($p->prt_nut_sodio_mg) }} mg</td></tr>
        </tbody>
    </table>
    @if($p->prt_nut_observacao)<div class="vazio">{{ $p->prt_nut_observacao }}</div>@endif
    @endif
</div>
@empty
<div style="text-align:center;padding:20px;color:#94a3b8">Nenhum registro encontrado.</div>
@endforelse

<div class="footer">
    <div class="footer-grid">
        <div class="footer-item">
            <div class="label">Total</div>
            <div class="value">{{ $total }}</div>
        </div>
        <div class="footer-item">
            <div class="label">Ativos</div>
            <div class="value value-active">{{ $totalAtivos }}</div>
        </div>
        <div class="footer-item">
            <div class="label">Inativos</div>
            <div class="value value-inactive">{{ $totalInativos }}</div>
        </div>
    </div>
</div>
</body>
</html>
