---
titulo: Etapas de Ensino
secao: Cadastros
ordem_secao: 1
ordem: 3
resumo: Cadastre as etapas de ensino (Creche, Fundamental, EJA...) e a quantidade de anos escolares.
slug: segmentos
---

## O que é

Etapa de ensino é a grande divisão do ensino oferecido — por exemplo Creche, Pré-escola, Ensino Fundamental, EJA. Cada etapa de ensino define quantos anos escolares possui e serve de base para séries, turmas e escolas.

## Como acessar

Menu **Cadastros → Rede de Ensino → Etapas de Ensino** (endereço `/segmentos`).

Na lista, cada etapa de ensino tem os ícones de ação:

- **Olho (Visualizar)** — abre o cadastro em modo consulta (somente leitura). Quem tem permissão de edição vê o botão **Editar** no topo para passar a alterar.
- **Lápis (Editar)** — abre o cadastro para alteração.
- **Lixeira (Remover)** — exclui a etapa de ensino.

O lápis e a lixeira só aparecem para quem tem permissão de edição.

## Passo a passo

1. Clique em **Nova etapa de ensino**.
2. Informe o **Nome Reduzido** e o **Nome Completo**.
3. Preencha a **Qtd. de Anos Escolares** e a **Ordem** (posição na lista).
4. Informe a **Data da Abertura** (e de Encerramento, se houver).
5. Se a etapa de ensino exigir pré-requisito de ingresso, ative a opção e descreva.
6. Clique em **Cadastrar etapa de ensino** (botão no topo).

## Campos principais

- **Nome Reduzido / Nome Completo** — obrigatórios e únicos.
- **Qtd. de Anos Escolares** — obrigatória, de 1 a 99.
- **Ordem** — obrigatória; controla a ordem de exibição.
- **Data da Abertura** — obrigatória.
- **Código INEP** — opcional; quando informado, não pode repetir em outra etapa de ensino.
- **Pré-requisito para ingressão** — quando ativo, a descrição do pré-requisito é obrigatória.
- **Ativo** — controla se a etapa de ensino aparece nas seleções.

## Avisos comuns

- A **Data de Encerramento** não pode ser anterior à de Abertura.
- **Excluir x Inativar:** se a etapa de ensino já estiver vinculada a séries/escolas/turmas, não será possível excluí-la — o sistema bloqueia e orienta a inativar. Desative o campo **Ativo**.
