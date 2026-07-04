---
titulo: Permissões de Telas
secao: Sistema
ordem_secao: 9
ordem: 3
resumo: Define, por perfil e por tela, se o acesso é nenhum, somente leitura ou edição.
slug: permissoes-tela
---

## O que é

As **Permissões de Telas** formam uma matriz de **perfil × tela**. Para cada tela do sistema você define o nível de acesso de cada perfil:

- **Nível 0 — Sem acesso**: o perfil não vê nem abre a tela.
- **Nível 1 — Leitura**: o perfil abre a tela, mas só consulta (campos bloqueados).
- **Nível 2 — Edição**: o perfil pode consultar e alterar.

## Como acessar

Menu **Sistema → Permissões** (endereço `/permissoes`). Tela restrita ao administrador.

## Passo a passo

1. Localize a **tela** na lista (as telas são agrupadas por área).
2. Para cada **perfil**, escolha o nível **0**, **1** ou **2**.
3. Repita para as demais telas e **salve**.

## Avisos comuns

- Definir **nível 0** esconde a tela do menu daquele perfil, além de bloquear o acesso.
- Definir **nível 1** deixa a tela **somente leitura** — bom para permitir consulta sem risco de alteração.
- As alterações passam a valer assim que salvas; os usuários daquele perfil sentem o efeito no próximo acesso à tela.
