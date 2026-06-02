# SIE Matrícula — Boas práticas

## Soft delete vs Hard delete

- **Inativar** (`*_fl_ativo = false`): registro permanece no banco, segue listado nas telas de cadastro/admin, mas **não aparece como opção** em telas que o consomem como dependência.
- **Excluir** (botão lixeira): hard delete via `safeDelete()` no Controller base. Se houver FK bloqueando, retorna mensagem orientando o usuário a inativar.

```php
// app/Http/Controllers/MeuController.php
public function destroy(MeuModel $registro): RedirectResponse
{
    return $this->safeDelete($registro)
        ?? back()->with('success', 'Removido.');
}
```

## Lookups: `incluir_ids` (manter histórico)

Quando uma tela edita um registro que já tem FK salva para um item que **depois** foi inativado, esse item precisa continuar visível no lookup — caso contrário a edição quebra ou perde o vínculo.

### Backend

Use `filtroAtivoOuIncluso()` no Controller base:

```php
public function search(Request $request): JsonResponse
{
    $incluirIds = $this->incluirIds($request); // lê CSV ou array

    $query = MeuModel::query()->orderBy('nome');
    $this->filtroAtivoOuIncluso($query, 'col_fl_ativo', 'col_id', $incluirIds);

    return response()->json($query->limit(50)->get());
}
```

Gera SQL: `WHERE (col_fl_ativo = true OR col_id IN (?, ?, ...))`.

### Frontend

Composables aceitam `incluirIds: number[]`. Forms passam o(s) ID(s) **atualmente salvo(s)** no `initial`:

```ts
// edit
searchSegmentos(escId, anlId, initial.tur_seg_id ? [initial.tur_seg_id] : []);
```

## Lookups aplicando o pattern

Todos `app/Http/Controllers/Api/*Controller@search`:
- `SegmentoController` (via `EscolaSegmento`)
- `SerieController` (bySegmento, byEscolaSegmento, search)
- `DisciplinaController`
- `CargoController`
- `EscolaController`
- `BairroController`
- `FuncionarioController`
- `GerenciaRegionalController`
