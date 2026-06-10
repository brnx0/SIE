<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Declaração de Transferência</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; color: #000; font-size: 11pt; }
        .cab { text-align: center; border-bottom: 1.5px solid #000; padding-bottom: 6px; margin-bottom: 10px; }
        .cab .linha { font-size: 9pt; font-weight: 700; }
        .cab .ent  { font-size: 12pt; font-weight: 700; margin-top: 2px; }
        .cab .esc  { font-size: 10pt; font-weight: 700; margin-top: 2px; }
        h1.titulo {
            text-align: center;
            font-size: 14pt;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin: 18px 0 22px;
        }
        p.declaracao { text-align: justify; line-height: 1.7; font-size: 11pt; margin-bottom: 28px; }
        .grid { width: 100%; margin-top: 16px; }
        .grid td { vertical-align: top; padding-bottom: 14px; }
        .label {
            font-size: 8.5pt; font-weight: 700;
            text-transform: uppercase; color: #555;
        }
        .valor { font-size: 11pt; font-weight: 700; padding-top: 2px; }
        .valor.underline { border-bottom: 1px solid #000; }
        .assinatura {
            margin-top: 60px;
            text-align: center;
            font-size: 10pt;
        }
        .assinatura .linha-ass {
            border-top: 1px solid #000;
            width: 60%;
            margin: 0 auto 4px;
        }
        .data { text-align: center; margin-top: 18px; font-size: 10pt; }
    </style>
</head>
<body>
    @php
        $formatDate = fn ($d) => $d ? \Carbon\Carbon::parse($d)->format('d/m/Y') : '—';
        $formatDateTime = fn ($d) => $d ? \Carbon\Carbon::parse($d)->format('d/m/Y H:i') : '—';
        $mesPt = ['', 'Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
        $hoje = now();
        $dataExtenso = $hoje->day . ' de ' . $mesPt[(int) $hoje->format('n')] . ' de ' . $hoje->year;

        $filiacaoMae = $aluno?->aln_filiacao_1_tipo === 'MAE' ? $aluno?->aln_filiacao_1
            : ($aluno?->aln_filiacao_2_tipo === 'MAE' ? $aluno?->aln_filiacao_2 : null);
        $filiacaoPai = $aluno?->aln_filiacao_1_tipo === 'PAI' ? $aluno?->aln_filiacao_1
            : ($aluno?->aln_filiacao_2_tipo === 'PAI' ? $aluno?->aln_filiacao_2 : null);

        $nomeAluno = mb_strtoupper($aluno?->aln_nome ?? '—', 'UTF-8');
        $nomeMae   = mb_strtoupper($filiacaoMae ?? 'FILIAÇÃO NÃO INFORMADA', 'UTF-8');
        $nomePai   = mb_strtoupper($filiacaoPai ?? 'FILIAÇÃO NÃO INFORMADA', 'UTF-8');
        $modalidadeMap = ['PRESENCIAL' => 'Presencial', 'EAD' => 'EAD', 'HIBRIDA' => 'Híbrida', 'SEMIPRESENCIAL' => 'Semipresencial'];
        $modalidade = $modalidadeMap[strtoupper($turma?->tur_mediacao ?? 'PRESENCIAL')] ?? 'Presencial';
    @endphp

    <div class="cab">
        @if ($parametros?->par_msg_cab_estado)
            <div class="linha">{{ $parametros->par_msg_cab_estado }}</div>
        @endif
        @if ($parametros?->par_msg_cab_secretaria)
            <div class="linha">{{ $parametros->par_msg_cab_secretaria }}</div>
        @endif
        <div class="ent">{{ $parametros?->par_nome_entidade ?? '—' }}</div>
        @if ($escola)
            <div class="esc">{{ $escola->esc_nome }}</div>
        @endif
    </div>

    <h1 class="titulo">Declaração de Transferência</h1>

    <p class="declaracao">
        Declaramos que <b>{{ $nomeAluno }}</b>, matrícula nº
        <b>{{ $aluno?->aln_nr_matricula ?? '—' }}</b>, nascido(a) em
        <b>{{ $formatDate($aluno?->aln_dt_nascimento) }}</b>, filho(a) da Sra.
        <b>{{ $nomeMae }}</b> e do Sr.
        <b>{{ $nomePai }}</b>, estava regularmente matriculado(a) no ano letivo de
        <b>{{ $anoLetivo?->anl_ano ?? '—' }}</b> no curso abaixo relacionado, e sua
        transferência foi requerida em
        <b>{{ $formatDateTime($movimentacao->mva_dt_movimentacao . ' ' . ($movimentacao->mva_created_at?->format('H:i') ?? '')) }}</b>.
    </p>

    <table class="grid">
        <tr>
            <td colspan="2">
                <div class="label">Curso</div>
                <div class="valor">{{ $segmento?->seg_nome_completo ?? $segmento?->seg_nome_reduzido ?? '—' }}</div>
            </td>
        </tr>
        <tr>
            <td style="width: 60%;">
                <div class="label">Modalidade</div>
                <div class="valor">{{ strtoupper($modalidade) }}</div>
            </td>
            <td>
                <div class="label">Ano Escolar</div>
                <div class="valor">{{ $serie?->ser_nome ?? '—' }}</div>
            </td>
        </tr>
        <tr>
            <td colspan="2">
                <div class="label">Apto a Cursar</div>
                <div class="valor underline">{{ $serieApto?->ser_nome ?? ($serie?->ser_nome ?? '—') }}</div>
            </td>
        </tr>
    </table>

    <div class="assinatura">
        <div style="margin-top: 80px;">
            <div class="linha-ass"></div>
            <div>Assinatura do Gestor(a) / Secretário(a)</div>
        </div>
        <div class="data">{{ $dataExtenso }}</div>
    </div>
</body>
</html>
