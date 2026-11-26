---
titulo: Lançamento de Notas e Faltas
secao: Secretaria Escolar
ordem_secao: 7
ordem: 2
resumo: A secretaria lança ou ajusta manualmente as médias e faltas dos alunos por disciplina e unidade.
slug: lancamento-notas-faltas
---

## O que é

A tela **Notas e Faltas** permite à secretaria registrar manualmente a **média** e as **faltas** de cada aluno, por disciplina e por unidade (bimestre/trimestre). É útil para escolas que fecham as notas fora do sistema. Onde o professor já lançou as notas no diário, a tela **mostra a média calculada** do diário — ela prevalece e **não pode ser sobreposta** pela secretaria.

## Como acessar

Menu **Secretaria Escolar → Lançamentos → Notas e Faltas** (endereço `/secretaria/lancamento-manual`).

A secretaria opera apenas as escolas em que está lotada; o administrador, todas. O item só aparece no menu para os perfis liberados em **Permissões de Telas**.

## Passo a passo

1. Selecione **Ano Letivo**, **Escola** e **Turma**.
2. Escolha a **disciplina** (opcional — em branco, a tela lista todas, uma abaixo da outra).
3. A grade mostra os **alunos nas linhas** e as **unidades nas colunas**, cada uma com **Média** (ou **Conceito**) e **Faltas**. As células com nota completa no diário já vêm com a **média calculada**, em itálico e com borda tracejada.
4. Digite a **média** e as **faltas** onde precisar. Nas células com **nota lançada pelo professor**, a média fica bloqueada — só as **faltas** podem ser lançadas.
5. Lance a **recuperação final** (coluna **Rec. Final**) por aluno/disciplina quando aplicável. Os valores são salvos automaticamente.
6. Acompanhe a coluna **Média Final**, calculada quando todas as unidades estão preenchidas.

## Avisos comuns

- A média vai de **0 a 10**. Séries com avaliação conceitual usam **conceito** no lugar do número. Quando a série permite os dois tipos, escolha em **Lançar: Numérica / Conceitual**.
- Um aluno **não pode** ter lançamento numérico e conceitual na mesma disciplina ao mesmo tempo — a linha dele aparece bloqueada com o aviso do tipo já lançado.
- A **recuperação final** só existe no lançamento numérico e vale como média final apenas se for **maior** que a média obtida.
- A **média final** é arredondada ao 0,5 mais próximo.
- Para **remover um lançamento manual**, apague os valores da célula (limpar média, conceito e faltas remove o lançamento).
- Com várias disciplinas na tela, use **Recolher todas / Expandir todas** ou clique no nome da disciplina para abrir e fechar. O botão **Atualizar** recarrega a grade.
