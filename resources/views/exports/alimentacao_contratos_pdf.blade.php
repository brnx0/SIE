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
    td { padding: 7px 10px; vertical-align: top; }
    .badge { display: inline-block; padding: 2px 8px; border-radius: 9999px; font-size: 9px; font-weight: 600; }
    .badge-vigente { background: #d1fae5; color: #065f46; }
    .badge-outro { background: #f1f5f9; color: #475569; }
    .footer { padding: 10px 24px 14px; border-top: 1px solid #e2e8f0; background: #f8fafc; }
    .footer-grid { display: flex; gap: 32px; }
    .footer-item .label { font-size: 9px; text-transform: uppercase; color: #64748b; letter-spacing: 0.05em; }
    .footer-item .value { font-size: 16px; font-weight: 700; color: #1e293b; }
</style>
</head>
<body>
<div class="header">
    <h1>Contratos de Alimentação Escolar</h1>
    <p>Relatório gerado em {{ now()->format('d/m/Y \à\s H:i') }}@if($search) · Filtro: "{{ $search }}"@endif</p>
</div>
<table>
    <thead>
        <tr>
            <th style="width:90px">Contrato</th>
            <th>Fornecedor</th>
            <th>Objeto</th>
            <th style="width:120px">Vigência</th>
            <th style="text-align:right;width:90px">Valor</th>
            <th style="text-align:center;width:75px">Situação</th>
        </tr>
    </thead>
    <tbody>
        @forelse($contratos as $c)
        <tr>
            <td><strong>{{ $c->ctr_nr_contrato }}/{{ $c->ctr_ano }}</strong></td>
            <td>{{ $c->fornecedor?->frn_razao_social ?? '—' }}</td>
            <td>{{ \Illuminate\Support\Str::limit($c->ctr_objeto, 90) }}</td>
            <td>{{ $c->ctr_dt_inicio->format('d/m/Y') }} a {{ $c->ctr_dt_fim->format('d/m/Y') }}</td>
            <td style="text-align:right">R$ {{ number_format((float) $c->ctr_vl_total, 2, ',', '.') }}</td>
            <td style="text-align:center">
                <span class="badge {{ $c->ctr_situacao === 'VIGENTE' ? 'badge-vigente' : 'badge-outro' }}">{{ $c->ctr_situacao }}</span>
            </td>
        </tr>
        @empty
        <tr><td colspan="6" style="text-align:center;padding:20px;color:#94a3b8">Nenhum registro encontrado.</td></tr>
        @endforelse
    </tbody>
</table>
<div class="footer">
    <div class="footer-grid">
        <div class="footer-item"><div class="label">Contratos</div><div class="value">{{ $total }}</div></div>
        <div class="footer-item"><div class="label">Valor total</div><div class="value">R$ {{ number_format($valorTotal, 2, ',', '.') }}</div></div>
    </div>
</div>
</body>
</html>
