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
    .badge-active { background: #d1fae5; color: #065f46; }
    .badge-inactive { background: #f1f5f9; color: #475569; }
    .badge-yes { background: #e0e7ff; color: #3730a3; }
    .footer { padding: 10px 24px 14px; border-top: 1px solid #e2e8f0; background: #f8fafc; }
    .footer-grid { display: flex; gap: 32px; }
    .footer-item .label { font-size: 9px; text-transform: uppercase; color: #64748b; letter-spacing: 0.05em; }
    .footer-item .value { font-size: 16px; font-weight: 700; color: #1e293b; }
    .footer-item .value-active { color: #059669; }
    .footer-item .value-inactive { color: #94a3b8; }
</style>
</head>
<body>
<div class="header">
    <h1>Fornecedores</h1>
    <p>Relatório gerado em {{ now()->format('d/m/Y \à\s H:i') }}@if($search) · Filtro: "{{ $search }}"@endif</p>
</div>
<table>
    <thead>
        <tr>
            <th style="width:130px">Documento</th>
            <th>Razão Social</th>
            <th>Nome Fantasia</th>
            <th style="text-align:center;width:80px">Agric. Fam.</th>
            <th>Contato</th>
            <th>Município</th>
            <th style="text-align:center;width:70px">Situação</th>
        </tr>
    </thead>
    <tbody>
        @forelse($fornecedores as $f)
        <tr>
            <td>{{ $documento($f) ?: '—' }}</td>
            <td><strong>{{ $f->frn_razao_social }}</strong></td>
            <td>{{ $f->frn_nome_fantasia ?? '—' }}</td>
            <td style="text-align:center">@if($f->frn_fl_agricultura_familiar)<span class="badge badge-yes">Sim</span>@else — @endif</td>
            <td>{{ $f->frn_email ?? '' }}{{ $f->frn_email && $f->frn_fone ? ' · ' : '' }}{{ $fone($f) }}{{ !$f->frn_email && !$f->frn_fone ? '—' : '' }}</td>
            <td>{{ $f->municipio ? $f->municipio->mun_nome.'/'.$f->municipio->mun_uf : '—' }}</td>
            <td style="text-align:center">
                <span class="badge {{ $f->frn_fl_ativo ? 'badge-active' : 'badge-inactive' }}">
                    {{ $f->frn_fl_ativo ? 'Ativo' : 'Inativo' }}
                </span>
            </td>
        </tr>
        @empty
        <tr><td colspan="7" style="text-align:center;padding:20px;color:#94a3b8">Nenhum registro encontrado.</td></tr>
        @endforelse
    </tbody>
</table>
<div class="footer">
    <div class="footer-grid">
        <div class="footer-item">
            <div class="label">Total</div>
            <div class="value">{{ $total }}</div>
        </div>
        <div class="footer-item">
            <div class="label">Ativos</div>
            <div class="value value-active">{{ $totalAtivos }}</div>
        </div>
        <div class="footer-item">
            <div class="label">Inativos</div>
            <div class="value value-inactive">{{ $totalInativos }}</div>
        </div>
        <div class="footer-item">
            <div class="label">Agric. Familiar</div>
            <div class="value">{{ $totalAgric }}</div>
        </div>
    </div>
</div>
</body>
</html>
