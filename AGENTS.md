# SIE Matrícula — Boas práticas e padrões

> Stack: **Laravel + Inertia + Vue 3 + TS + Tailwind**. **Postgres** (`sie_auth`, docker). Idioma **pt_BR**. Marca **indigo → fuchsia**.
> Guia canônico de convenções do projeto. Manter em sincronia com `CLAUDE.md`.

---

## 1. Convenções de nomenclatura

### Colunas — prefixo de 3 letras por tabela
`aln_*` (aluno), `tma_*` (matrícula em turma), `tur_*` (turma), `esc_*` (escola), `seg_*` (segmento), `ser_*` (série), `dis_*` (disciplina), `fun_*` (funcionário), `crg_*` (cargo), `anl_*` (ano letivo), `grh_*` (grade horário), `grd_*` (grade disciplinar), `trh_*` (turma horário), `tup_*` (turma professor), `tpa_*` (turma professor apoio), `als_*` (aluno saúde), `par_*` (parâmetros entidade), `mun_*` (município), `bai_*` (bairro), `tas_*` (turma-aluno situação), `hmn_*`/`hmd_*` (histórico manual), `fes_*` (funcionário escolaridade), `cfs_*` (curso formação superior), `tla_*`/`tlp_*` (tela/permissão).

- **Flags booleanas**: `*_fl_*` (ex.: `aln_fl_ativo`).
- **Datas**: `*_dt_*`. **Códigos**: `*_cd_*` (ex.: `esc_cd_escola`). **Timestamps**: `*_created_at`/`*_updated_at`/`*_deleted_at` (soft delete).

### Tabelas
- `edu_*` — dados de negócio educacional.
- `cfg_*` — configuração (`cfg_ano_letivo`, `cfg_parametros_entidade`, `cfg_tela`).

---

## 2. Soft delete vs Hard delete vs Inativação

| Operação | Como | Efeito | Quando |
|----------|------|--------|--------|
| **Inativar** | `*_fl_ativo = false` | Fica no DB, segue em telas admin, **some** de lookups dependentes | Cadastros mestres (escola, série, disciplina, funcionário) |
| **Soft delete** | `*_deleted_at` via `SoftDeletes` | Some das queries, recuperável | Transacionais (matrículas, lotações) |
| **Hard delete** | `forceDelete()`/`delete()` sem `SoftDeletes` | Linha some | Pivots simples sem histórico (`edu_turma_professor_apoio`) |

Padrão de exclusão pelo Controller base:
```php
public function destroy(MeuModel $registro): RedirectResponse
{
    return $this->safeDelete($registro)
        ?? back()->with('success', 'Removido.');
}
```
`safeDelete()` retorna `null` no sucesso ou `RedirectResponse` com mensagem se FK bloquear (orienta a inativar).

---

## 3. Lookups: `incluir_ids` (manter histórico)

Quando uma tela edita um registro com FK para um item **depois inativado**, esse item precisa continuar visível no lookup.

**Backend** — `filtroAtivoOuIncluso()` no Controller base:
```php
$incluirIds = $this->incluirIds($request); // CSV ou array
$this->filtroAtivoOuIncluso($query, 'col_fl_ativo', 'col_id', $incluirIds);
// SQL: WHERE (col_fl_ativo = true OR col_id IN (?, ...))
```
**Frontend** — composables aceitam `incluirIds: number[]`; forms passam o(s) ID(s) salvo(s) no `initial`.

Lookups que aplicam o pattern: `SegmentoController`, `SerieController` (bySegmento/byEscolaSegmento/search), `DisciplinaController`, `CargoController`, `EscolaController`, `BairroController`, `FuncionarioController`, `GerenciaRegionalController`.

---

## 4. Parâmetros do sistema (ParametroEntidade)

Flags globais expostas via Inertia em **`page.props.params`** (nunca direto em `page.props`):
`nome_pessoa_caixa_alta`, `nome_escola_caixa_alta`, `alertar_homonimos`, `alertar_acentos_nomes`, `validar_idade_serie`, `gerar_matricula_auto`, `validar_carga_prof`, `cpf_obrigatorio`, `fardamento_obrigatorio`, `tipo_validacao_carga` (`'avisar'|'bloquear'`).

```ts
const params = computed(() => (page.props as any).params ?? {});
const cpfObrigatorio = computed(() => !!params.value.cpf_obrigatorio);
```
- **Acentuação:** `alertar_acentos_nomes` só controla o **banner**. Strip de acentos é **sempre** aplicado nos campos de texto: `s.normalize('NFD').replace(/[̀-ͯ]/g, '')`.

---

## 5. Feedback visual de gravação

- **Toda gravação retorna flash `success`/`error`**; o `FlashToaster` global renderiza o toast — **não usar banner inline**. Flash é compartilhado em `HandleInertiaRequests` (`flash.success`/`flash.error`).
- **store/update redirecionam para `{recurso}.edit`** do próprio registro (não index). **destroy** vai para index.
- Botão **Salvar no topo** do form (acima das abas); navegação Anterior/Próximo no rodapé.
- **Erro 403 de dono/permissão dentro de fluxo:** não deixar página crua do Laravel. Redirecionar com flash `error` (ex.: `throw new HttpResponseException(redirect()->route(...)->with('error', ...))`) → toast amigável no front.

### 5.1 Foco no campo obrigatório ao salvar (OBRIGATÓRIO em todo form)

Ao gravar, **sempre** levar o usuário até o **primeiro campo inválido/obrigatório não preenchido** — scroll + foco. Usar o helper canônico `useErrorFocus` no `onError` do submit. Requisitos:
- O `id` do input deve bater com a chave do erro (sem o prefixo aninhado): erro `saude.als_cid` → `<Input id="als_cid">`. O helper faz `idFromErrorKey`.
- Em forms com **abas**: ativar a aba do primeiro erro **antes** de focar (input em aba oculta não está no DOM).

```ts
import { useErrorFocus } from '@/composables/useErrorFocus';
const { focusFirstError } = useErrorFocus();

// Inertia useForm
form.post(url, {
    onError: (errors) => {
        // (forms com abas) goToFirstErrorTab(errors) antes:
        focusFirstError(Object.keys(errors));
    },
});
```
Para validação **client-side** (ex.: modal de matrícula), montar `errors.value` e chamar `focusFirstError` do mesmo jeito. Já aplicado em `AlunoForm`, `FuncionarioForm`, `MatriculaModal` — replicar em qualquer form novo.

---

## 6. Menu × autorização (sidebar) + Permissão por tela

**Item de menu nunca aparece se a role não puder acessar a tela.** O gate da rota tem espelho no `AppSidebar.vue`.

- Grupos com itens condicionais viram `computed<any[]>` (não array estático). Item condicional entra via spread: `...(isAdmin.value ? [{...}] : [])`.
- Atenção ao `flatLeaves`: grupo que virou `computed` precisa de `.value` dentro do `flattenItems(...)`.

**Matriz de permissão por tela** (`cfg_tela` + `cfg_tela_permissao`):
- Fonte de verdade = **`database/seeders/TelaPermissaoSeeder.php`** (MATRIZ `[rota, nome, grupo, ordem, [níveis por role]]`). Níveis: **0** sem acesso, **1** ver+editar, **2** só visualizar. O `run()` usa **`updateOrCreate`** (autoritativo: seed sobrescreve o banco).
- Ordem das roles: `admin, gestor_secretaria, professor, professor_aee, coordenador, coordenador_interno, diretor, secretaria_escola, pedagogico, monitor, matricula_censo`.
- Frontend: `usePermissao()` → `podeVer(rota)` (menu) e `nivelAtual`/`isReadOnly` (tela atual, read-only = 2, via fieldset). Rota **fora** da matriz → escondida (fail-closed quando permissões ativas).
- Ao criar tela nova: adicionar à MATRIZ **e** ao `AppSidebar`; rodar `php artisan db:seed --class=TelaPermissaoSeeder`.

**Roles globais sem filtro de lotação:** `UserAccess::semFiltroLotacao()` = `admin`, `gestor_secretaria`, `coordenador_interno` → veem tudo sem filtro de escola (retorna `null` = "sem filtro"). Não usar `isAdmin()` puro quando o gestor também deve ter acesso — ex.: Diário AEE/Atividade usam `User::isGestorGlobal()` (admin **ou** gestor_secretaria) e o middleware `role:` inclui as roles extras. `EnsureUserHasRole`: **admin sempre passa**; sintaxe **vírgula** (`role:a,b`).

---

## 7. Listagens

Toolbar (direita → esquerda): `RefreshButton` · `PerPageSelect` (10/25/50) · `ExportMenu` (CSV `BOM+;` e PDF mPDF — **exporta sempre tudo filtrado**).
```vue
<div class="ml-auto flex items-center gap-3">
    <RefreshButton />        <!-- router.reload preserveScroll+preserveState -->
    <PerPageSelect v-model="perPage" />
    <ExportMenu base-url="/recurso/export" :filters="{ search }" />
</div>
```
- **Largura da página:** Inertia usa `w-[95%]` (sem `max-w-*`).
- Busca livre: filtrar no backend por nome + relação (ex.: `tur_nome ILIKE %x% OR serie.ser_nome ILIKE %x%`).

---

## 8. Shell de abas (TabShell + TabRegistrar)

Identidade da aba por `pathOf` (`/lib/tabRegistry.ts`): 1º segmento p/ CRUD; 2 segmentos quando o 2º não é numérico nem `create`/`edit` (rota nomeada = aba própria). Nomes amigáveis em `PATH_LABELS`. Título da aba = `breadcrumbs[0].title` (omitir 1ª breadcrumb apontando p/ o pai quando for aba separada). **`TabRegistrar` observa `page.component`, `page.url` E `page.props`** — não remover `page.props` do watch.

---

## 9. Sub-listas dentro de form (abas)

1. Renderizar via `computed(() => props.xxx ?? [])` — **NÃO** copiar p/ `ref` + `watch`.
2. `router.post/put/delete` com **AMBOS** `preserveScroll: true` **e** `preserveState: true` (sem `preserveState` a aba volta pra primeira).
3. Botão "Atualizar" da sub-lista: `router.reload({ only: ['prop1','prop2','flash'], preserveScroll: true, preserveState: true })` (incluir `'flash'`).
- **Listagens não-padrão** (`grade-disciplinar`, `matriculas`) buscam via composable/API sob demanda — `router.reload`/`RefreshButton` **não** atualiza; re-disparar o `search` da composable.

---

## 10. Serialização Inertia / camelCase

Laravel `toArray()` aplica `$snakeAttributes = true`: relations em camelCase (`professoresApoio()`) viram **`professores_apoio`** no JSON. Atributos dinâmicos (`$turma->total_matriculados = ...`) **não** serializam. Fix: passar prop separado serializado manualmente e fazer merge no Edit.vue.

---

## 11. Componente `Input` (ui/input)

Usa `v-model="modelValue"` — **ignora prop `value`**. Errado: `:value="x"` (aparece vazio). Certo: `:model-value="x ?? 0"`.

---

## 12. Abrir janelas externas (PDFs, comprovantes)

`window.open(url, '_blank')` **direto no gesto do usuário** (clique). **NÃO** `window.open('', '_blank')` antes de `await fetch()` — em alguns browsers navega a aba atual.

---

## 13. Máscaras e endereço (aluno/funcionário)

- **Telefone:** máscara `(00) 00000-0000` (via `v-maska` ou handler); armazena mascarado, backend **tira os dígitos** em `prepareForValidation` (`digits_between:10,11`).
- **CEP → endereço (ViaCEP):** `useViaCep`; ao completar 8 dígitos preenche logradouro/bairro/cidade/UF e **trava** (`readonly`/`disabled`) os campos que o CEP retornou (libera ao trocar o CEP).
- **Município:** sempre lookup da nossa base (`MunicipioCombobox`/`edu_municipio`), nunca texto livre.
- **Limites:** nome do aluno e filiação `max:50`; demais textos com `maxlength` alinhado ao backend (todo `text` sem limite ganha `max:*`).

---

## 14. Validações de matrícula/aluno

- **Duplicidade/homônimo:** backend lança `ValidationException` com chave especial (JSON dos matches); front abre `HomonimoDialog`; se confirmar, reenvia com `confirm_duplicata: true`. Componente: `@/components/aluno/HomonimoDialog.vue`.
- **Idade × série:** só quando o parâmetro **`validar_idade_serie` está ativo** E a série tem `ser_idade`. Sem enforcement no backend — checagem **só no frontend**, em 2 momentos (antes de abrir o modal: `Index.vue` lê `props.parametros.par_fl_validar_idade_serie`; no submit do modal: lê `params.validar_idade_serie`). Idade contada até `anl_dt_corte`.
- **Flags da matrícula vivem em `edu_aluno`** (não na matrícula): `aln_fl_trouxe_transferencia/rg/reg_nascimento`, `aln_fl_bolsa_familia`, `aln_fl_recebe_merenda`, `aln_fl_usa_transporte/biblioteca`, `aln_fl_indigena` (derivado `cor_raca === 5`), `aln_fl_creche` (derivado: segmento contém "CRECHE"). Modal salva via `payload.aluno.*`.
- **Quadro de saúde** em `edu_aluno_saude` (1-1). No `reset()` do modal, limpar `formSaude` **antes** de `preencherFormAluno`. PCD (`possui_deficiencia`) exige ≥1 item entre deficiências / TGD / transtornos aprendizagem / altas habilidades / outro (front **e** backend). Campo "Alergia a" é separado de "Restrições Alimentares".

---

## 15. Migrations destrutivas (Postgres)

Dropar constraints/índices cujo nome varia entre ambientes: descobrir dinamicamente via `pg_constraint` num bloco `DO $$ ... $$` (evita `constraint "x" does not exist`).

---

## 16. Cargo docente (lotação)

`isCargoDocente()` em `FuncionarioLotacaoController` decide se "função em sala de aula" é obrigatória. Prefixos: `Professor/Docente/Regente/Prof` + lista de nomes exatos (Cuidador, Mediador, Tradutor LIBRAS, Auxiliar de Creche, etc.). **Mesma lista espelhada** em `AdmissaoLotacaoTab.vue` (`CARGOS_SALA_AULA_EXATOS`) — manter sincronizada.

**Escolaridade do funcionário:** lista própria (`edu_funcionario_escolaridade`, `fes_*`); catálogo INEP `edu_curso_formacao_superior` (`cfs_*`); curso obrigatório nos níveis {5,6,7,8,10}, mín. 1.

---

## 17. Grade disciplinar é por ANO LETIVO

Toda query de grade filtra por `grd_ser_id` **E `grd_anl_id`** (a grade muda de ano p/ ano). Aplica em boletim, encerramento, histórico. Exceção: o **Histórico Escolar** cai para a grade da série de **qualquer ano** se não houver grade do ano específico (escolaridade legada).

---

## 18. Deploy homolog/prod (Docker)

- **nginx roda no HOST** (`fastcgi_pass`); o **container é php-fpm puro** — sem nginx em container.
- Composes: `docker-compose.app.yml` (app + worker) e `docker-compose.db.yml` (postgres). Container app `sie_app_${MUNICIPIO}`, db `sie_pg_${MUNICIPIO}`. **Porta do Postgres = `127.0.0.1:5432:5432`** (localhost only; acesso externo via SSH tunnel). **Não** usar `5432:5432` (0.0.0.0) em servidor público.
- `storage/app` é volume nomeado; `public` é bind-mount do host. **Sem `storage:link`** (symlink quebra pelo split) — entrypoint faz `rm -f public/storage`; a rota pública `PublicStorageController` (`storage/{path}`) serve o arquivo via PHP.
- Entrypoint (php-fpm): `config:cache`, `route:cache`, `view:cache`, `event:cache`. Migrations OFF por padrão (`RUN_MIGRATIONS=true` p/ ligar; recomendado migrar manual antes do up).
- **Dependência de produção:** qualquer pacote usado em runtime deve estar em `require` (não `require-dev`). `composer install --no-dev` em prod → **sempre do lock, nunca `composer update` no deploy**. (Ex.: `symfony/yaml` precisou ir p/ `require` porque o portal de Ajuda usa o FrontMatter do commonmark → `Yaml::parse()`.)

---

## 19. Central de Ajuda

Rota pública `ajuda` → `AjudaController` lê `resources/ajuda/**.md` (frontmatter + Markdown → HTML, versionado no git; editar `.md` publica sem deploy de código). Processamento de cada arquivo em `try/catch` (1 arquivo ruim não derruba a página; loga e pula).

---

## 20. Documentação (QA + MER)

`docs/regras/` (regras por tela p/ QA, com cenários Gherkin) e `docs/mer/` (modelo de dados, Mermaid). Pasta **gitignored**. Atualizar por módulo/domínio conforme muda a regra. Manter `CLAUDE.md`/`AGENTS.md` em sincronia com o comportamento real.

---

## 21. Gotchas rápidos (UI/UX)

- **`<Input type="date">`**: ter `min`/`max` + handler `@input` que clampa ano com > 4 dígitos.
- **AEE (dupla matrícula):** aluno não pode estar em 2 turmas AEE ativas no mesmo ano (`TurmaAeeAlunoController::store` valida — as duas telas de matrícula AEE postam nesse endpoint).
- **Combobox remoto dentro de Dialog modal (radix):** o dropdown teleportado p/ `body` precisa de `pointer-events-auto` (o modal seta `pointer-events: none` no body). Componente reutilizável: `RemoteCombobox` (busca via URL).
- **DialogContent:** sobrescrever o `grid gap-4` padrão por `flex flex-col` quando o conteúdo empilha (evita sobreposição). Alinhar colunas com `items-start` (labels de alturas diferentes).
- **Modal só fecha no X:** `@interact-outside.preventDefault` + `@escape-key-down.preventDefault` no `DialogContent`.
- **Alocação de professor na turma:** 1 disciplina por turma — não listar/permitir disciplina já alocada (front filtra; backend bloqueia por `tup_dis_id`; edição inline mantém a atual).
- **Tabela de relatório (impressão):** `table-layout: fixed` + larguras por coluna p/ alinhar entre blocos/turmas (auto-layout desalinha).
- **`v-html` em `<Link>`** (paginação Inertia): mover o `v-html` para um `<span>` interno (regra ESLint `vue/no-v-text-v-html-on-component`).
