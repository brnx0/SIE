<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111; }

.via { width: 100%; padding: 10mm 12mm 8mm 12mm; page-break-after: always; }
.via:last-child { page-break-after: avoid; }

/* Cabeçalho */
.header-box { border: 1px solid #aaa; margin-bottom: 5mm; }
.header-logo { width: 28mm; padding: 3mm 4mm; border-right: 1px solid #aaa; vertical-align: middle; text-align: center; overflow: hidden; }
.header-logo img { width: 60px; height: 48px; }
.header-logo-placeholder { font-size: 8px; font-weight: bold; color: #555; line-height: 1.4; }
.header-info { padding: 4mm 6mm; vertical-align: middle; }
.header-info .estado { font-size: 9px; }
.header-info .entidade { font-size: 10px; font-weight: bold; }
.header-info .secretaria { font-size: 9px; }
.header-date { width: 32mm; padding: 4mm; vertical-align: top; text-align: right; font-size: 9px; white-space: nowrap; }

/* Título */
.titulo-box { border: 1px solid #aaa; margin-bottom: 7mm; padding: 3mm; text-align: center; font-size: 12px; font-weight: bold; }

/* Campos */
.label { font-size: 8px; color: #777; text-transform: uppercase; letter-spacing: 0.05em; padding-bottom: 1mm; }
.valor { font-size: 11.5px; font-weight: bold; color: #111; padding-bottom: 4mm; }

/* Separador */
.sep { border-top: 1px solid #ccc; margin-bottom: 4mm; }

/* Termo */
.termo-titulo { font-size: 8px; color: #777; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 2.5mm; }
.termo-p { font-size: 9px; line-height: 1.6; text-align: justify; margin-bottom: 3mm; }

/* Assinaturas */
.ass-linha { border-top: 1px solid #333; width: 70mm; }
.ass-texto { font-size: 7.5px; text-transform: uppercase; color: #444; text-align: center; padding-top: 2mm; }

/* Rodapé */
.rod-label { font-size: 8px; color: #777; text-transform: uppercase; letter-spacing: 0.04em; }
.rod-valor { font-size: 11px; font-weight: bold; }

.emitido { font-size: 9px; color: #555; text-align: center; margin-top: 3mm; }
.via-num { font-size: 9px; font-weight: bold; text-align: right; }
</style>
</head>
<body>

@php
    $aluno        = $matricula->aluno;
    $turma        = $matricula->turma;
    $escola       = $turma?->escola;
    $serie        = $turma?->serie;
    $anoLetivo    = $turma?->anoLetivo;
    $ano          = $anoLetivo?->anl_ano ?? now()->year;
    $tz           = 'America/Bahia';
    $hoje         = \Carbon\Carbon::today($tz)->format('d/m/Y');
    $hojeD        = \Carbon\Carbon::today($tz)->format('d');
    $hojeM        = \Carbon\Carbon::today($tz)->format('m');
    $hojeY        = \Carbon\Carbon::today($tz)->format('Y');
    $dtMatricula  = $matricula->tma_dt_matricula?->format('d/m/Y') ?? '—';
    $dtNasc       = $aluno?->aln_dt_nascimento
        ? \Carbon\Carbon::parse($aluno->aln_dt_nascimento)->format('d/m/Y')
        : '—';
    $turno = match(strtoupper($turma?->tur_turno ?? '')) {
        'INTEGRAL'   => 'Integral',
        'MATUTINO'   => 'Matutino',
        'VESPERTINO' => 'Vespertino',
        'NOTURNO'    => 'Noturno',
        default      => ucfirst(strtolower($turma?->tur_turno ?? '—')),
    };
    $nomeEscola      = strtoupper($escola?->esc_nome ?? '—');
    $nomeAluno       = strtoupper($aluno?->aln_nome ?? '—');
    $nomeResponsavel = strtoupper($aluno?->aln_filiacao_1 ?? '—');
    $nomeMatric      = strtoupper($matriculadoPor);
    $vias = ['1ª VIA', '2ª VIA'];
@endphp

@foreach($vias as $via)
<div class="via">

{{-- ── Cabeçalho ─────────────────────────────────────────── --}}
<table class="header-box" width="100%" cellspacing="0" cellpadding="0">
    <tr>
        <td class="header-logo">
            @if($logoPath)
                <img src="{{ $logoPath }}" alt="Logo" width="60" height="48" style="width:60px;height:48px;object-fit:contain;">
            @else
                <div class="header-logo-placeholder">{{ $params->par_nome_entidade }}</div>
            @endif
        </td>
        <td class="header-info">
            <div class="estado">{{ $params->par_msg_cab_estado }}</div>
            <div class="entidade">{{ $params->par_nome_entidade }}</div>
            <div class="secretaria">{{ $params->par_msg_cab_secretaria }}</div>
        </td>
        <td class="header-date">Data: {{ $hoje }}</td>
    </tr>
</table>

{{-- ── Título ──────────────────────────────────────────────── --}}
<div class="titulo-box">Comprovante de Matrícula {{ $ano }}</div>

{{-- ── Escola (linha completa) ────────────────────────────── --}}
<div class="label">Escola</div>
<div class="valor">{{ $nomeEscola }}</div>

{{-- ── Ano Escolarização + Turno ───────────────────────────── --}}
<table width="100%" cellspacing="0" cellpadding="0">
    <tr>
        <td width="58%">
            <div class="label">Ano Escolarização</div>
            <div class="valor">{{ $serie?->ser_nome ?? '—' }}</div>
        </td>
        <td width="42%">
            <div class="label">Turno</div>
            <div class="valor">{{ $turno }}</div>
        </td>
    </tr>
</table>

{{-- ── Turma + Matrícula ───────────────────────────────────── --}}
<table width="100%" cellspacing="0" cellpadding="0">
    <tr>
        <td width="58%">
            <div class="label">Turma</div>
            <div class="valor">{{ $turma?->tur_nome ?? '—' }}</div>
        </td>
        <td width="42%">
            <div class="label">Matrícula</div>
            <div class="valor">{{ $aluno?->aln_nr_matricula ?? '—' }}</div>
        </td>
    </tr>
</table>

{{-- ── Nome do Aluno + Nascimento ─────────────────────────── --}}
<table width="100%" cellspacing="0" cellpadding="0">
    <tr>
        <td width="58%">
            <div class="label">Nome do Aluno</div>
            <div class="valor">{{ $nomeAluno }}</div>
        </td>
        <td width="42%">
            <div class="label">Data&nbsp; de Nascimento</div>
            <div class="valor">{{ $dtNasc }}</div>
        </td>
    </tr>
</table>

{{-- ── Responsável + Telefone ─────────────────────────────── --}}
<table width="100%" cellspacing="0" cellpadding="0">
    <tr>
        <td width="58%">
            <div class="label">Responsável</div>
            <div class="valor">{{ $nomeResponsavel }}</div>
        </td>
        <td width="42%">
            <div class="label">Telefone</div>
            <div class="valor">{{ $aluno?->aln_telefone ?? '—' }}</div>
        </td>
    </tr>
</table>

<div class="sep"></div>

{{-- ── Termo ───────────────────────────────────────────────── --}}
<div class="termo-titulo">Termo de Responsabilidade</div>
<div class="termo-p">
    Responsabilizo-me por todos os danos materiais causados por mim ou pelo aluno supracitado
    ao patrimônio da Escola ou a pertences particulares dos colegas e de pessoas que nela
    trabalham. Atenderei todos os chamados da direção. Assim como cumprirei e farei cumprir o
    Regimento Interno desta Unidade Escolar.
</div>
<div class="termo-p">
    AUTORIZO o uso da imagem do(a) aluno(a) o(a) qual está sob minha responsabilidade, em
    todo e qualquer material entre fotos, documentos e outros meios de comunicação, para ser
    utilizada em campanhas promocionais e institucionais da Escola, Secretaria e/ou Prefeitura
    Municipal, bem como fundos, fundações e autarquias dela derivados, sejam essas destinadas
    à divulgação ao público em geral e/ou apenas para uso interno desta instituição, respeitando o
    que se aplica na Lei Geral de Proteção de Dados (LGPD).
</div>

{{-- ── Assinaturas ────────────────────────────────────────── --}}
<table width="100%" cellspacing="0" cellpadding="0" style="margin-top:8mm; margin-bottom:5mm;">
    <tr>
        <td width="50%" style="padding-right:10mm; padding-top:10mm;">
            <div class="ass-linha"></div>
            <div class="ass-texto">Assinatura do Responsável</div>
        </td>
        <td width="50%" style="padding-left:10mm; padding-top:10mm;">
            <div class="ass-linha"></div>
            <div class="ass-texto" style="white-space:nowrap;">Assinatura do Responsável da Unidade Escolar</div>
        </td>
    </tr>
</table>

{{-- ── Rodapé: data + matriculador ───────────────────────── --}}
<table width="100%" cellspacing="0" cellpadding="0" style="margin-top:4mm;">
    <tr>
        <td width="50%">
            <div class="rod-label">Data da Matrícula</div>
            <div class="rod-valor">{{ $dtMatricula }}</div>
        </td>
        <td width="50%" style="text-align:right;">
            <div class="rod-label">Matriculado por</div>
            <div class="rod-valor">{{ $nomeMatric }}</div>
        </td>
    </tr>
</table>

{{-- ── Emitido em + Via ────────────────────────────────────── --}}
<table width="100%" cellspacing="0" cellpadding="0" style="margin-top:3mm;">
    <tr>
        <td width="20%">&nbsp;</td>
        <td width="60%" style="text-align:center;" class="emitido">
            Emitido em &nbsp; {{ $hojeD }} / {{ $hojeM }} / {{ $hojeY }}
        </td>
        <td width="20%" class="via-num">{{ $via }}</td>
    </tr>
</table>

</div>
@endforeach

</body>
</html>
