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
    .badge-1 { background: #d1fae5; color: #065f46; }
    .badge-2 { background: #fef3c7; color: #92400e; }
    .badge-3 { background: #fee2e2; color: #991b1b; }
    .footer { padding: 10px 24px 14px; border-top: 1px solid #e2e8f0; background: #f8fafc; }
    .footer-grid { display: flex; gap: 32px; }
    .footer-item .label { font-size: 9px; text-transform: uppercase; color: #64748b; letter-spacing: 0.05em; }
    .footer-item .value { font-size: 16px; font-weight: 700; color: #1e293b; }
    .meta { font-size: 9px; color: #94a3b8; margin-top: 6px; }
</style>
</head>
<body>
<div class="header">
    <h1>Escolas</h1>
    <p>Relatório gerado em {{ now()->format('d/m/Y \à\s H:i') }}@if($search) · Filtro: "{{ $search }}"@endif</p>
</div>
<table>
    <thead>
        <tr>
            <th>Apelido</th>
            <th>Nome</th>
            <th>INEP</th>
            <th>CNPJ</th>
            <th>Município / UF</th>
            <th style="text-align:center">Situação</th>
        </tr>
    </thead>
    <tbody>
        @forelse($escolas as $e)
        @php $cnpj = $e->esc_cnpj ? preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $e->esc_cnpj) : '—'; @endphp
        <tr>
            <td><strong>{{ $e->esc_apelido ?? $e->esc_nome }}</strong></td>
            <td>{{ $e->esc_nome }}</td>
            <td>{{ $e->esc_cd_inep ?? '—' }}</td>
            <td>{{ $cnpj }}</td>
            <td>{{ $e->municipio ? $e->municipio->mun_nome . ' / ' . $e->municipio->mun_uf : '—' }}</td>
            <td style="text-align:center">
                <span class="badge badge-{{ $e->esc_situacao_func }}">
                    {{ $situacaoLabels[$e->esc_situacao_func] ?? '—' }}
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
        <div class="footer-item"><div class="label">Em atividade</div><div class="value" style="color:#059669">{{ $totalAtividade }}</div></div>
        <div class="footer-item"><div class="label">Paralisadas</div><div class="value" style="color:#d97706">{{ $totalParalisadas }}</div></div>
        <div class="footer-item"><div class="label">Extintas</div><div class="value" style="color:#dc2626">{{ $totalExtintas }}</div></div>
    </div>
    <div class="meta">SIE Matrícula · Exportação automática</div>
</div>
</body>
</html>
