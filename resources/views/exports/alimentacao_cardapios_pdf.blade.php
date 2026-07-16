<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
@php
    use App\Models\Alimentacao\CardapioDia;
@endphp
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1e293b; }
    .header { padding: 18px 24px 12px; border-bottom: 2px solid #6366f1; }
    .header h1 { font-size: 18px; font-weight: 700; color: #4f46e5; }
    .header p { font-size: 10px; color: #64748b; margin-top: 2px; }
    .cardapio { padding: 12px 24px; border-bottom: 1px solid #e2e8f0; }
    .cardapio-nome { font-size: 13px; font-weight: 700; }
    .cardapio-meta { font-size: 10px; color: #64748b; margin-top: 2px; }
    .badge { display: inline-block; padding: 2px 8px; border-radius: 9999px; font-size: 9px; font-weight: 600; }
    .badge-active { background: #d1fae5; color: #065f46; }
    .badge-inactive { background: #f1f5f9; color: #475569; }
    .badge-rede { background: #e0e7ff; color: #3730a3; }
    table.grade { width: 100%; border-collapse: collapse; margin-top: 8px; }
    table.grade thead th { background: #f1f5f9; padding: 5px 8px; text-align: left; font-size: 9px; text-transform: uppercase; color: #475569; }
    table.grade td { padding: 5px 8px; border-bottom: 1px solid #f8fafc; }
    .vazio { font-size: 10px; color: #94a3b8; font-style: italic; margin-top: 6px; }
    .footer { padding: 10px 24px 14px; border-top: 1px solid #e2e8f0; background: #f8fafc; }
    .footer-item .label { font-size: 9px; text-transform: uppercase; color: #64748b; letter-spacing: 0.05em; }
    .footer-item .value { font-size: 16px; font-weight: 700; }
</style>
</head>
<body>
<div class="header">
    <h1>Cardápios</h1>
    <p>Relatório gerado em {{ now()->format('d/m/Y \à\s H:i') }}@if($search) · Filtro: "{{ $search }}"@endif</p>
</div>

@forelse($cardapios as $c)
<div class="cardapio">
    <div>
        <span class="cardapio-nome">{{ $c->crd_nome }}</span>
        <span class="badge {{ $c->crd_fl_ativo ? 'badge-active' : 'badge-inactive' }}" style="margin-left:6px">
            {{ $c->crd_fl_ativo ? 'Ativo' : 'Inativo' }}
        </span>
        @if(! $c->crd_esc_id)<span class="badge badge-rede" style="margin-left:4px">Padrão da rede</span>@endif
    </div>
    <div class="cardapio-meta">
        {{ $c->escola?->esc_nome ?? 'Vale para todas as escolas sem cardápio próprio' }}
        · Ano {{ $c->anoLetivo?->anl_ano }}
        · Vigência {{ $c->crd_dt_inicio->format('d/m/Y') }} a {{ $c->crd_dt_fim->format('d/m/Y') }}
    </div>

    @if($c->dias->count())
    <table class="grade">
        <thead>
            <tr>
                <th style="width:50px">Semana</th>
                <th style="width:110px">Dia</th>
                <th style="width:90px">Turno</th>
                <th style="width:120px">Refeição</th>
                <th>Prato</th>
            </tr>
        </thead>
        <tbody>
            @foreach($c->dias->sortBy(['cdi_semana', 'cdi_dia_semana', 'cdi_turno', 'cdi_refeicao']) as $d)
            <tr>
                <td>{{ $d->cdi_semana }}</td>
                <td>{{ CardapioDia::nomeDia($d->cdi_dia_semana) }}</td>
                <td>{{ $d->cdi_turno }}</td>
                <td>{{ $d->cdi_refeicao }}</td>
                <td>{{ $d->prato?->prt_nome ?? '—' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @else
    <div class="vazio">Grade não montada — nenhum prato definido para este cardápio.</div>
    @endif
</div>
@empty
<div style="text-align:center;padding:20px;color:#94a3b8">Nenhum registro encontrado.</div>
@endforelse

<div class="footer">
    <div class="footer-item">
        <div class="label">Cardápios</div>
        <div class="value">{{ $total }}</div>
    </div>
</div>
</body>
</html>
