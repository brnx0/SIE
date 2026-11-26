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

- **0 — Sem acesso**: o perfil não vê nem abre a tela.
- **1 — Ver + Editar**: o perfil pode consultar e alterar.
- **2 — Só visualizar**: o perfil abre a tela, mas só consulta (não consegue gravar alterações).

## Como acessar

Menu **Sistema → Permissões de Telas** (endereço `/permissoes`). Por padrão, apenas o administrador tem acesso.

## Passo a passo

1. Localize a **tela** na lista (as telas são agrupadas por área, como no menu).
2. Na coluna de cada **perfil**, escolha o nível **0**, **1** ou **2**.
3. Para aplicar o mesmo nível a **todas as telas** de um perfil, use o campo **aplicar…** logo abaixo do nome do perfil.
4. Repita para as demais telas e clique em **Salvar**.

## Avisos comuns

- Definir **0 — Sem acesso** esconde a tela do menu daquele perfil, além de bloquear o acesso.
- Definir **2 — Só visualizar** deixa a tela **somente leitura** — bom para permitir consulta sem risco de alteração.
- O **administrador** sempre tem acesso **total** a todas as telas; a coluna dele não pode ser alterada.
- As alterações passam a valer assim que salvas; os usuários daquele perfil sentem o efeito no próximo acesso à tela.
