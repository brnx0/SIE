<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1e293b; }
    .header { padding: 18px 24px 12px; border-bottom: 2px solid #0d9488; }
    .header h1 { font-size: 18px; font-weight: 700; color: #0f766e; }
    .header p { font-size: 10px; color: #64748b; margin-top: 2px; }
    table { width: 100%; border-collapse: collapse; }
    thead th { background: #f1f5f9; padding: 7px 10px; text-align: left; font-size: 9px; text-transform: uppercase; letter-spacing: 0.05em; color: #475569; border-bottom: 1px solid #e2e8f0; }
    tbody tr { border-bottom: 1px solid #f1f5f9; }
    tbody tr:nth-child(even) { background: #fafafa; }
    td { padding: 7px 10px; vertical-align: middle; }
    .badge { display: inline-block; padding: 2px 8px; border-radius: 9999px; font-size: 9px; font-weight: 600; }
    .badge-aberta { background: #d1fae5; color: #065f46; }
    .badge-encerrada { background: #f1f5f9; color: #475569; }
    .footer { padding: 10px 24px 14px; border-top: 1px solid #e2e8f0; background: #f8fafc; }
    .footer-grid { display: flex; gap: 32px; }
    .footer-item .label { font-size: 9px; text-transform: uppercase; color: #64748b; letter-spacing: 0.05em; }
    .footer-item .value { font-size: 16px; font-weight: 700; color: #1e293b; }
    .meta { font-size: 9px; color: #94a3b8; margin-top: 6px; }
</style>
</head>
<body>
<div class="header">
    <h1>Turmas de Atividade</h1>
    <p>Atividade Complementar · Relatório gerado em {{ now()->format('d/m/Y \à\s H:i') }}</p>
</div>
<table>
    <thead>
        <tr>
            <th>Turma</th>
            <th>Escola</th>
            <th style="text-align:center">Ano</th>
            <th>Turno</th>
            <th style="text-align:center">Cap.</th>
            <th style="text-align:center">Situação</th>
        </tr>
    </thead>
    <tbody>
        @forelse($turmas as $t)
        <tr>
            <td><strong>{{ $t->tur_nome }}</strong></td>
            <td>{{ $t->escola?->esc_nome ?? '—' }}</td>
            <td style="text-align:center">{{ $t->anoLetivo?->anl_ano ?? '—' }}</td>
            <td>{{ $turnos[$t->tur_turno] ?? $t->tur_turno }}</td>
            <td style="text-align:center">{{ $t->tur_capacidade ?? '—' }}</td>
            <td style="text-align:center">
                <span class="badge {{ $t->tur_situacao === 'ABERTA' ? 'badge-aberta' : 'badge-encerrada' }}">
                    {{ $t->tur_situacao }}
                </span>
            </td>
        </tr>
        @empty
        <tr><td colspan="6" style="text-align:center;padding:20px;color:#94a3b8">Nenhum registro encontrado.</td></tr>
        @endforelse
    </tbody>
</table>
<div class="footer">
    <div class="footer-grid">
        <div class="footer-item"><div class="label">Total</div><div class="value">{{ $total }}</div></div>
        <div class="footer-item"><div class="label">Abertas</div><div class="value" style="color:#059669">{{ $totalAbertas }}</div></div>
        <div class="footer-item"><div class="label">Encerradas</div><div class="value" style="color:#94a3b8">{{ $totalEncerradas }}</div></div>
    </div>
    <div class="meta">SIE Matrícula · Exportação automática</div>
</div>
</body>
</html>
