<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 10.5px; color: #1f2937; line-height: 1.4; }
    .page { padding: 4mm 12mm 10mm; position: relative; }

    .header { text-align: center; padding-bottom: 3mm; margin-bottom: 4mm; border-bottom: 1px solid #cbd5e1; }
    .header .entidade { font-size: 10.5px; font-weight: 600; color: #374151; }
    .header .titulo { font-size: 14px; font-weight: 700; letter-spacing: .03em; margin-top: 1mm; color: #111827; }
    .header .sub { font-size: 8.5px; color: #6b7280; margin-top: 0.5mm; }
    .status-top { position: absolute; top: 4mm; right: 12mm; }

    .meta { display: table; width: 100%; margin-bottom: 5mm; }
    .meta .row { display: table-row; }
    .meta .cell { display: table-cell; vertical-align: top; padding: 1mm 0; }
    .meta .label { font-size: 8.5px; color: #6b7280; text-transform: uppercase; letter-spacing: .05em; }
    .meta .valor { font-size: 10.5px; color: #111827; font-weight: 500; }
    .status { display: inline-block; padding: 0.5mm 2.5mm; font-size: 9px; font-weight: 600; border: 1px solid #94a3b8; color: #334155; border-radius: 2px; }

    .section { margin-top: 5mm; padding-top: 3mm; border-top: 1px solid #e5e7eb; }
    .section h3 { font-size: 10px; font-weight: 700; color: #111827; text-transform: uppercase; letter-spacing: .06em; margin-bottom: 2mm; }
    .bloco { font-size: 10.5px; line-height: 1.55; white-space: pre-wrap; color: #1f2937; }

    .indicadores { list-style: disc; padding-left: 5mm; font-size: 10.5px; line-height: 1.6; }
    .indicadores li { margin-bottom: 0.5mm; }

    .foot { margin-top: 10mm; padding-top: 3mm; border-top: 1px solid #e5e7eb; font-size: 8.5px; color: #6b7280; text-align: center; }
</style>
</head>
<body>
<div class="page">
    @php
        $tz = 'America/Bahia';
        $hoje = \Carbon\Carbon::now($tz)->format('d/m/Y H:i');
        $dtIni = \Carbon\Carbon::parse($plano->dpa_dt_inicio)->format('d/m/Y');
        $dtFim = \Carbon\Carbon::parse($plano->dpa_dt_fim)->format('d/m/Y');
        $statusMap = ['pendente'=>'Pendente','aprovado'=>'Aprovado','reprovado'=>'Reprovado','correcao'=>'Em correção'];
        $tipoUnidadeLabel = ucfirst(strtolower($plano->unidade->uni_tipo ?? 'Unidade'));
    @endphp

    <span class="status status-top">{{ $statusMap[$plano->dpa_status] ?? $plano->dpa_status }}</span>

    <div class="header">
        <div class="entidade">{{ $entidade ?? 'Secretaria Municipal de Educação' }}</div>
        <div class="titulo">Plano de Aula</div>
        <div class="sub">Diário Online</div>
    </div>

    <div style="margin-bottom:4mm">
        <div class="label">Professor</div>
        <div class="valor">{{ $plano->funcionario->fun_nome ?? '—' }}</div>
    </div>
    <div style="margin-bottom:4mm">
        <div class="label">Tema</div>
        <div class="valor">{{ $plano->dpa_tema }}</div>
    </div>

    <table style="width:100%; margin-bottom:4mm">
        <tr>
            <td style="width:50%; padding-right:4mm">
                <div class="label" style="font-size:8.5px; color:#6b7280; text-transform:uppercase; letter-spacing:.05em">Escola</div>
                <div style="font-size:10.5px">{{ $plano->escola->esc_nome ?? '—' }}</div>
            </td>
            <td style="width:25%">
                <div class="label" style="font-size:8.5px; color:#6b7280; text-transform:uppercase; letter-spacing:.05em">Ano Letivo</div>
                <div style="font-size:10.5px">{{ $plano->anoLetivo->anl_ano ?? '—' }}</div>
            </td>
            <td style="width:25%">
                <div class="label" style="font-size:8.5px; color:#6b7280; text-transform:uppercase; letter-spacing:.05em">{{ $tipoUnidadeLabel }}</div>
                <div style="font-size:10.5px">{{ $plano->unidade->uni_numero ?? '' }}º {{ $tipoUnidadeLabel }}</div>
            </td>
        </tr>
        <tr>
            <td style="padding-top:3mm">
                <div class="label" style="font-size:8.5px; color:#6b7280; text-transform:uppercase; letter-spacing:.05em">Turma</div>
                <div style="font-size:10.5px">{{ ($plano->turma->serie->ser_nome ?? '') }} / {{ $plano->turma->tur_nome ?? '—' }}</div>
            </td>
            <td style="padding-top:3mm">
                <div class="label" style="font-size:8.5px; color:#6b7280; text-transform:uppercase; letter-spacing:.05em">Componente</div>
                <div style="font-size:10.5px">{{ $plano->disciplina->dis_nome ?? '—' }}</div>
            </td>
            <td style="padding-top:3mm">
                <div class="label" style="font-size:8.5px; color:#6b7280; text-transform:uppercase; letter-spacing:.05em">Período</div>
                <div style="font-size:10.5px">{{ $dtIni }} a {{ $dtFim }}</div>
            </td>
        </tr>
    </table>

    @if($indicadores->count() > 0)
    <div class="section">
        <h3>Objetivos de Aprendizagem e Desenvolvimento (Indicadores)</h3>
        <ul class="indicadores">
            @foreach($indicadores as $ind)
                <li>{{ $ind->ind_descricao }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <div class="section">
        <h3>Objeto do Conhecimento</h3>
        <div class="bloco">{{ $plano->dpa_objeto_conhecimento }}</div>
    </div>

    @if($plano->dpa_estrategias)
    <div class="section">
        <h3>Estratégias / Metodologia</h3>
        <div class="bloco">{{ $plano->dpa_estrategias }}</div>
    </div>
    @endif

    <div class="section">
        <h3>Recursos Didáticos</h3>
        <div class="bloco">{{ $plano->dpa_recursos }}</div>
    </div>

    @if($plano->dpa_competencias)
    <div class="section">
        <h3>Competências Gerais</h3>
        <div class="bloco">{{ $plano->dpa_competencias }}</div>
    </div>
    @endif

    @if($plano->dpa_avaliacao)
    <div class="section">
        <h3>Avaliação</h3>
        <div class="bloco">{{ $plano->dpa_avaliacao }}</div>
    </div>
    @endif

    @if($plano->dpa_objetivos_complementares)
    <div class="section">
        <h3>Objetivos Complementares / Recomposição / Descritor</h3>
        <div class="bloco">{{ $plano->dpa_objetivos_complementares }}</div>
    </div>
    @endif

    @if($plano->dpa_status !== 'pendente' && $plano->dpa_obs_coordenador)
    <div class="section">
        <h3>Observação do Coordenador</h3>
        <div class="bloco">{{ $plano->dpa_obs_coordenador }}</div>
        @if(!empty($plano->validadoPor) || !empty($plano->dpa_validado_em))
            <div style="font-size:8.5px; color:#6b7280; margin-top:2mm">
                Validado por {{ $plano->validadoPor->name ?? '—' }} em
                {{ $plano->dpa_validado_em ? \Carbon\Carbon::parse($plano->dpa_validado_em)->format('d/m/Y H:i') : '—' }}
            </div>
        @endif
    </div>
    @endif

    <div class="foot">Emitido em {{ $hoje }} — Plano #{{ $plano->dpa_id }}</div>
</div>
</body>
</html>
