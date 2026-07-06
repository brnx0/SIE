@php
    $LEI_FIXA = 'De acordo com o inciso VII do Artigo 24, combinado com o artigo 26 da Lei n.º 9.394/96, de 20/12/1996 e Lei n.º 11.274/2006, de 06/02/2006.';
    $dash = fn ($v) => ($v === null || $v === '') ? '' : $v;
@endphp
<style>
    * { font-family: sans-serif; }
    body { font-size: 8pt; color: #111; }
    .center { text-align: center; }
    .b { font-weight: bold; }
    .muted { color: #555; }
    h1 { font-size: 12pt; margin: 6px 0 2px; text-align: center; }
    h2 { font-size: 9pt; margin: 8px 0 3px; }
    .escola { text-align: center; line-height: 1.3; }
    .escola .nome { font-size: 11pt; font-weight: bold; }
    .ato { font-size: 7pt; margin-top: 2px; }
    .cert { margin: 6px 0; line-height: 1.4; text-align: justify; }
    table { width: 100%; border-collapse: collapse; }
    .matriz th, .matriz td { border: 0.5px solid #333; padding: 2px 3px; font-size: 7pt; }
    .matriz th { background: #eee; text-align: center; }
    .matriz td.comp { text-align: left; white-space: nowrap; }
    .matriz td.val { text-align: center; }
    .matriz tr.resumo td { background: #f5f5f5; font-weight: bold; }
    .rel th, .rel td { border: 0.5px solid #333; padding: 2px 4px; font-size: 7pt; text-align: center; }
    .rel th { background: #eee; text-align: left; width: 130px; white-space: nowrap; }
    .legenda { font-size: 7pt; margin-top: 6px; }
    .assin { margin-top: 26px; }
    .assin td { text-align: center; padding-top: 18px; font-size: 8pt; }
    .assin .linha { border-top: 0.5px solid #333; padding-top: 3px; }
</style>

{{-- Cabeçalho da escola --}}
<div class="escola">
    <div class="nome">{{ $h['escola']['nome'] }}</div>
    <div>{{ $dash($h['escola']['endereco']) }} @if($h['escola']['municipio_uf']) — {{ $h['escola']['municipio_uf'] }} @endif</div>
    <div>Telefone: {{ $dash($h['escola']['telefone']) }}</div>
    <div class="ato">
        Ato autorizativo:
        Resolução Nº {{ $dash($h['escola']['resolucao_num']) }} CME &nbsp;|&nbsp;
        Portaria Nº {{ $dash($h['escola']['portaria_num']) }} - GME &nbsp;|&nbsp;
        D.O.E. de {{ $dash($h['escola']['dt_publicacao']) }}
        @if($h['escola']['fundamentacao'])
            <br>Fundamentação legal: {{ $h['escola']['fundamentacao'] }}
        @endif
    </div>
</div>

<h1>Certificado de Conclusão / Histórico Escolar — Educação Básica</h1>

{{-- Dados do aluno + certificação --}}
<div class="cert">
    Certificamos que <span class="b">{{ $h['aluno']['nome'] }}</span>,
    nascido(a) em <span class="b">{{ $dash($h['aluno']['dt_nascimento']) }}</span>,
    natural de <span class="b">{{ $dash($h['aluno']['naturalidade']) }}</span>, Brasil,
    filho(a) de <span class="b">{{ $dash($h['aluno']['filiacao_1']) }}</span>
    @if($h['aluno']['filiacao_2']) e <span class="b">{{ $h['aluno']['filiacao_2'] }}</span> @endif,
    concluiu o <span class="b">{{ $dash($h['conclusao']['ano_escolar']) }}</span>
    @if($h['conclusao']['segmento']) do <span class="b">{{ $h['conclusao']['segmento'] }}</span> @endif
    conforme histórico escolar.
    <br><span class="muted">Fundamentação legal: {{ $LEI_FIXA }}</span>
</div>

{{-- Quadro componente × ano --}}
<h2>Componentes Curriculares</h2>
<table class="matriz">
    <thead>
        <tr>
            <th style="text-align:left;">Componente</th>
            @foreach($h['colunas'] as $c)
                <th>{{ $c['ano_escolar'] }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @foreach($h['componentes'] as $linha)
            <tr>
                <td class="comp">{{ $linha['componente'] }}</td>
                @foreach($linha['celulas'] as $cel)
                    <td class="val">{{ $cel }}</td>
                @endforeach
            </tr>
        @endforeach

        <tr class="resumo">
            <td class="comp">% Frequência</td>
            @foreach($h['colunas'] as $c)<td class="val">{{ $c['frequencia'] }}%</td>@endforeach
        </tr>
        <tr class="resumo">
            <td class="comp">Dias letivos</td>
            @foreach($h['colunas'] as $c)<td class="val">{{ $c['dias_letivos'] }}</td>@endforeach
        </tr>
        <tr class="resumo">
            <td class="comp">Carga horária</td>
            @foreach($h['colunas'] as $c)<td class="val">{{ $c['carga_horaria'] ?? '-' }}</td>@endforeach
        </tr>
        <tr class="resumo">
            <td class="comp">Situação final</td>
            @foreach($h['colunas'] as $c)<td class="val">{{ $c['situacao'] }}</td>@endforeach
        </tr>
    </tbody>
</table>

{{-- Relação por ano --}}
<h2>Dados por Ano Escolar</h2>
<table class="rel">
    <tr><th>Etapa / Equivalência</th>@foreach($h['relacao'] as $r)<td>{{ $r['etapa'] }}</td>@endforeach</tr>
    <tr><th>Ano Conclusão</th>@foreach($h['relacao'] as $r)<td>{{ $r['ano_conclusao'] }}</td>@endforeach</tr>
    <tr><th>Curso</th>@foreach($h['relacao'] as $r)<td>{{ $r['curso'] }}</td>@endforeach</tr>
    <tr><th>Estabelecimento de Ensino</th>@foreach($h['relacao'] as $r)<td>{{ $r['estabelecimento'] }}</td>@endforeach</tr>
    <tr><th>Município / UF</th>@foreach($h['relacao'] as $r)<td>{{ $r['municipio_uf'] }}</td>@endforeach</tr>
</table>

{{-- Legenda de conceitos (parametrizados) --}}
@if(count($h['legenda']))
    <div class="legenda">
        <span class="b">Conceitos:</span>
        @foreach($h['legenda'] as $l)
            ({{ $l->cnc_sigla }}) {{ $l->cnc_descricao }}@if(!$loop->last) &nbsp; @endif
        @endforeach
    </div>
@endif

{{-- Emissão + assinaturas --}}
<div class="center" style="margin-top:10px;">{{ $dash($h['emissao']['municipio_uf']) }}, {{ $h['emissao']['data'] }}</div>

<table class="assin">
    <tr>
        <td style="width:50%;"><div class="linha">Secretário(a) Escolar</div></td>
        <td style="width:50%;"><div class="linha">Diretor(a) Escolar</div></td>
    </tr>
</table>
