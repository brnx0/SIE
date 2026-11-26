---
titulo: Encerramento de Turmas
secao: Ano Letivo
ordem_secao: 8
ordem: 1
resumo: Fecha as turmas no fim do ano, gravando a situação final de cada aluno; permite cancelar o encerramento.
slug: encerramento-turmas
---

## O que é

O **Encerramento de Turmas** fecha as turmas no fim do ano letivo. Ao encerrar, o sistema calcula e grava a **situação final** de cada aluno ativo (aprovado, reprovado, etc.), com base nas notas e na frequência. A tela também mostra, por aluno, se todas as notas já foram lançadas.

## Como acessar

Menu **Ano Letivo → Encerramento de Turmas** (endereço `/encerramento-turmas`).

A secretaria opera apenas as escolas em que está lotada; o administrador, todas.

## Passo a passo

1. Selecione **Ano Letivo** e **Escola** e clique em **Listar Turmas**.
2. As turmas aparecem como cartões: **Fechada** (verde) ou **Ativa** (cinza). Se quiser, filtre por etapa de ensino ou use **Buscar turma**, e clique na turma desejada.
3. Dentro da turma, cada aluno aparece com a **Situação** e o indicador de notas: **Notas completas**, **Pendência de notas**, **Avaliação descritiva** ou **Sem grade definida**. Em quem tem pendência, clique em **ver pendência(s)** para saber a disciplina e o período que faltam.
4. Resolva as **pendências** antes de encerrar. O botão **Lançar notas** abre a janela de notas e faltas do aluno, por disciplina e período; cada campo é salvo automaticamente ao sair dele.
5. Para os alunos elegíveis, marque a coluna **Apr. Conselho** quando houver aprovação por conselho de classe.
6. Clique em **Encerrar turma** e confirme. O sistema grava a situação final de todos os alunos ativos.
7. Com a turma fechada, o botão **Ata Final** emite a ata do encerramento. Se precisar reabrir, use **Cancelar encerramento** (turma inteira) ou **Reabrir** (um aluno).

## Como a situação final é calculada

- **Nota**: o aluno precisa atingir a média mínima em todas as disciplinas. Se a escola tem uma média própria cadastrada, ela vale no lugar da média geral do ano letivo.
- **Recuperação final**: na janela de notas, a coluna **Rec. Final** (notas numéricas) vale como média final da disciplina quando é maior que a média obtida.
- **Frequência**: o aluno precisa atingir a frequência mínima do ano letivo.
- **Resultado**: aprovado; reprovado por nota; reprovado por falta; ou reprovado por nota e falta. Quem foi marcado em **Apr. Conselho** fica como aprovado pelo conselho.
- Em séries com avaliação descritiva (sem nota) ou com progressão automática, todos os alunos ativos ficam aprovados.

## Avisos comuns

- O botão **Encerrar turma** só é liberado quando **todos** os alunos estão com as notas completas (ou a série é de avaliação descritiva). A tela informa quantos alunos ainda estão pendentes.
- **Média e frequência mínimas não configuradas**: a turma mostra um aviso e não pode ser encerrada até que a média mínima e a frequência mínima de aprovação estejam definidas (Parâmetros → Ano Letivo, ou Média por Escola).
- O **conselho de classe** só fica disponível para alunos que já estão completos e que **não atingiriam** a média ou a frequência sozinhos.
- **Limite do conselho**: se o ano letivo define um número máximo de matérias que o conselho pode aprovar e o aluno marcado tem mais reprovações em nota do que isso, o encerramento é recusado e a tela mostra o nome do aluno e o motivo.
- **Cancelar o encerramento** limpa a situação final e reabre a turma. Se já houver renovação criada a partir dessas matrículas, ela é **removida** junto. **Reabrir** um aluno faz o mesmo apenas para ele, e a turma também volta a ficar aberta.
- Só aparecem turmas regulares; turmas de AEE e de atividades não passam por esta tela.
