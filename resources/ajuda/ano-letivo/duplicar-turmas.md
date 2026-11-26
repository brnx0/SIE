---
titulo: Duplicar Turmas
secao: Ano Letivo
ordem_secao: 8
ordem: 2
resumo: Recria as turmas encerradas para o ano seguinte, sem precisar cadastrá-las de novo.
slug: duplicar-turmas
---

## O que é

O **Duplicar Turmas** recria, para o **ano seguinte**, as turmas que foram **encerradas** no ano selecionado. Assim você não precisa cadastrar cada turma manualmente. Cada turma só pode ser duplicada **uma vez**.

## Como acessar

Menu **Ano Letivo → Duplicar Turmas** (endereço `/duplicar-turmas`).

A secretaria opera apenas as escolas em que está lotada; o administrador, todas.

## Passo a passo

1. Selecione o **Ano Letivo (origem)** e a **Escola** e clique em **Listar Turmas**.
2. A tela lista as turmas **encerradas** da escola, indicando quais estão **Disponíveis** e quais estão **Já duplicadas**. Use **Buscar turma** para localizar uma turma.
3. Clique em **Duplicar** em uma turma, ou use **Duplicar todas** para copiar de uma vez as ainda não duplicadas, e confirme.
4. As novas turmas são criadas no ano seguinte, abertas e prontas para receber matrículas/renovações.

## Avisos comuns

- Só aparecem turmas regulares com situação **encerrada**. Encerre a turma antes de duplicar.
- Uma turma **já duplicada** não é copiada de novo.
- A cópia leva os dados da turma (nome, etapa de ensino, série, turno, capacidade, horário). Os alunos não são copiados: eles entram no ano seguinte pela **Confirmação de Renovação** ou por nova matrícula.
- Se o **ano letivo seguinte ainda não existir**, ele é **criado automaticamente** ao duplicar, copiando a configuração e os períodos do ano atual com as datas deslocadas em um ano. Revise-o depois em Parâmetros → Ano Letivo.
