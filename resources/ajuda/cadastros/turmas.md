---
titulo: Turmas
secao: Cadastros
ordem_secao: 1
ordem: 8
resumo: Crie turmas regulares, aloque docentes e disciplinas e monte o quadro de horário.
slug: turmas
---

## O que é

Turma é o agrupamento de alunos de uma série, escola e turno. Nesta tela você cria a turma, aloca docentes por disciplina, define docentes de apoio e monta o quadro de horário.

## Como acessar

Menu **Cadastros → Rede de Ensino → Turmas** (endereço `/turmas`).

Na lista, cada turma tem os ícones de ação:

- **Olho (Visualizar)** — abre a turma em modo consulta (somente leitura). Quem tem permissão de edição vê o botão **Editar** no topo para passar a alterar.
- **Lápis (Editar)** — abre a turma para alteração.
- **Lixeira (Remover)** — exclui a turma.

O lápis e a lixeira só aparecem para quem tem permissão de edição.

## Passo a passo

1. Clique em **Nova Turma** e preencha os dados gerais: escola, ano letivo, etapa de ensino, série, turno, semestre, nome, capacidade e situação.
2. Clique em **Cadastrar Turma** (botão no topo). No modo de edição aparecem as abas **Dados Gerais**, **Docentes**, **Horários** e **Alunos**.
3. Em **Docentes**, clique em **Adicionar Docente** e informe docente + disciplina.
4. Ainda em **Docentes**, na parte **Docentes de Apoio**, clique em **Adicionar Docente de Apoio** para incluir apoios, se houver.
5. Em **Horários**, monte a grade de horário por dia e tempo, atribuindo docente e disciplina.
6. A aba **Alunos** mostra os matriculados.

## Campos principais

- **Escola, Ano Letivo, Etapa de Ensino, Série, Turno, Semestre, Nome da Turma, Capacidade, Situação** — obrigatórios.
- **Nome da Turma** — até 20 caracteres; aceita apenas letras sem acento, números, hífen e espaços.
- **Situação** — ABERTA (em uso) ou ENCERRADA.
- **Dias de Funcionamento e Horários** — definem quando a turma funciona.

## Avisos comuns

- O **Nome da Turma** não pode repetir na mesma escola, ano, série e turno.
- Depois de **ABERTA**, os campos estruturais (escola, ano, etapa de ensino, série, semestre) ficam bloqueados.
- Turma **ENCERRADA** fica somente leitura.
- **Excluir x Inativar:** turma com docentes, docentes de apoio, horários ou alunos vinculados não pode ser excluída — o sistema bloqueia e avisa. Mude a **Situação** para ENCERRADA.
