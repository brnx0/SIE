---
titulo: Parâmetros do Sistema
secao: Sistema
ordem_secao: 9
ordem: 2
resumo: Configurações gerais que definem o comportamento do sistema para toda a entidade.
slug: parametros
---

## O que é

Os **Parâmetros do Sistema** reúnem as configurações gerais da entidade (rede/município). Aqui ficam a identidade (nome, logomarca, brasão), os anos letivos e seus períodos, as médias e conceitos, a grade de horários e uma série de **regras** que ligam ou desligam comportamentos em todo o sistema.

A tela é organizada em abas: **1. Entidade**, **2. Ano Letivo**, **3. Unidade**, **4. Dias Não Letivos**, **5. Diário**, **6. Cadastros** e **7. Grade de Horários**.

## Como acessar

Menu **Sistema → Parâmetros** (endereço `/parametros`). Por padrão, apenas o administrador tem acesso; a liberação para outros perfis é feita em **Permissões de Telas**.

## Passo a passo

1. Na aba **Entidade**, ajuste os dados de identidade (nome da entidade, mensagens de cabeçalho dos relatórios, endereço e cidade) e envie a **logomarca** e o **brasão** se desejar (JPG, PNG, WEBP ou SVG, até 5 MB). Na mesma aba, em **Textos das Atas**, escreva o texto da **Ata de Resultados Finais**. Clique em **Salvar alterações**.
2. Na aba **Ano Letivo**, cadastre ou edite os **anos letivos** (datas de início, fim e corte, frequência mínima, média geral, forma de lançamento do conceito, ano em exercício).
3. Nas abas **Unidade** e **Dias Não Letivos**, configure os **períodos** de cada ano letivo e os dias sem aula.
4. Na aba **Diário**, defina os **conceitos**, as **situações que bloqueiam registros**, os **dias letivos** e a **média por escola**.
5. Na aba **Cadastros**, ative ou desative as regras gerais conforme a política da rede e clique em **Salvar alterações**.
6. Na aba **Grade de Horários**, cadastre os horários de aula por **etapa de ensino** e turno.

## Regras mais comuns

- **Tornar CPF do aluno obrigatório** — exige CPF no cadastro do aluno.
- **Validar a Idade / Série do aluno** — bloqueia matrícula quando o aluno não tem a idade mínima da série.
- **Gerar o número de matrícula automático** — cria o número de matrícula automaticamente.
- **Alertar Homônimos** e **Alertar sobre acentos em nomes** — exibem avisos ao digitar nomes.
- **Cadastro do nome da pessoa / da escola em Caixa alta** — grava os nomes em letras maiúsculas.
- **Permitir replicar o conteúdo do diário para outras turmas** — libera, no diário, a cópia do conteúdo lançado para outras turmas.

## Avisos comuns

- As mudanças valem para **toda a entidade** — confirme a política antes de salvar.
- O botão **Salvar alterações** existe nas abas **Entidade** e **Cadastros**. Nas demais abas, cada item (ano letivo, período, conceito, horário etc.) é gravado no próprio cadastro.
- A **logomarca** aparece na **tela de login** do sistema (se não houver logomarca, é usado o brasão).
- O texto da **Ata de Resultados Finais** é um bloco fixo, sem dados do aluno, impresso na Ata Final abaixo do quadro de notas. Se ficar em branco, a ata é emitida sem esse texto.
- A forma de lançamento do conceito (por nota numérica convertida pela faixa ou por conceito direto) é definida em cada **ano letivo**.
