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

Menu **Secretaria → Acessos de Professores** (endereço `/secretaria/acessos-professores`).

A secretaria vê apenas os professores das escolas em que está lotada; o administrador vê todos.

## Passo a passo

1. Use os filtros de **escola** e **situação** e a **busca** (nome ou CPF) para localizar os professores.
2. Selecione um ou mais professores que estejam prontos para gerar.
3. Clique em **gerar acesso**. Para cada professor apto, o sistema cria o login usando o **CPF** (o CPF também é a senha inicial).
4. Oriente o professor a fazer o primeiro acesso e trocar a senha.

## Avisos comuns

- Um professor é **ignorado** e o motivo é informado quando: já possui login, não está lotado, está sem CPF cadastrado, ou o CPF já é usado como login de outra pessoa.
- O **perfil sugerido** vem da função da lotação: função "Docente AEE" gera perfil de professor de AEE; as demais funções docentes geram perfil de professor.
- Se um professor não aparecer, confira a lotação dele com o cadastro de funcionários.
- É possível exportar a lista em CSV ou PDF.
