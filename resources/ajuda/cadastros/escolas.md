---
titulo: Escolas
secao: Cadastros
ordem_secao: 1
ordem: 7
resumo: Cadastro completo das escolas — identificação, endereço, etapas de ensino oferecidas e Censo Escolar.
slug: escolas
---

## O que é

Cadastro-mestre das instituições da rede. Reúne identificação (nome, CNPJ, INEP), endereço, dados administrativos, as etapas de ensino que a escola oferece e o preenchimento do Censo Escolar.

## Como acessar

Menu **Cadastros → Rede de Ensino → Escolas** (endereço `/escolas`).

Na lista, cada escola tem os ícones de ação:

- **Olho (Visualizar)** — abre o cadastro em modo consulta (somente leitura). Quem tem permissão de edição vê o botão **Editar** no topo para passar a alterar.
- **Lápis (Editar)** — abre o cadastro para alteração.
- **Lixeira (Remover)** — exclui a escola.

O lápis e a lixeira só aparecem para quem tem permissão de edição.

## Passo a passo

1. Clique em **Nova escola**.
2. Preencha a aba **Identificação** (nome, código Censo, CNPJ, apelido, dados administrativos).
3. Em **Endereço e Contato**, informe o CEP (preenche o endereço) e o e-mail.
4. Preencha a **Ata de Autorização** se aplicável e clique em **Cadastrar escola** (botão no topo).
5. No modo de edição, use a aba **Etapas de Ensino** e o botão **Adicionar etapa de ensino** para definir quais etapas de ensino e anos escolares a escola oferece.
6. A aba **Censo** dá acesso ao formulário do Censo Escolar (dependências, salas e equipamentos, internet, profissionais, materiais, gestão): clique em **Iniciar preenchimento** na primeira vez e em **Editar censo** nas seguintes.

## Campos principais

- **Nome, Código Censo (INEP), CNPJ, Apelido** e os dados administrativos (Dependência Administrativa, Situação de Funcionamento, Proprietário do Imóvel, Forma de Ocupação, Regulamentada pelo Conselho) — obrigatórios.
- **CEP, Endereço, Número, Cidade/UF, Bairro, Zona, Localização Diferenciada (Censo), E-mail** — obrigatórios.
- **Escola ativa no sistema** — controla se a escola está ativa.
- **Logo da escola** — JPG, PNG ou WEBP de até 5 MB. Clique na imagem para ampliá-la.
- **Etapas de Ensino** — definem quais anos escolares a escola atende (ano escolar inicial e final) e em qual período (ano letivo de início e, se houver, de fim).

## Avisos comuns

- **CNPJ e INEP** são únicos entre as escolas.
- O **CEP** busca endereço, cidade e bairro automaticamente.
- **Nome e Apelido** são gravados sem acentuação.
- Não é possível remover uma etapa de ensino da escola se houver turmas vinculadas a ela.
- O **Censo** é um registro único por escola e pode ser atualizado sempre que houver mudança.
- **Excluir x Inativar:** escola já vinculada a outros registros não pode ser excluída — o sistema bloqueia e orienta a inativar. Desligue **Escola ativa no sistema** em vez de excluir.
