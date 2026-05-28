<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1e293b; }
    .header { padding: 18px 24px 12px; border-bottom: 2px solid #0ea5e9; }
    .header h1 { font-size: 18px; font-weight: 700; color: #0284c7; }
    .header p { font-size: 10px; color: #64748b; margin-top: 2px; }
    table { width: 100%; border-collapse: collapse; }
    thead th { background: #f1f5f9; padding: 7px 10px; text-align: left; font-size: 9px; text-transform: uppercase; letter-spacing: 0.05em; color: #475569; border-bottom: 1px solid #e2e8f0; }
    tbody tr { border-bottom: 1px solid #f1f5f9; }
    tbody tr:nth-child(even) { background: #fafafa; }
    td { padding: 7px 10px; vertical-align: middle; }
    .badge { display: inline-block; padding: 2px 8px; border-radius: 9999px; font-size: 9px; font-weight: 600; }
    .badge-active { background: #d1fae5; color: #065f46; }
    .badge-inactive { background: #f1f5f9; color: #475569; }
    .footer { padding: 10px 24px 14px; border-top: 1px solid #e2e8f0; background: #f8fafc; }
    .footer-grid { display: flex; gap: 32px; }
    .footer-item .label { font-size: 9px; text-transform: uppercase; color: #64748b; letter-spacing: 0.05em; }
    .footer-item .value { font-size: 16px; font-weight: 700; color: #1e293b; }
    .footer-item .value-active { color: #059669; }
    .footer-item .value-inactive { color: #94a3b8; }
    .meta { font-size: 9px; color: #94a3b8; margin-top: 6px; }
</style>
</head>
<body>
<div class="header">
    <h1>Usuários</h1>
    <p>Relatório gerado em {{ now()->format('d/m/Y \à\s H:i') }}@if($search) · Filtro: "{{ $search }}"@endif@if($roleLabel) · Perfil: {{ $roleLabel }}@endif</p>
</div>
<table>
    <thead>
        <tr>
            <th>Nome</th>
            <th>E-mail</th>
            <th>Perfil</th>
            <th style="text-align:center">Status</th>
        </tr>
    </thead>
    <tbody>
        @forelse($users as $u)
        <tr>
            <td><strong>{{ $u->name }}</strong></td>
            <td>{{ $u->email }}</td>
            <td>{{ $roles[$u->role] ?? $u->role }}</td>
            <td style="text-align:center">
                <span class="badge {{ $u->active ? 'badge-active' : 'badge-inactive' }}">
                    {{ $u->active ? 'Ativo' : 'Inativo' }}
                </span>
            </td>
        </tr>
        @empty
        <tr><td colspan="4" style="text-align:center;padding:20px;color:#94a3b8">Nenhum registro encontrado.</td></tr>
        @endforelse
    </tbody>
</table>
<div class="footer">
    <div class="footer-grid">
        <div class="footer-item"><div class="label">Total</div><div class="value">{{ $total }}</div></div>
        <div class="footer-item"><div class="label">Ativos</div><div class="value value-active">{{ $totalAtivos }}</div></div>
        <div class="footer-item"><div class="label">Inativos</div><div class="value value-inactive">{{ $totalInativos }}</div></div>
    </div>
    <div class="meta">SIE Matrícula · Exportação automática</div>
</div>
</body>
</html>
