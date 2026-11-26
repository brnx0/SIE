---
titulo: Quadro de Horário
secao: Diário Online
ordem_secao: 4
ordem: 10
resumo: Como montar a grade de horários da turma, alocando professor e disciplina em cada tempo de aula.
slug: quadro-horario
---

## O que é

O **Quadro de Horário** é a grade de aulas da turma: define **qual professor** dá **qual disciplina** em cada **dia e tempo** de aula. É essa grade que organiza a **chamada** no diário — a frequência é lançada por tempo, e cada professor só lança os tempos onde está alocado.

## Como acessar

O Quadro de Horário **não tem um item próprio no menu**. Você chega nele por dois caminhos:

- **Para consultar** — Menu **Diário Online → Diário de Classe** (endereço `/diario`). Selecione o ano letivo, a escola e a turma e clique em **Quadro de Horário**, no grupo **Consulta**. Aqui a grade é apenas para leitura.
- **Para montar ou alterar** — Menu **Cadastros → Rede de Ensino → Turmas** (endereço `/turmas`). Abra a turma para edição e vá na aba **Horários**.

A tela avulsa do quadro (endereço `/diario/quadro-horario`) continua existindo, mas só abre digitando o endereço. Ela traz a mesma grade de edição da aba **Horários**, limitada às turmas em que você está alocado.

## Passo a passo

### Consultar (no Diário de Classe)

1. Selecione **Ano Letivo**, **Escola** e **Turma**. Não é preciso escolher disciplina nem período.
2. No grupo **Consulta**, clique em **Quadro de Horário**.
3. A grade mostra os **tempos** (linhas) por **dia da semana** (colunas), com a disciplina e o professor de cada tempo. Quando houver **sábado letivo** na semana, ele aparece em uma coluna destacada, repetindo o horário do dia da semana que ele substitui.
4. Use **Atualizar** para recarregar a grade.

### Montar ou alterar (na aba Horários da turma)

1. Abra a turma para edição e clique na aba **Horários**. Cada dia de funcionamento da turma aparece em um quadro, com os tempos do 1º ao 10º.
2. No tempo vago, clique no **+** (Atribuir docente) e informe:
   - o **Docente** (só aparecem os que já estão alocados na turma, na aba **Docentes**);
   - a **Disciplina** (só as que o docente escolhido leciona nessa turma);
   - o **Horário**, se quiser (opcional);
   - **Tempo Compartilhado (TC)**, quando for o caso.
3. Clique em **Salvar**.
4. Para remover uma alocação, clique na **lixeira** daquele tempo e confirme.

## Campos principais

- **Tempo** e **Dia** — a posição na grade.
- **Docente** e **Disciplina** — quem leciona e o quê naquele tempo (obrigatórios).
- **Horário** — hora do tempo de aula (opcional). As opções vêm da grade de horários cadastrada para a etapa de ensino e o turno da turma; um horário já usado no dia não aparece de novo.
- **Tempo Compartilhado (TC)** — marca o tempo como compartilhado; ele aparece com a etiqueta **TC** na grade.

## Avisos comuns

- **"Professor não está alocado nesta turma com a disciplina informada."** — o vínculo professor + disciplina na turma precisa existir antes (aba **Docentes** da turma).
- **"Nenhum docente alocado nesta turma. Aloque na aba Docentes."** — a turma ainda não tem professores vinculados.
- **Conflito de horário**: o sistema bloqueia alocar o mesmo professor no mesmo dia/tempo em outra turma do mesmo turno (uma turma de turno **Integral** conflita com qualquer turno). Com **Tempo Compartilhado (TC)** marcado, o conflito é liberado apenas entre turmas da **mesma escola**.
- **"Este horário já foi utilizado neste dia."** — a mesma hora não pode se repetir em outro tempo do dia.
- Com a turma **encerrada**, a aba **Horários** fica apenas para consulta.
- No Diário de Classe, **"Esta turma não possui dias de funcionamento ou grade de horário configurada."** indica que o cadastro da turma ainda não tem os dias ou a grade definidos.
- Um quadro bem montado é o que permite a **chamada por tempo** funcionar corretamente no Diário de Classe.
