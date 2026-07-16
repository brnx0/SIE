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
    .visita { padding: 12px 24px; border-bottom: 1px solid #e2e8f0; }
    .visita-nome { font-size: 12px; font-weight: 700; }
    .visita-meta { font-size: 10px; color: #64748b; margin-top: 2px; }
    .badge { display: inline-block; padding: 2px 8px; border-radius: 9999px; font-size: 9px; font-weight: 600; }
    .badge-atestada { background: #d1fae5; color: #065f46; }
    .badge-realizada { background: #fef3c7; color: #92400e; }
    .badge-programada { background: #e0f2fe; color: #075985; }
    .badge-cancelada { background: #f1f5f9; color: #475569; }
    table.q { width: 100%; border-collapse: collapse; margin-top: 6px; }
    table.q thead th { background: #f1f5f9; padding: 5px 8px; text-align: left; font-size: 9px; text-transform: uppercase; color: #475569; }
    table.q td { padding: 5px 8px; border-bottom: 1px solid #f8fafc; vertical-align: top; }
    .parecer { margin-top: 6px; padding: 6px 8px; background: #f8fafc; border-left: 3px solid #6366f1; font-size: 10px; }
    .vazio { font-size: 10px; color: #94a3b8; font-style: italic; margin-top: 4px; }
    .footer { padding: 10px 24px 14px; border-top: 1px solid #e2e8f0; background: #f8fafc; }
    .footer-grid { display: flex; gap: 32px; }
    .footer-item .label { font-size: 9px; text-transform: uppercase; color: #64748b; letter-spacing: 0.05em; }
    .footer-item .value { font-size: 16px; font-weight: 700; color: #1e293b; }
</style>
</head>
<body>
@php
    $classe = fn ($s) => match ($s) {
        'ATESTADA'   => 'badge-atestada',
        'REALIZADA'  => 'badge-realizada',
        'PROGRAMADA' => 'badge-programada',
        default      => 'badge-cancelada',
    };
@endphp
<div class="header">
    <h1>Visitas Técnicas</h1>
    <p>Período: {{ $periodo }} · Relatório gerado em {{ now()->format('d/m/Y \à\s H:i') }}</p>
</div>

@forelse($visitas as $v)
<div class="visita">
    <div>
        <span class="visita-nome">{{ $v->escola?->esc_nome ?? '—' }}</span>
        <span class="badge {{ $classe($v->vis_situacao) }}" style="margin-left:6px">{{ $v->vis_situacao }}</span>
    </div>
    <div class="visita-meta">
        {{ $v->tipoVisita?->tpv_nome ?? '—' }}
        · Programada {{ $v->vis_dt_programada->format('d/m/Y') }}
        @if($v->vis_dt_realizada) · Realizada {{ $v->vis_dt_realizada->format('d/m/Y') }} @endif
        @if($v->tecnico) · Técnico: {{ $v->tecnico->fun_nome }} @endif
        @if($v->atestador)
            · Atestada por {{ $v->atestador->fun_nome }} em {{ $v->vis_dt_ateste?->format('d/m/Y') }}
        @endif
        @if($v->vis_situacao === 'CANCELADA' && $v->vis_motivo_cancelamento)
            · Motivo: {{ $v->vis_motivo_cancelamento }}
        @endif
    </div>

    @if($v->respostas->count())
    <table class="q">
        <thead>
            <tr>
                <th>Pergunta</th>
                <th style="width:130px">Resposta</th>
                <th style="width:180px">Observação</th>
            </tr>
        </thead>
        <tbody>
            {{-- A pergunta impressa é a congelada na resposta: é o que a visita
                 realmente perguntou naquele dia. --}}
            @foreach($v->respostas as $r)
            <tr>
                <td>{{ $r->vre_pergunta_texto }}</td>
                <td><strong>{{ $r->textoResposta() }}</strong></td>
                <td>{{ $r->vre_obs ?? '—' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @else
    <div class="vazio">Visita sem questionário.</div>
    @endif

    @if($v->vis_parecer)
    <div class="parecer"><strong>Parecer:</strong> {{ $v->vis_parecer }}</div>
    @endif
</div>
@empty
<div style="text-align:center;padding:20px;color:#94a3b8">Nenhuma visita no período.</div>
@endforelse

<div class="footer">
    <div class="footer-grid">
        <div class="footer-item"><div class="label">Visitas</div><div class="value">{{ $total }}</div></div>
        <div class="footer-item"><div class="label">Realizadas</div><div class="value">{{ $realizadas }}</div></div>
        <div class="footer-item"><div class="label">Atestadas</div><div class="value">{{ $atestadas }}</div></div>
    </div>
</div>
</body>
</html>
