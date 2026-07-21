<script setup lang="ts">
import InputError from '@/components/common/InputError.vue';
import { Button } from '@/components/ui/button';
import { validarAnexo } from '@/lib/validaArquivo';
import type { Anexo } from '@/types/alimentacao';
import { router, useForm } from '@inertiajs/vue3';
import { Download, FileText, Image as ImageIcon, LoaderCircle, Paperclip, Trash2, Upload, X } from 'lucide-vue-next';
import { ref } from 'vue';

const props = defineProps<{
    /** Base das rotas de anexo, ex.: /alimentacao/contratos/123/anexos */
    baseUrl: string;
    anexos: Anexo[];
    /** Bloqueia envio/remoção (somente-leitura ou registro fechado). */
    readonly?: boolean;
    titulo?: string;
    descricao?: string;
}>();

const inputEl = ref<HTMLInputElement | null>(null);
const erroLocal = ref<string | null>(null);

// Arquivos selecionados aguardando envio — dá para revisar antes de mandar.
const form = useForm<{ anexos: File[] }>({ anexos: [] });

const aoSelecionar = (e: Event) => {
    erroLocal.value = null;
    const escolhidos = Array.from((e.target as HTMLInputElement).files ?? []);
    const validos: File[] = [];
    for (const f of escolhidos) {
        const erro = validarAnexo(f, 10); // PDF/imagem, até 10 MB
        if (erro) { erroLocal.value = `${f.name}: ${erro}`; continue; }
        validos.push(f);
    }
    form.anexos = [...form.anexos, ...validos];
    // Zera o input para permitir reescolher o mesmo arquivo depois.
    if (inputEl.value) inputEl.value.value = '';
};

const removerDaFila = (i: number) => {
    form.anexos = form.anexos.filter((_, idx) => idx !== i);
};

const enviar = () => {
    if (props.readonly || form.anexos.length === 0) return;
    form.post(props.baseUrl, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => { form.reset('anexos'); erroLocal.value = null; },
    });
};

const remover = (a: Anexo) => {
    if (props.readonly) return;
    if (!confirm(`Remover o anexo "${a.anx_nome}"?`)) return;
    router.delete(`${props.baseUrl}/${a.anx_id}`, { preserveScroll: true });
};

const fmtTamanho = (bytes: number) => {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(0)} KB`;
    return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
};
const dataBr = (iso: string | null) => (iso ? iso.slice(0, 10).split('-').reverse().join('/') : '');
</script>

<template>
    <div class="grid gap-5 rounded-xl border bg-card p-6 shadow-sm">
        <div>
            <h2 class="text-lg font-semibold">{{ titulo ?? 'Anexos' }}</h2>
            <p class="mt-1 text-sm text-muted-foreground">
                {{ descricao ?? 'Arquivos PDF ou imagem (JPG, PNG, WEBP), até 10 MB cada.' }}
            </p>
        </div>

        <!-- Lista de anexos já enviados -->
        <div class="overflow-hidden rounded-lg border">
            <table class="w-full text-sm">
                <tbody class="divide-y">
                    <tr v-if="anexos.length === 0">
                        <td class="px-4 py-8 text-center text-muted-foreground">Nenhum anexo.</td>
                    </tr>
                    <tr v-for="a in anexos" :key="a.anx_id" class="transition-colors hover:bg-muted/30">
                        <td class="w-10 px-4 py-3">
                            <ImageIcon v-if="a.eh_imagem" class="size-5 text-indigo-500" />
                            <FileText v-else class="size-5 text-rose-500" />
                        </td>
                        <td class="px-2 py-3">
                            <a
                                :href="`${baseUrl}/${a.anx_id}`"
                                target="_blank"
                                rel="noopener"
                                class="font-medium text-indigo-600 hover:underline dark:text-indigo-400"
                            >
                                {{ a.anx_nome }}
                            </a>
                            <p class="text-xs text-muted-foreground">
                                {{ fmtTamanho(a.anx_tamanho) }}
                                <template v-if="a.usuario"> · {{ a.usuario }}</template>
                                <template v-if="a.criado_em"> · {{ dataBr(a.criado_em) }}</template>
                            </p>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-1">
                                <a :href="`${baseUrl}/${a.anx_id}`" target="_blank" rel="noopener">
                                    <Button type="button" variant="ghost" size="sm"><Download class="size-4" /></Button>
                                </a>
                                <Button
                                    v-if="!readonly"
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    class="text-rose-600 hover:bg-rose-50 hover:text-rose-700 dark:hover:bg-rose-900/30"
                                    @click="remover(a)"
                                >
                                    <Trash2 class="size-4" />
                                </Button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Envio -->
        <div v-if="!readonly" class="grid gap-3 rounded-lg border bg-muted/30 p-4">
            <div class="flex flex-wrap items-center gap-3">
                <input
                    ref="inputEl"
                    type="file"
                    multiple
                    accept="application/pdf,image/jpeg,image/png,image/webp"
                    class="hidden"
                    @change="aoSelecionar"
                />
                <Button type="button" variant="outline" @click="inputEl?.click()">
                    <Paperclip class="mr-2 size-4" /> Escolher arquivos
                </Button>
                <span class="text-xs text-muted-foreground">PDF ou imagem · máximo 10 MB por arquivo</span>
            </div>

            <p v-if="erroLocal" class="text-sm text-rose-600 dark:text-rose-400">{{ erroLocal }}</p>
            <InputError :message="(form.errors as Record<string, string>)['anexos.0'] || form.errors.anexos" />

            <!-- Fila de arquivos a enviar -->
            <ul v-if="form.anexos.length" class="grid gap-1">
                <li
                    v-for="(f, i) in form.anexos"
                    :key="i"
                    class="flex items-center justify-between rounded-md border bg-background px-3 py-2 text-sm"
                >
                    <span class="min-w-0 truncate">{{ f.name }} <span class="text-xs text-muted-foreground">({{ fmtTamanho(f.size) }})</span></span>
                    <button type="button" class="ml-2 shrink-0 rounded p-1 text-muted-foreground hover:bg-muted" @click="removerDaFila(i)">
                        <X class="size-4" />
                    </button>
                </li>
            </ul>

            <div v-if="form.anexos.length">
                <Button
                    type="button"
                    :disabled="form.processing"
                    class="bg-indigo-600 text-white hover:bg-indigo-700 dark:bg-indigo-500 dark:hover:bg-indigo-400"
                    @click="enviar"
                >
                    <LoaderCircle v-if="form.processing" class="mr-2 size-4 animate-spin" />
                    <Upload v-else class="mr-2 size-4" />
                    Enviar {{ form.anexos.length }} arquivo{{ form.anexos.length !== 1 ? 's' : '' }}
                </Button>
            </div>
        </div>
    </div>
</template>
