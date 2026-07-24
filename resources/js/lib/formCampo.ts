/**
 * Classe padrão para campos nativos (`<select>`, `<input>`) usados ao lado de
 * LocalCombobox nos formulários de relatório.
 *
 * Espelha exatamente o trigger do LocalCombobox (`h-9` + `px-3 py-1` + borda e
 * sombra). Sem isso, um `<select>` com `py-2` fica ~2px mais alto e os campos da
 * mesma linha desalinham.
 */
export const CAMPO =
    'h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm transition ' +
    'focus:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-60';
