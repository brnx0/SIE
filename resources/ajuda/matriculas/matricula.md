---
titulo: Realizar Matrícula
secao: Matrículas
ordem_secao: 2
ordem: 1
resumo: Localize uma turma com vaga e matricule o aluno, novo ou já cadastrado.
slug: matricula
---

## O que é

Tela para matricular um aluno em uma turma regular. Você primeiro localiza a turma disponível pelos filtros e, em seguida, matricula um aluno já cadastrado na base ou informa um aluno novo.

## Como acessar

Menu **Matrículas → Matrículas** (endereço `/matriculas`).

## Passo a passo

1. Preencha os filtros **Ano Letivo**, **Escola**, **Etapa de Ensino**, **Série / Ano Escolar** e **Semestre**. Só depois de todos preenchidos a lista de turmas aparece.
2. No campo **Aluno a ser matriculado**, busque por nome, matrícula ou CPF e selecione o aluno. Se o aluno ainda não existe no sistema, marque **Aluno não cadastrado na base** — os dados serão informados na próxima etapa.
3. Na lista **Turmas disponíveis**, confira as **Vagas disp.** e clique em **Matricular** na turma escolhida.
4. Na janela de matrícula, confira ou preencha os dados do aluno na aba **Dados** e, se quiser, a **Observação**.
5. Clique em **Finalizar Matrícula**. A data da matrícula é registrada automaticamente com a data do dia, e o comprovante abre em uma nova aba.

## Campos principais

- **Semestre, Situação, Série / Ano Escolar, Turma, Turno, Matriculados**: identificação da turma.
- **Vagas disp.**: fica em vermelho quando não há vagas (botão bloqueado).
- **Nome, CPF, NIS, Filiação, Sexo, Data de Nascimento, Cor / Raça, Município de Nascimento, endereço e contato**: dados do aluno. São obrigatórios: **Nome**, **Filiação 1** (indicando se é mãe ou pai), **Sexo**, **Data de Nascimento**, **Cor / Raça**, **Município de Nascimento** e **CEP**. O **CPF** é obrigatório apenas quando o sistema está configurado para exigi-lo.
- **Possui alguma deficiência**: libera a aba **Quadro de Saúde**, onde é preciso marcar ao menos um item (deficiência, transtorno ou altas habilidades).
- **Documentos / Programas** (**Trouxe Transferência**, **Bolsa Família**, **Recebe Merenda**, **Usa Transporte** e demais marcações): ficam guardadas no cadastro do aluno, não na matrícula.
- **Série de origem do aluno (turma multi)**: aparece apenas em turmas multisseriadas e é obrigatória.

## Avisos comuns

- **Aluno não selecionado**: antes de clicar em **Matricular**, selecione um aluno ou marque **Aluno não cadastrado na base**.
- **Idade × série**: esta verificação só acontece quando a validação de idade por série está ativada nos parâmetros do sistema. Nesse caso, se a série exige uma idade mínima, o sistema calcula a idade do aluno na data de corte do ano letivo e bloqueia a matrícula quando ele ainda não completou a idade exigida.
- **Homônimos**: ao cadastrar um aluno novo sem CPF, com nome e nascimento iguais aos de outro já existente, o sistema pergunta se realmente é uma pessoa diferente antes de gravar.
- **Matrícula duplicada**: o aluno não pode ter duas matrículas ativas no mesmo ano letivo; o sistema avisa se ele já estiver matriculado nesta ou em outra escola.
- **Turma sem vagas**: o botão Matricular fica desabilitado.
- **Campo obrigatório em branco**: ao finalizar, o sistema leva você até o primeiro campo com problema.
