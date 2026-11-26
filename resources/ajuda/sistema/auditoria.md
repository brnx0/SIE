---
titulo: Auditoria
secao: Sistema
ordem_secao: 9
ordem: 4
resumo: Trilha de quem alterou o quê e quando no sistema.
slug: auditoria
---

## O que é

A **Auditoria** é a trilha de registro das alterações feitas no sistema. Para cada ação ela mostra **quem** fez, **quando**, **qual ação** (criou, alterou, excluiu ou restaurou), sobre **qual entidade** e **o que mudou** (valor anterior e novo).

## Como acessar

Menu **Sistema → Auditoria** (endereço `/auditoria`). Por padrão, apenas o administrador tem acesso; a liberação para outros perfis é feita em **Permissões de Telas**.

## Passo a passo

1. Use os filtros para reduzir a lista: **usuário**, **entidade**, **ação**, **período** (de/até) e **busca** por texto (usuário, entidade ou ação).
2. Percorra os registros, do mais recente para o mais antigo.
3. Clique em um registro para ver os **campos alterados**, com o valor **de** e **para**. A coluna **Alterações** indica quantos campos mudaram.

## Avisos comuns

- Ações feitas por rotinas do próprio sistema aparecem com o autor **Sistema**.
- Os rótulos das ações são: **Criou**, **Alterou**, **Excluiu** e **Restaurou**.
- A auditoria é **somente consulta** — serve para acompanhar e investigar mudanças, não para desfazê-las.
