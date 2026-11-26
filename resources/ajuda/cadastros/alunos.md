---
titulo: Cadastro de Alunos
secao: Cadastros
ordem_secao: 1
ordem: 1
resumo: Registre e mantenha os dados pessoais, documentos, filiação e saúde dos alunos.
slug: alunos
---

## O que é

Tela onde você cadastra e atualiza todas as informações dos alunos da rede. O cadastro fica organizado em abas para separar os tipos de dado.

## Como acessar

Menu **Cadastros → Alunos** (endereço `/alunos`). Clique em **Novo aluno** para cadastrar.

Na lista, cada aluno tem os ícones de ação:

- **Olho (Visualizar)** — abre o cadastro em modo consulta (somente leitura). Quem tem permissão de edição vê o botão **Editar** no topo para passar a alterar.
- **Lápis (Editar)** — abre o cadastro para alteração.
- **Lixeira (Remover)** — exclui o aluno.

O lápis e a lixeira só aparecem para quem tem permissão de edição.

## Passo a passo

1. Clique em **Novo aluno**.
2. Preencha a aba **Dados Pessoais** (nome, nascimento, sexo, cor/raça, país de origem, município de nascimento).
3. Vá para **Documentação** e informe CPF, INEP, certidão, NIS, os documentos apresentados e os programas e serviços.
4. Em **Filiação e Contato**, informe pelo menos a Filiação 1 (marcando se é Mãe ou Pai) e o endereço (o CEP preenche o endereço automaticamente).
5. Em **Saúde e Acessibilidade**, registre contato de emergência e, se for o caso, deficiência/TGD/altas habilidades.
6. Clique em **Cadastrar aluno** (botão no topo; na edição o botão é **Salvar alterações**). Depois de salvar, a aba **Turmas** mostra o histórico de matrículas.

## Campos principais

- **Nome completo, Data de nascimento, Sexo, Cor/Raça, País de origem, Município de nascimento** — obrigatórios. Se a Cor/Raça for Indígena, o **Povo indígena** também é obrigatório.
- **Filiação 1** (com a indicação Mãe ou Pai), **CEP** e **Localização/Zona de residência** — obrigatórios.
- **Matrícula** — não é digitada: o número é gerado pelo sistema ao salvar (quando a geração automática está ligada nos parâmetros). Se o aluno ficou sem número, use o botão **Gerar matrícula** na aba Documentação. Depois de gerado, o número não pode ser alterado.
- **CPF** — pode ser obrigatório dependendo dos parâmetros do sistema.
- **Aluno com Deficiência (PCD), TGD ou Altas Habilidades** — ao ativar, é obrigatório marcar ao menos um item do quadro (deficiência, transtorno, altas habilidades ou outro).
- **Foto do aluno** — JPG, PNG ou WEBP de até 5 MB. Clique na foto para ampliá-la.
- **Aba Turmas** — histórico de matrículas separado em Regulares, Atividades e AEE. A coluna **Resultado Final** mostra "Em andamento" enquanto o aluno está cursando e, depois do encerramento, o resultado obtido na turma; transferências e outras saídas aparecem em **Situação de Saída**.

## Avisos comuns

- **Nomes parecidos:** se já existir aluno com mesmo nome e data de nascimento, o sistema pergunta se é a mesma pessoa antes de salvar (evita duplicidade). O aviso depende do alerta de homônimos estar ligado nos parâmetros.
- **Acentos:** quando o sistema está configurado para nomes sem acentuação, os acentos são removidos automaticamente e um aviso aparece abaixo do nome.
- **CPF repetido:** se o CPF já pertencer a um funcionário, a data de nascimento precisa ser idêntica.
- **Excluir:** aluno que já tem matrículas ou outros registros vinculados não pode ser excluído — o sistema bloqueia a remoção e avisa.
