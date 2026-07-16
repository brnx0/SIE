<script setup lang="ts">
import RemoteCombobox from '@/components/common/RemoteCombobox.vue';
import FormLabel from '@/components/common/FormLabel.vue';
import InputError from '@/components/common/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { usePermissao } from '@/composables/usePermissao';
import { useErrorFocus } from '@/composables/useErrorFocus';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ChevronLeft, LoaderCircle, Save, TriangleAlert, Warehouse } from 'lucide-vue-next';
import { ref } from 'vue';

defineProps<{
    ordens: { ofn_id: number; rotulo: string; situacao: string }[];
    tipos: string[];
}>();

const { isReadOnly } = usePermissao();
const { focusFirstError } = useErrorFocus();

const form = useForm<{
    rcb_ofn_id: number | null;
    rcb_dt: string;
    rcb_tipo: string;
    rcb_nr_nota: string;
    rcb_fun_id: number | null;
    rcb_obs: string;
}>({
    rcb_ofn_id: null,
    rcb_dt: new Date().toISOString().slice(0, 10),
    rcb_tipo: 'ORDINARIO',
    rcb_nr_nota: '',
    rcb_fun_id: null,
    rcb_obs: '',
});

const recebedor = ref<{ id: number; label: string } | null>(null);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Recebimento de Itens', href: '/alimentacao/recebimentos' },
    { title: 'Novo', href: '/alimentacao/recebimentos/create' },
];

const clampAno = (e: Event) => {
    const el = e.target as HTMLInputElement;
    const [ano] = el.value.split('-');
    if (ano && ano.length > 4) el.value = `${ano.slice(0, 4)}${el.value.slice(ano.length)}`;
};

const submit = () => {
    if (isReadOnly.value) return;
    form.post('/alimentacao/recebimentos', {
        onError: (errors: Record<string, string>) => focusFirstError(Object.keys(errors)),
    });
};
</script>

<template>
    <Head title="Novo recebimento" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4 md:p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">Novo recebimento</h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        Abre a conferência dos itens da ordem. Nada entra no estoque até você conferir e aceitar.
                    </p>
                </div>
                <Link href="/alimentacao/recebimentos">
                    <Button type="button" variant="outline"><ChevronLeft class="mr-1 size-4" /> Voltar</Button>
                </Link>
            </div>

            <div
                v-if="ordens.length === 0"
                class="flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-900/50 dark:bg-amber-900/20 dark:text-amber-200"
            >
                <TriangleAlert class="mt-0.5 size-4 shrink-0" />
                <p>
                    Nenhuma ordem de fornecimento tem itens pendentes de entrega. Emita uma ordem antes de registrar
                    um recebimento.
                </p>
            </div>

            <form v-else @submit.prevent="submit" novalidate class="grid gap-6">
                <fieldset :disabled="isReadOnly || form.processing" class="contents">
                    <div class="grid gap-5 rounded-xl border bg-card p-6 shadow-sm sm:grid-cols-6 sm:items-start">
                        <div class="flex items-center gap-3 rounded-lg border bg-muted/40 px-4 py-3 sm:col-span-6">
                            <Warehouse class="size-4 shrink-0 text-muted-foreground" />
                            <div>
                                <p class="text-sm font-medium leading-none">Entrada no estoque central</p>
                                <p class="mt-0.5 text-xs text-muted-foreground">
                                    O fornecedor entrega sempre no central; as escolas recebem por transferência.
                                </p>
                            </div>
                        </div>

                        <div class="grid gap-2 sm:col-span-4">
                            <FormLabel for="rcb_ofn_id" :required="true">Ordem de fornecimento</FormLabel>
                            <select id="rcb_ofn_id" v-model="form.rcb_ofn_id" class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-background dark:text-foreground">
                                <option :value="null" disabled>Selecione a ordem entregue...</option>
                                <option v-for="o in ordens" :key="o.ofn_id" :value="o.ofn_id">
                                    {{ o.rotulo }}{{ o.situacao === 'PARCIAL' ? ' (entrega parcial)' : '' }}
                                </option>
                            </select>
                            <p class="text-xs text-muted-foreground">
                                Só aparecem ordens com itens ainda não entregues.
                            </p>
                            <InputError :message="form.errors.rcb_ofn_id" />
                        </div>

                        <div class="grid gap-2 sm:col-span-2">
                            <FormLabel for="rcb_tipo" :required="true">Tipo</FormLabel>
                            <select id="rcb_tipo" v-model="form.rcb_tipo" class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-background dark:text-foreground">
                                <option v-for="t in tipos" :key="t" :value="t">{{ t === 'AGRICULTURA_FAMILIAR' ? 'Agricultura familiar' : 'Ordinário' }}</option>
                            </select>
                            <p class="text-xs text-muted-foreground">Agricultura familiar entra na apuração do PNAE.</p>
                            <InputError :message="form.errors.rcb_tipo" />
                        </div>

                        <div class="grid gap-2 sm:col-span-2">
                            <FormLabel for="rcb_dt" :required="true">Data do recebimento</FormLabel>
                            <Input id="rcb_dt" type="date" v-model="form.rcb_dt" min="2000-01-01" max="2099-12-31" @input="clampAno" />
                            <InputError :message="form.errors.rcb_dt" />
                        </div>

                        <div class="grid gap-2 sm:col-span-2">
                            <FormLabel for="rcb_nr_nota">Número da nota</FormLabel>
                            <Input id="rcb_nr_nota" v-model="form.rcb_nr_nota" maxlength="30" placeholder="Opcional" />
                            <InputError :message="form.errors.rcb_nr_nota" />
                        </div>

                        <div class="grid gap-2 sm:col-span-2">
                            <FormLabel>Recebido por</FormLabel>
                            <RemoteCombobox
                                v-model="form.rcb_fun_id"
                                fetch-url="/api/funcionarios"
                                id-key="fun_id"
                                label-key="fun_nome"
                                placeholder="Buscar funcionário..."
                                :invalid="!!form.errors.rcb_fun_id"
                                :selected-label="recebedor?.label ?? null"
                                @selected="recebedor = $event"
                            />
                            <InputError :message="form.errors.rcb_fun_id" />
                        </div>

                        <div class="grid gap-2 sm:col-span-6">
                            <FormLabel for="rcb_obs">Observação</FormLabel>
                            <Input id="rcb_obs" v-model="form.rcb_obs" placeholder="Opcional" />
                            <InputError :message="form.errors.rcb_obs" />
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <Button
                            type="submit"
                            :disabled="form.processing || !form.rcb_ofn_id"
                            class="bg-indigo-600 text-white hover:bg-indigo-700 dark:bg-indigo-500 dark:hover:bg-indigo-400"
                        >
                            <LoaderCircle v-if="form.processing" class="mr-2 size-4 animate-spin" />
                            <Save v-else class="mr-2 size-4" />
                            Abrir conferência
                        </Button>
                    </div>
                </fieldset>
            </form>
        </div>
    </AppLayout>
</template>
