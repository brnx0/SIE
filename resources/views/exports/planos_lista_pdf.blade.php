<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1e293b; }
    .header { padding: 18px 24px 12px; border-bottom: 2px solid #6366f1; }
    .header h1 { font-size: 18px; font-weight: 700; color: #4f46e5; }
    .header p { font-size: 10px; color: #64748b; margin-top: 2px; }
    table { width: 100%; border-collapse: collapse; }
    thead th { background: #f1f5f9; padding: 7px 8px; text-align: left; font-size: 9px; text-transform: uppercase; letter-spacing: 0.05em; color: #475569; border-bottom: 1px solid #e2e8f0; }
    tbody tr { border-bottom: 1px solid #f1f5f9; }
    tbody tr:nth-child(even) { background: #fafafa; }
    td { padding: 6px 8px; vertical-align: middle; }
    .badge { display: inline-block; padding: 2px 8px; border-radius: 9999px; font-size: 9px; font-weight: 600; }
    .badge-pendente  { background: #fef3c7; color: #92400e; }
    .badge-aprovado  { background: #d1fae5; color: #065f46; }
    .badge-reprovado { background: #fee2e2; color: #991b1b; }
    .badge-correcao  { background: #e0f2fe; color: #075985; }
    .footer { padding: 10px 24px 14px; border-top: 1px solid #e2e8f0; background: #f8fafc; }
    .footer-grid { display: flex; gap: 32px; }
    .footer-item .label { font-size: 9px; text-transform: uppercase; color: #64748b; letter-spacing: 0.05em; }
    .footer-item .value { font-size: 16px; font-weight: 700; color: #1e293b; }
    .meta { font-size: 9px; color: #94a3b8; margin-top: 6px; }
</style>
</head>
<body>
<div class="header">
    <h1>{{ $titulo }}</h1>
    <p>Gerado em {{ now()->format('d/m/Y \à\s H:i') }}@if(!empty($filtroDescricao)) · {{ $filtroDescricao }}@endif</p>
</div>
<table>
    <thead>
        <tr>
            <th style="text-align:center;width:80px">Status</th>
            @if($mostraProfessor)<th>Professor</th>@endif
            <th>Escola</th>
            <th>Turma</th>
            <th>Disciplina</th>
            <th>Unidade</th>
            <th>Tema</th>
            <th style="text-align:center;width:100px">Período</th>
        </tr>
    </thead>
    <tbody>
        @forelse($planos as $p)
        <tr>
            <td style="text-align:center">
                <span class="badge badge-{{ $p->dpa_status ?? $p->dae_status ?? 'pendente' }}">
                    {{ \Illuminate\Support\Str::ucfirst($p->dpa_status ?? $p->dae_status ?? 'pendente') }}
                </span>
            </td>
            @if($mostraProfessor)<td>{{ $p->funcionario?->fun_nome ?? '—' }}</td>@endif
            <td>{{ $p->escola?->esc_nome ?? '—' }}</td>
            <td>
                <strong>{{ $p->turma?->tur_nome ?? '—' }}</strong>
                @if($p->turma?->serie?->ser_nome)
                    <div style="font-size:9px;color:#64748b">{{ $p->turma->serie->ser_nome }}</div>
                @endif
            </td>
            <td>{{ $p->disciplina?->dis_nome ?? '—' }}</td>
            <td>{{ optional($p->unidade)->uni_numero }}º {{ \Illuminate\Support\Str::lower($p->unidade?->uni_tipo ?? '') }}</td>
            <td>{{ $p->dpa_tema ?? $p->dae_tema ?? '—' }}</td>
            <td style="text-align:center;white-space:nowrap">
                {{ optional($p->dpa_dt_inicio ?? $p->dae_dt_inicio)?->format('d/m/Y') ?? '—' }}
                <div style="font-size:8px;color:#94a3b8">a {{ optional($p->dpa_dt_fim ?? $p->dae_dt_fim)?->format('d/m/Y') ?? '—' }}</div>
            </td>
        </tr>
        @empty
        <tr><td colspan="{{ $mostraProfessor ? 8 : 7 }}" style="text-align:center;padding:20px;color:#94a3b8">Nenhum plano encontrado.</td></tr>
        @endforelse
    </tbody>
</table>
<div class="footer">
    <div class="footer-grid">
        <div class="footer-item"><div class="label">Total</div><div class="value">{{ $total }}</div></div>
        @foreach($statusCounts as $k => $v)
            <div class="footer-item"><div class="label">{{ \Illuminate\Support\Str::ucfirst($k) }}</div><div class="value">{{ $v }}</div></div>
        @endforeach
    </div>
    <div class="meta">SIE Matrícula · Exportação automática</div>
</div>
</body>
</html>
