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

Menu **Movimentações → Movimentar Aluno** (endereço `/movimentacoes`). Use **Nova Movimentação** para registrar uma nova.

## Passo a passo

1. Escolha o **Tipo de Movimentação**. Uma explicação do tipo selecionado aparece logo abaixo.
2. Informe a **Data** e, se houver, o **Protocolo nº**.
3. No bloco **Origem**, selecione em cascata: **Ano Letivo → Escola → Etapa de Ensino → Série → Turma → Aluno**.
4. Se o tipo exigir, preencha o bloco **Destino** (etapa de ensino, série e turma). A turma atual do aluno não aparece na lista de turmas de destino. No remanejamento, a etapa de ensino e a série ficam travadas iguais às da origem — só a turma muda.
5. Confira o **Resumo da Movimentação** e clique em **Salvar**. O sistema abre a tela do registro salvo.

## Campos principais

- **Tipo de Movimentação**: define se há destino e qual regra se aplica.
- **Origem / Destino**: turma de saída e, quando aplicável, turma de entrada.
- **Ano escolar do aluno na turma multi**: aparece quando a turma de destino é multisseriada e é obrigatório — informe em que ano escolar o aluno fica.
- **Transferir notas / Transferir faltas** (no remanejamento): copia o diário para a turma nova; o histórico permanece na origem.
- **Observação**: texto livre.

## Declaração de transferência

Para movimentações de transferência, a tela do registro (abrir a movimentação na lista) traz o botão **Declaração de Transferência**, que gera o documento em PDF em nova aba. O botão não aparece em movimentações já desfeitas.

## Desfazer movimentação

Na tela do registro, use **Desfazer Movimentação** e informe o **Motivo**, que é **obrigatório** (mínimo de 3 caracteres) — o botão **Confirmar** só fica disponível depois de preenchido. Ao confirmar, a matrícula de origem é **reativada** e a de destino (se houver) é **removida**. O registro fica marcado como **DESFEITA**, com data, usuário e motivo.

## Avisos comuns

- **Reclassificação**: a série de destino deve ser **diferente** da série de origem.
- **Remanejamento**: destino é a mesma série, em outra turma da mesma escola.
- **Turma de destino igual à atual**: não é permitido; por isso a turma em que o aluno já está não é listada no destino.
- **Sem vaga no destino**: se a turma de destino estiver lotada, a movimentação (ou o desfazer que reativa a matrícula) pode ser bloqueada — o motivo aparece na tela.
- **Matrículas de AEE e de atividades**: nas movimentações de saída (sem destino), as matrículas ativas do aluno em turmas de AEE e de atividades são encerradas junto e aparecem listadas na tela do registro. Ao desfazer, elas voltam a ficar ativas.
- **Movimentação já desfeita**: não é possível desfazer duas vezes.
