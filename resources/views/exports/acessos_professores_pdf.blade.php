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
    table { width: 100%; border-collapse: collapse; }
    thead th { background: #f1f5f9; padding: 7px 10px; text-align: left; font-size: 9px; text-transform: uppercase; letter-spacing: 0.05em; color: #475569; border-bottom: 1px solid #e2e8f0; }
    tbody tr { border-bottom: 1px solid #f1f5f9; }
    tbody tr:nth-child(even) { background: #fafafa; }
    td { padding: 7px 10px; vertical-align: middle; }
    .badge { display: inline-block; padding: 2px 8px; border-radius: 9999px; font-size: 9px; font-weight: 600; }
    .badge-ok { background: #d1fae5; color: #065f46; }
    .badge-block { background: #fef3c7; color: #92400e; }
    .badge-login { background: #e0e7ff; color: #3730a3; }
    .footer { padding: 10px 24px 14px; border-top: 1px solid #e2e8f0; background: #f8fafc; }
    .footer-item .label { font-size: 9px; text-transform: uppercase; color: #64748b; letter-spacing: 0.05em; }
    .footer-item .value { font-size: 16px; font-weight: 700; color: #1e293b; }
    .meta { font-size: 9px; color: #94a3b8; margin-top: 6px; }
</style>
</head>
<body>
<div class="header">
    <h1>Acessos de Professores</h1>
    <p>Relatório gerado em {{ now()->format('d/m/Y \à\s H:i') }}</p>
</div>
<table>
    <thead>
        <tr>
            <th>Nome</th>
            <th>CPF</th>
            <th>Escola(s)</th>
            <th>Perfil sugerido</th>
            <th>Situação</th>
        </tr>
    </thead>
    <tbody>
        @forelse($linhas as $l)
        <tr>
            <td><strong>{{ $l['fun_nome'] }}</strong></td>
            <td>{{ $l['fun_cpf'] ?: '—' }}</td>
            <td>{{ implode(', ', $l['escolas']) ?: '—' }}</td>
            <td>{{ implode(', ', array_map(fn ($r) => $roles[$r] ?? $r, $l['roles'])) ?: '—' }}</td>
            <td>
                @php
                    $cls = $l['motivo'] === 'ok' ? 'badge-ok' : ($l['motivo'] === 'com_login' ? 'badge-login' : 'badge-block');
                @endphp
                <span class="badge {{ $cls }}">{{ $l['motivo_label'] }}</span>
            </td>
        </tr>
        @empty
        <tr><td colspan="5" style="text-align:center;padding:20px;color:#94a3b8">Nenhum registro encontrado.</td></tr>
        @endforelse
    </tbody>
</table>
<div class="footer">
    <div class="footer-item"><div class="label">Total</div><div class="value">{{ $total }}</div></div>
    <div class="meta">SIE Matrícula · Exportação automática</div>
</div>
</body>
</html>
