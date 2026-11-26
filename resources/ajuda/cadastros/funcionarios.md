---
titulo: Cadastro de Funcionários
secao: Cadastros
ordem_secao: 1
ordem: 2
resumo: Cadastre servidores, documentos e registre admissões e lotações nas escolas.
slug: funcionarios
---

## O que é

Cadastro de todos os funcionários da rede — dados pessoais, escolaridade, documentos e o vínculo com as escolas (admissão e lotação). Professores cadastrados aqui é que ficam disponíveis para alocar em turmas e no diário.

## Como acessar

Menu **Cadastros → Professor/Funcionário → Funcionários** (endereço `/funcionarios`). Clique em **Novo funcionário** para cadastrar.

Na lista, cada funcionário tem os ícones de ação:

- **Olho (Visualizar)** — abre o cadastro em modo consulta (somente leitura). Quem tem permissão de edição vê o botão **Editar** no topo para passar a alterar.
- **Lápis (Editar)** — abre o cadastro para alteração.
- **Lixeira (Remover)** — exclui o funcionário.

O lápis e a lixeira só aparecem para quem tem permissão de edição.

## Passo a passo

1. Preencha a aba **Dados Pessoais** (nome, nascimento, sexo, CPF, cor/raça, nacionalidade, naturalidade, estado civil, filiação).
2. Na aba **Escolaridade**, informe ao menos uma escolaridade.
3. Informe os documentos na aba **Documentação** (RG, certidão, CTPS, título de eleitor, CNH).
4. Preencha **Endereço e Contato** e, se for o caso, a aba **Deficiência**.
5. Clique em **Cadastrar funcionário** (botão no topo). Depois, na aba **Admissão / Lotação**:
   - Clique em **Nova Admissão** e cadastre a admissão (matrícula, data, cargo).
   - Clique em **Nova Lotação** e cadastre a lotação, ligando a admissão a uma escola, vínculo e função.
6. A aba **Horário** (disponível na edição) mostra os horários de aula atribuídos no ano letivo em exercício.

## Campos principais

- **Nome completo, Data de nascimento, Sexo, CPF, Etnia/Cor/Raça, Nacionalidade, País de Origem, Naturalidade, Estado Civil, Filiação 1** (com a indicação Mãe ou Pai) e **Localização/Zona de residência** — obrigatórios.
- **CPF** — sempre obrigatório para funcionário.
- **Escolaridade** — no cadastro de um novo funcionário é obrigatório informar ao menos uma.
- **Funcionário ativo** — controla se ele aparece nas listas de seleção.
- **Foto do funcionário** — JPG, PNG ou WEBP de até 5 MB. Clique na foto para ampliá-la.
- **Lotação** — Admissão vinculada, Escola, Vínculo, Função Atual e Data Inicial são obrigatórios.
- **Função exercida em sala de aula** — obrigatória quando o cargo é docente (Professor, Regente, Monitor de Creche, etc.).

## Avisos comuns

- **Admissão antes da lotação:** só é possível lançar lotação depois de cadastrar a admissão correspondente.
- **Escolaridade para docente:** não é possível lotar o funcionário em cargo docente sem ao menos uma escolaridade cadastrada.
- **Data final da lotação:** ao preencher a data final, a lotação é marcada automaticamente como inativa.
- **Salvar bloqueado:** enquanto houver uma escolaridade, admissão ou lotação em edição, finalize ou cancele esse registro para liberar o botão de salvar do cadastro.
- **CPF repetido:** não é possível cadastrar dois funcionários com o mesmo CPF; se o CPF coincidir com o de um aluno, a data de nascimento precisa bater.
- **Nomes parecidos:** quando o alerta de homônimos está ligado nos parâmetros, o sistema pergunta antes de salvar se já existir funcionário com mesmo nome e data de nascimento.
- **Remover lotação:** se o professor tiver alocação em turma da escola, a lotação não pode ser removida — informe a **Data Final** para inativá-la.
- **Excluir x Inativar:** funcionário já vinculado a turmas/lotações não pode ser excluído — o sistema bloqueia e orienta a inativar. Use **Funcionário ativo** para inativar.
