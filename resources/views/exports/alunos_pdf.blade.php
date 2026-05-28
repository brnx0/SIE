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
    .footer { padding: 10px 24px 14px; border-top: 1px solid #e2e8f0; background: #f8fafc; }
    .footer-grid { display: flex; gap: 32px; }
    .footer-item .label { font-size: 9px; text-transform: uppercase; color: #64748b; letter-spacing: 0.05em; }
    .footer-item .value { font-size: 16px; font-weight: 700; color: #1e293b; }
    .meta { font-size: 9px; color: #94a3b8; margin-top: 6px; }
</style>
</head>
<body>
<div class="header">
    <h1>Alunos</h1>
    <p>Relatório gerado em {{ now()->format('d/m/Y \à\s H:i') }}@if($search) · Filtro: "{{ $search }}"@endif</p>
</div>
<table>
    <thead>
        <tr>
            <th>Matrícula</th>
            <th>Nome</th>
            <th>CPF</th>
            <th>Nascimento</th>
            <th>Município / UF</th>
        </tr>
    </thead>
    <tbody>
        @forelse($alunos as $a)
        @php
            $cpf = $a->aln_cpf ? preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $a->aln_cpf) : '—';
        @endphp
        <tr>
            <td>{{ $a->aln_nr_matricula ?? '—' }}</td>
            <td><strong>{{ $a->aln_nome }}</strong></td>
            <td>{{ $cpf }}</td>
            <td>{{ $a->aln_dt_nascimento?->format('d/m/Y') ?? '—' }}</td>
            <td>{{ $a->municipioNascimento ? $a->municipioNascimento->mun_nome . ' / ' . $a->municipioNascimento->mun_uf : '—' }}</td>
        </tr>
        @empty
        <tr><td colspan="5" style="text-align:center;padding:20px;color:#94a3b8">Nenhum registro encontrado.</td></tr>
        @endforelse
    </tbody>
</table>
<div class="footer">
    <div class="footer-grid">
        <div class="footer-item"><div class="label">Total de alunos</div><div class="value">{{ $total }}</div></div>
    </div>
    <div class="meta">SIE Matrícula · Exportação automática</div>
</div>
</body>
</html>
