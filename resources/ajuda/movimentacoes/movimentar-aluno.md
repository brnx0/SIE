---
titulo: Movimentar Aluno
secao: Movimentações
ordem_secao: 3
ordem: 1
resumo: Registre transferências, remanejamentos e reclassificações, com declaração e opção de desfazer.
slug: movimentar-aluno
---

## O que é

Tela para registrar a movimentação de um aluno: transferência (saída), remanejamento entre turmas, reclassificação de série e demais tipos configurados. Cada movimentação encerra a matrícula de origem e, quando o tipo exige, cria a matrícula de destino.

## Como acessar

Menu **Movimentações**. Use **Nova Movimentação** para registrar uma nova.

## Passo a passo

1. Escolha o **Tipo de Movimentação**. Uma explicação do tipo selecionado aparece logo abaixo.
2. Informe a **Data** e, se houver, o **Protocolo nº**.
3. No bloco **Origem**, selecione em cascata: **Ano Letivo → Escola → Etapa de Ensino → Série → Turma → Aluno**.
4. Se o tipo exigir, preencha o bloco **Destino** (etapa de ensino, série e turma).
5. Confira o **Resumo da Movimentação** e clique em **Salvar**.

## Campos principais

- **Tipo de Movimentação**: define se há destino e qual regra se aplica.
- **Origem / Destino**: turma de saída e, quando aplicável, turma de entrada.
- **Transferir notas / Transferir faltas** (no remanejamento): copia o diário para a turma nova; o histórico permanece na origem.
- **Observação**: texto livre.

## Declaração de transferência

Para movimentações de transferência, a tela do registro (abrir a movimentação na lista) traz o botão **Declaração de Transferência**, que gera o documento em PDF em nova aba.

## Desfazer movimentação

Na tela do registro, use **Desfazer Movimentação** e informe um **Motivo** (opcional). Ao confirmar, a matrícula de origem é **reativada** e a de destino (se houver) é **removida**. O registro fica marcado como **DESFEITA**, com data, usuário e motivo.

## Avisos comuns

- **Reclassificação**: a série de destino deve ser **diferente** da série de origem.
- **Remanejamento**: destino é a mesma série, em outra turma da mesma escola.
- **Sem vaga no destino**: se a turma de destino estiver lotada, a movimentação (ou o desfazer que reativa a matrícula) pode ser bloqueada — o motivo aparece na tela.
- **Movimentação já desfeita**: não é possível desfazer duas vezes.
