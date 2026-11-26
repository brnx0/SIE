---
titulo: Séries
secao: Cadastros
ordem_secao: 1
ordem: 4
resumo: Cadastre as séries/anos de cada etapa de ensino, tipo de avaliação, idade e indicadores de diagnóstico.
slug: series
---

## O que é

Série é o ano escolar dentro de uma etapa de ensino (ex.: 1º Ano, 2º Ano). Aqui você define o tipo de avaliação, a idade esperada, as regras de promoção e os indicadores usados na avaliação diagnóstica.

## Como acessar

Menu **Cadastros → Rede de Ensino → Séries** (endereço `/series`).

Na lista, cada série tem os ícones de ação:

- **Olho (Visualizar)** — abre o cadastro em modo consulta (somente leitura). Quem tem permissão de edição vê o botão **Editar** no topo para passar a alterar.
- **Lápis (Editar)** — abre o cadastro para alteração.
- **Lixeira (Remover)** — exclui a série.

O lápis e a lixeira só aparecem para quem tem permissão de edição.

## Passo a passo

1. Clique em **Nova série** e escolha a **Etapa de Ensino**.
2. Informe o **Nome da Série** e a **Ordem da Série na Etapa de Ensino**.
3. Marque o(s) **Tipo(s) de Avaliação** (por Diagnóstico, Conceitual, Numérica, Descritiva).
4. Preencha a **Idade na Série** e, se quiser, as opções de promoção/conservação.
5. Clique em **Cadastrar série** (botão no topo). No modo de edição, a seção **Indicadores**, abaixo do formulário, permite cadastrar os indicadores de diagnóstico por ano letivo.

## Campos principais

- **Etapa de Ensino, Nome da Série, Idade na Série, Ordem da Série na Etapa de Ensino** — obrigatórios.
- **Tipo de Avaliação Descritiva** — obrigatório quando "Avaliação Descritiva" está marcada (Por Aluno ou Por Disciplina).
- **Ativo** — controla se a série aparece nas seleções.
- **Indicadores** — vinculados a um ano letivo e a uma disciplina da grade; podem ser replicados de outra série ou de outro ano.

## Avisos comuns

- O **Nome da Série** e a **Ordem da Série na Etapa de Ensino** não podem repetir dentro da mesma etapa de ensino.
- A **Idade na Série** é usada para validar se o aluno tem idade compatível ao matricular (quando essa validação está ligada nos parâmetros).
- **Creche/Pré-escola** não permitem opções de conservação.
- Para adicionar **Indicadores**, a série precisa ter a grade disciplinar cadastrada.
- **Excluir x Inativar:** série já usada em turmas/matrículas não pode ser excluída — o sistema bloqueia e orienta a inativar. Use o campo **Ativo**.
