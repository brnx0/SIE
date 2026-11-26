---
titulo: Acessos de Professores
secao: Secretaria Escolar
ordem_secao: 7
ordem: 1
resumo: Gerar o login de acesso ao sistema para os professores da escola.
slug: acessos-professores
---

## O que é

A tela de **Acessos de Professores** permite à secretaria **gerar o login** de acesso ao sistema para os professores lotados na escola, sem precisar cadastrar cada usuário manualmente. O sistema já sugere o perfil (professor ou professor de AEE) conforme a lotação de cada um.

## Como acessar

Menu **Secretaria Escolar → Acessos de Professores** (endereço `/secretaria/acessos-professores`).

A secretaria vê apenas os professores das escolas em que está lotada; o administrador e o gestor da secretaria veem todos. O item só aparece no menu para os perfis liberados em **Permissões de Telas**.

## Passo a passo

1. Use a **busca** (nome ou CPF), o campo de **escola** (digite para localizar a escola ou deixe em "Todas as escolas") e o filtro de **situação** (Sem login, Com login, Não lotado) para localizar os professores.
2. Confira as colunas **Perfil sugerido** e **Situação**. Só é possível gerar acesso para quem está como **Pronto para gerar**.
3. Para gerar o acesso de **um** professor, clique no botão **Criar** na linha dele.
4. Para gerar **vários de uma vez**, marque a caixa de seleção de cada professor (ou a caixa do cabeçalho, que marca todos os aptos da página) e clique em **Gerar selecionados**.
5. Confirme a mensagem. Para cada professor apto, o sistema cria o login usando o **CPF** (o CPF também é a senha inicial).
6. Oriente o professor a fazer o primeiro acesso e trocar a senha.

## Avisos comuns

- Um professor é **ignorado** e o motivo é informado quando: já possui login, não está lotado, está sem CPF cadastrado, ou o CPF já é usado como login de outra pessoa. Nesses casos a caixa de seleção fica bloqueada e o botão **Criar** não aparece.
- O **perfil sugerido** vem da função da lotação: função "Docente AEE" gera perfil de professor de AEE; as demais funções docentes geram perfil de professor.
- Se um professor não aparecer, confira a lotação dele com o cadastro de funcionários.
- Se o seu usuário não estiver vinculado a um funcionário com lotação ativa, a tela avisa que não é possível listar professores — procure o administrador.
- É possível exportar a lista em CSV ou PDF.
