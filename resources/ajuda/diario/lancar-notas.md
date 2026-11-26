---
titulo: Lançar Notas
secao: Diário Online
ordem_secao: 4
ordem: 5
resumo: Como criar avaliações, distribuir os valores e lançar notas numéricas ou conceituais dos alunos.
slug: lancar-notas
---

## O que é

Nesta área você cria as **avaliações** da disciplina no período e lança as **notas** dos alunos. As notas podem ser **numéricas** ou **conceituais**, conforme o que a série da turma permite. Recuperação é lançada à parte.

## Como acessar

Dentro do **Diário de Classe** (`/diario`), após selecionar turma, disciplina e período, clique em **Avaliação Numérica** ou em **Avaliação Conceitual**, no grupo **Lançamentos**. Só aparecem os botões dos tipos de avaliação que a série da turma permite.

## Passo a passo

1. Clique em **Nova avaliação**.
2. Escolha o **Instrumento** (prova, trabalho, etc.), informe a **Data** e, se quiser, uma **Descrição** (opcional). A data precisa estar dentro do período selecionado.
3. Informe o **Valor** da avaliação (quanto ela vale, com até uma casa decimal). A soma dos valores das avaliações regulares do período não pode passar de **10** — a tela mostra quanto ainda está disponível.
4. Clique em **Salvar**. A avaliação passa a ser uma coluna na tabela de alunos.
5. Digite a **nota** de cada aluno. A gravação é automática. A nota não pode ser maior que o valor da avaliação.
6. Para recuperação, use um instrumento marcado como **recuperação**; ele vale até 10 e fica fora da soma dos 10 pontos.
7. Para corrigir ou apagar uma avaliação, use os ícones de **Editar** e **Excluir** no topo da coluna. Excluir apaga também todas as notas lançadas nela.

## Campos principais

- **Instrumento** — define o tipo da avaliação e se é de recuperação (obrigatório).
- **Descrição** — complemento opcional para identificar a avaliação.
- **Data** — obrigatória, dentro do período (incluindo a extensão).
- **Valor** — quanto a avaliação vale (o teto do conjunto é 10).
- **Nota do aluno** — numérica ou conceito, dependendo da série.
- **Média** — soma das notas das avaliações regulares do período, sem arredondar. A nota de **recuperação** substitui a média quando for maior. O arredondamento para 0,5 só acontece na média final do ano.

## Avaliação conceitual

A forma de lançar o conceito depende de como o **ano letivo** foi configurado; o modo em uso aparece no topo da tela.

- **Modo numérico (faixa)** — você informa o valor da avaliação e digita a nota de cada aluno, como na numérica. O sistema soma as notas e mostra o **conceito** correspondente à faixa. Avaliação sem valor fica bloqueada até você definir o valor (aviso **"defina o valor"**).
- **Modo conceito direto** — a avaliação não tem valor; você escolhe o **conceito** de cada aluno na lista. O conceito da unidade é o que tiver **maioria** entre as avaliações; sem maioria, vale a média dos conceitos arredondada para cima. A mesma regra é usada no fechamento do ano, considerando os conceitos das unidades.
- Nos dois modos, a **recuperação** fica fora do cálculo e só substitui o resultado se for melhor.

## Parametrização da unidade

A coordenação pode definir, em **Pedagógico → Cadastro → Parametrização de Avaliações**, quais instrumentos cada unidade terá e o valor máximo de cada um (vale para toda a rede).

- Com a unidade parametrizada, a lista de instrumentos mostra só os definidos e o **valor já vem preenchido** ao escolher o instrumento.
- Se os valores estiverem **bloqueados**, o campo Valor fica travado no valor definido pela coordenação.
- Avaliações criadas antes da parametrização continuam editáveis com o instrumento que já tinham.
- A soma das avaliações regulares continua limitada a 10.

## Avisos comuns

- **"A soma dos valores das avaliações não pode passar de 10."** — reduza o valor da nova avaliação ou ajuste as existentes.
- **Não é possível lançar nota em avaliação com data futura.** Ela aparece como **agendada** e libera o lançamento a partir da data.
- **"A data da avaliação deve estar dentro do período selecionado."** — escolha uma data entre o início e o fim do período (com a extensão).
- **"Instrumento não parametrizado para esta unidade."** — escolha um dos instrumentos definidos pela coordenação.
- Um aluno **não pode ter notas numéricas e conceituais** na mesma disciplina; remova um tipo antes de lançar o outro.
- Aluno que **saiu da turma** fica bloqueado nas avaliações com data posterior à saída.
- Só é possível lançar com a **turma aberta** e dentro do **período** da unidade.
- **A nota do professor prevalece**: onde você já lançou notas no diário, a secretaria não consegue sobrepor a média (ela só lança as faltas). Se já existia uma média lançada manualmente pela secretaria, ela deixa de valer quando você lança ou altera a nota, e volta a contar a média calculada pelo diário.
