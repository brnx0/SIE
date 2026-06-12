<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: DejaVu Sans, sans-serif; font-size: 10.5px; color: #111; }
.page { padding: 10mm 12mm; }
.header { text-align: center; border-bottom: 2px solid #4338ca; padding-bottom: 4mm; margin-bottom: 5mm; }
.header .entidade { font-size: 12px; font-weight: bold; }
.header .titulo { font-size: 14px; font-weight: bold; color: #4338ca; margin-top: 2mm; }
.header .sub { font-size: 9px; color: #555; }
.status { display: inline-block; padding: 1mm 3mm; border-radius: 10px; font-size: 9px; font-weight: bold; }
.s-pendente { background: #fef3c7; color: #92400e; }
.s-aprovado { background: #d1fae5; color: #065f46; }
.s-reprovado { background: #fee2e2; color: #991b1b; }
.s-correcao { background: #dbeafe; color: #1e40af; }

.box { border: 1px solid #ddd; border-radius: 4px; padding: 3mm 4mm; margin-bottom: 4mm; }
.box h3 { font-size: 10px; color: #4338ca; text-transform: uppercase; letter-spacing: .05em; margin-bottom: 2mm; border-bottom: 1px solid #eee; padding-bottom: 1mm; }
.grid { width: 100%; }
.grid td { vertical-align: top; padding: 1mm 2mm; font-size: 10px; }
.label { font-size: 8.5px; color: #6b7280; text-transform: uppercase; letter-spacing: .04em; }
.valor { font-size: 11px; font-weight: bold; color: #111; }

.bloco { font-size: 10px; line-height: 1.5; white-space: pre-wrap; padding: 2mm 0; }
.indicadores { font-size: 10px; line-height: 1.6; }
.indicadores li { margin-left: 5mm; padding: 0.5mm 0; }

.foot { margin-top: 8mm; font-size: 9px; color: #555; text-align: center; border-top: 1px solid #eee; padding-top: 3mm; }
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

    <div class="header">
        <div class="entidade">{{ $entidade ?? 'Secretaria Municipal de Educação' }}</div>
        <div class="titulo">PLANO DE AULA</div>
        <div class="sub">Diário Online</div>
    </div>

    <div class="box">
        <h3>Identificação</h3>
        <table class="grid">
            <tr>
                <td width="65%">
                    <div class="label">Professor</div>
                    <div class="valor">{{ $plano->funcionario->fun_nome ?? '—' }}</div>
                </td>
                <td width="35%">
                    <div class="label">Status</div>
                    <span class="status s-{{ $plano->dpa_status }}">{{ $statusMap[$plano->dpa_status] ?? $plano->dpa_status }}</span>
                </td>
            </tr>
            <tr>
                <td colspan="2">
                    <div class="label">Tema</div>
                    <div class="valor">{{ $plano->dpa_tema }}</div>
                </td>
            </tr>
            <tr>
                <td>
                    <div class="label">Escola</div>
                    <div>{{ $plano->escola->esc_nome ?? '—' }}</div>
                </td>
                <td>
                    <div class="label">Ano Letivo</div>
                    <div>{{ $plano->anoLetivo->anl_ano ?? '—' }}</div>
                </td>
            </tr>
            <tr>
                <td>
                    <div class="label">Turma</div>
                    <div>{{ ($plano->turma->serie->ser_nome ?? '') }} / {{ $plano->turma->tur_nome ?? '—' }}</div>
                </td>
                <td>
                    <div class="label">Componente Curricular</div>
                    <div>{{ $plano->disciplina->dis_nome ?? '—' }}</div>
                </td>
            </tr>
            <tr>
                <td>
                    <div class="label">{{ $tipoUnidadeLabel }}</div>
                    <div>{{ $plano->unidade->uni_numero ?? '' }}º {{ $tipoUnidadeLabel }}</div>
                </td>
                <td>
                    <div class="label">Período do Plano</div>
                    <div>{{ $dtIni }} até {{ $dtFim }}</div>
                </td>
            </tr>
        </table>
    </div>

    @if($indicadores->count() > 0)
    <div class="box">
        <h3>Objetivos Complementares / Recomposição / Descritor</h3>
        <ul class="indicadores">
            @foreach($indicadores as $ind)
                <li>{{ $ind->ind_descricao }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <div class="box">
        <h3>Objeto do Conhecimento / Saberes</h3>
        <div class="bloco">{{ $plano->dpa_objeto_conhecimento }}</div>
    </div>

    @if($plano->dpa_estrategias)
    <div class="box">
        <h3>Estratégias / Metodologia</h3>
        <div class="bloco">{{ $plano->dpa_estrategias }}</div>
    </div>
    @endif

    <div class="box">
        <h3>Recursos Didáticos</h3>
        <div class="bloco">{{ $plano->dpa_recursos }}</div>
    </div>

    @if($plano->dpa_competencias)
    <div class="box">
        <h3>Competências Gerais</h3>
        <div class="bloco">{{ $plano->dpa_competencias }}</div>
    </div>
    @endif

    @if($plano->dpa_avaliacao)
    <div class="box">
        <h3>Avaliação</h3>
        <div class="bloco">{{ $plano->dpa_avaliacao }}</div>
    </div>
    @endif

    @if($plano->dpa_status !== 'pendente' && $plano->dpa_obs_coordenador)
    <div class="box">
        <h3>Observação do Coordenador</h3>
        <div class="bloco">{{ $plano->dpa_obs_coordenador }}</div>
    </div>
    @endif

    <div class="foot">Emitido em {{ $hoje }} — Plano #{{ $plano->dpa_id }}</div>
</div>
</body>
</html>
