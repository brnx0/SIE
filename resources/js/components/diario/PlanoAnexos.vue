<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { FileSpreadsheet, FileText, Loader2, Paperclip, Presentation, Trash2, Upload } from 'lucide-vue-next';
import { computed, ref } from 'vue';

// Material de apoio do plano de aula (consulta). Usado no formulário do professor
// (com upload/remoção) e no modal de revisão da coordenação (só leitura).
// Os anexos NÃO entram no PDF do plano.
export interface PlanoAnexo {
    dax_id: number;
    nome: string;
    mime: string;
    tamanho: number;
    eh_imagem: boolean;
    usuario: string | null;
    criado_em: string | null;
    url: string;
}

const props = withDefaults(defineProps<{
    anexos: PlanoAnexo[];
    /** URL de upload (POST); ausente = só leitura. */
    uploadUrl?: string | null;
    podeAlterar?: boolean;
}>(), { uploadUrl: null, podeAlterar: false });

// Espelho da validação do servidor (DiarioPlanoAnexo) — aqui só para avisar antes.
const EXTENSOES = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'];
const MAX_BYTES = 10 * 1024 * 1024;
const MAX_POR_PLANO = 10;
const accept = EXTENSOES.map((e) => `.${e}`).join(',');

const input = ref<HTMLInputElement | null>(null);
const enviando = ref(false);
const removendo = ref<number | null>(null);
const erro = ref<string | null>(null);

const editavel = computed(() => props.podeAlterar && !!props.uploadUrl);
const restantes = computed(() => MAX_POR_PLANO - props.anexos.length);

const escolher = () => {
    erro.value = null;
    input.value?.click();
};

const aoSelecionar = (e: Event) => {
    const el = e.target as HTMLInputElement;
    const arquivos = Array.from(el.files ?? []);
    el.value = ''; // permite reenviar o mesmo arquivo depois
    erro.value = null;
    if (!arquivos.length || !props.uploadUrl) return;

    const invalido = arquivos.find((f) => !EXTENSOES.includes((f.name.split('.').pop() ?? '').toLowerCase()));
    if (invalido) {
        erro.value = `"${invalido.name}": formato não permitido. Envie PDF, imagem (JPG, PNG, WEBP) ou documento do Office (DOC, DOCX, XLS, XLSX, PPT, PPTX).`;
        return;
    }
    const grande = arquivos.find((f) => f.size > MAX_BYTES);
    if (grande) {
        erro.value = `"${grande.name}" passa de 10 MB.`;
        return;
    }
    if (arquivos.length > restantes.value) {
        erro.value = `O plano pode ter no máximo ${MAX_POR_PLANO} anexos (restam ${restantes.value}).`;
        return;
    }

    enviando.value = true;
    router.post(props.uploadUrl, { anexos: arquivos }, {
        forceFormData: true,
        preserveScroll: true,
        preserveState: true,
        onError: (errors) => {
            erro.value = Object.values(errors)[0] ?? 'Não foi possível enviar o arquivo.';
        },
        onFinish: () => (enviando.value = false),
    });
};

const remover = (a: PlanoAnexo) => {
    if (!confirm(`Remover o anexo "${a.nome}"?`)) return;
    removendo.value = a.dax_id;
    router.delete(a.url, {
        preserveScroll: true,
        preserveState: true,
        onFinish: () => (removendo.value = null),
    });
};

const fmtTamanho = (b: number) =>
    b >= 1024 * 1024 ? `${(b / 1024 / 1024).toFixed(1)} MB` : `${Math.max(1, Math.round(b / 1024))} KB`;
const fmtData = (d: string | null) =>
    d ? new Date(d).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' }) : '';
const icone = (a: PlanoAnexo) => {
    const ext = (a.nome.split('.').pop() ?? '').toLowerCase();
    if (['xls', 'xlsx'].includes(ext)) return FileSpreadsheet;
    if (['ppt', 'pptx'].includes(ext)) return Presentation;
    return FileText;
};
</script>

<template>
    <div class="flex flex-col gap-3">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <p class="text-xs text-muted-foreground">
                Material de apoio para consulta da coordenação — não entra na impressão do plano.
                <template v-if="editavel"> PDF, imagens ou Office · até 10 MB cada · máx. {{ MAX_POR_PLANO }} por plano.</template>
            </p>
            <template v-if="editavel">
                <input ref="input" type="file" class="hidden" multiple :accept="accept" @change="aoSelecionar" />
                <button
                    type="button"
                    :disabled="enviando || restantes <= 0"
                    class="inline-flex h-9 items-center gap-1.5 rounded-md border border-input px-3 text-sm font-medium transition hover:bg-muted/50 disabled:cursor-not-allowed disabled:opacity-50"
                    @click="escolher"
                >
                    <Loader2 v-if="enviando" class="size-4 animate-spin" />
                    <Upload v-else class="size-4" />
                    {{ enviando ? 'Enviando...' : 'Anexar arquivos' }}
                </button>
            </template>
        </div>

        <p v-if="erro" class="rounded-md bg-rose-50 px-3 py-2 text-sm text-rose-700 dark:bg-rose-950/40 dark:text-rose-300">{{ erro }}</p>

        <div v-if="!anexos.length" class="flex items-center gap-2 rounded-lg border border-dashed px-4 py-6 text-sm text-muted-foreground">
            <Paperclip class="size-4" /> Nenhum anexo disponível para este plano.
        </div>

        <ul v-else class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
            <li v-for="a in anexos" :key="a.dax_id" class="flex items-center gap-3 rounded-lg border bg-background p-2">
                <a :href="a.url" target="_blank" rel="noopener" class="shrink-0" :title="`Abrir ${a.nome}`">
                    <img v-if="a.eh_imagem" :src="a.url" :alt="a.nome" class="size-12 rounded object-cover" loading="lazy" />
                    <span v-else class="flex size-12 items-center justify-center rounded bg-indigo-50 text-indigo-600 dark:bg-indigo-950/50 dark:text-indigo-300">
                        <component :is="icone(a)" class="size-6" />
                    </span>
                </a>
                <div class="min-w-0 flex-1">
                    <a :href="a.url" target="_blank" rel="noopener" class="block truncate text-sm font-medium hover:text-indigo-600 hover:underline" :title="a.nome">
                        {{ a.nome }}
                    </a>
                    <div class="truncate text-[11px] text-muted-foreground">
                        {{ fmtTamanho(a.tamanho) }} · {{ fmtData(a.criado_em) }}<template v-if="a.usuario"> · {{ a.usuario }}</template>
                    </div>
                </div>
                <button
                    v-if="editavel"
                    type="button"
                    :disabled="removendo === a.dax_id"
                    class="rounded p-1.5 text-muted-foreground transition hover:bg-rose-50 hover:text-rose-600 disabled:opacity-50 dark:hover:bg-rose-950/40"
                    :title="`Remover ${a.nome}`"
                    @click="remover(a)"
                >
                    <Loader2 v-if="removendo === a.dax_id" class="size-4 animate-spin" />
                    <Trash2 v-else class="size-4" />
                </button>
            </li>
        </ul>
    </div>
</template>
