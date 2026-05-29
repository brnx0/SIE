<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1e293b; }
    .header { padding: 0 0 12px; border-bottom: 2px solid #6366f1; margin-bottom: 16px; }
    .header h1 { font-size: 18px; font-weight: 700; color: #4f46e5; }
    .header .sub { font-size: 11px; color: #334155; margin-top: 3px; }
    .header .meta { font-size: 9px; color: #94a3b8; margin-top: 4px; }
    table { width: 100%; border-collapse: collapse; }
    thead th { background: #4f46e5; color: #fff; padding: 8px 10px; text-align: left; font-size: 10px; text-transform: uppercase; letter-spacing: 0.04em; border: 1px solid #4338ca; }
    th.tempo, th.hora { width: 52px; text-align: center; }
    tbody td { padding: 7px 10px; border: 1px solid #e2e8f0; vertical-align: top; }
    tbody tr:nth-child(even) { background: #fafafa; }
    td.tempo { text-align: center; font-weight: 700; color: #475569; }
    td.hora { text-align: center; font-variant-numeric: tabular-nums; color: #475569; }
    .prof { font-weight: 600; font-size: 10px; }
    .disc { font-size: 9px; color: #64748b; }
    .tc { display: inline-block; margin-top: 2px; padding: 1px 6px; border-radius: 9999px; font-size: 8px; font-weight: 700; background: #e0f2fe; color: #0369a1; }
    .empty { color: #cbd5e1; }
</style>
</head>
<body>
<div class="header">
    <h1>Grade de Horários — {{ $turma->serie?->ser_nome ?? '' }} / {{ $turma->tur_nome }}</h1>
    <div class="sub">
        {{ $turma->escola?->esc_nome ?? '—' }}
        @if($turma->segmento) · {{ $turma->segmento->seg_nome_reduzido }} @endif
        @if($turma->anoLetivo) · {{ $turma->anoLetivo->anl_ano }} @endif
    </div>
    <div class="meta">Gerado em {{ now()->format('d/m/Y \à\s H:i') }}</div>
</div>

@if($grade->isEmpty() || empty($diasAtivos))
    <p style="color:#94a3b8;text-align:center;padding:24px">Nenhuma grade de horários configurada para este segmento.</p>
@else
<table>
    <thead>
        <tr>
            <th class="tempo">Tempo</th>
            <th class="hora">Horário</th>
            @foreach($diasAtivos as $label)
                <th>{{ $label }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @foreach($grade as $grh)
        <tr>
            <td class="tempo">{{ $grh->grh_ordem }}º</td>
            <td class="hora">{{ \Illuminate\Support\Str::substr($grh->grh_hora, 0, 5) }}</td>
            @foreach($diasAtivos as $dia => $label)
                @php($h = $map->get("{$dia}:{$grh->grh_id}"))
                <td>
                    @if($h)
                        <div class="prof">{{ $h->funcionario?->fun_nome ?? '—' }}</div>
                        <div class="disc">{{ $h->disciplina?->dis_nome ?? '—' }}</div>
                        @if($h->trh_fl_tc)<span class="tc">TC</span>@endif
                    @else
                        <span class="empty">—</span>
                    @endif
                </td>
            @endforeach
        </tr>
        @endforeach
    </tbody>
</table>
@endif
</body>
</html>
