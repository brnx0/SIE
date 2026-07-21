<script setup lang="ts">
import BairroCombobox from '@/components/escola/BairroCombobox.vue';
import FormLabel from '@/components/common/FormLabel.vue';
import InputError from '@/components/common/InputError.vue';
import InstituicaoCombobox from '@/components/transporte/InstituicaoCombobox.vue';
import MunicipioCombobox from '@/components/aluno/MunicipioCombobox.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { PontoRota, Rota } from '@/types/transporte';
import { router } from '@inertiajs/vue3';
import { ArrowDown, ArrowUp, Clock, Landmark, Loader2, MapPin, Pencil, Plus, RefreshCw, Trash2, X } from 'lucide-vue-next';
import { computed, reactive, ref } from 'vue';

const props = defineProps<{
    rota: Rota;
    pontos: PontoRota[];
}>();

const list = computed<PontoRota[]>(() =>
    [...(props.pontos ?? [])].sort((a, b) => a.pto_ordem - b.pto_ordem),
);

const showForm = ref(false);
const editingId = ref<number | null>(null);
const processing = ref(false);
const errors = ref<Record<string, string>>({});
const munId = ref<number | null>(null);

const form = reactive<{
    pto_nome: string;
    pto_endereco: string;
    pto_bai_id: number | null;
    pto_latitude: number | null;
    pto_longitude: number | null;
    pto_hr_prevista: string;
    pto_tin_id: number | null;
}>({
    pto_nome: '',
    pto_endereco: '',
    pto_bai_id: null,
    pto_latitude: null,
    pto_longitude: null,
    pto_hr_prevista: '',
    pto_tin_id: null,
});

const bairroInitial = ref<{ bai_id: number; bai_nome: string; bai_mun_id?: number } | null>(null);
const instituicaoInitial = ref<{ tin_id: number; tin_nome: string } | null>(null);

const resetForm = () => {
    form.pto_nome = '';
    form.pto_endereco = '';
    form.pto_bai_id = null;
    form.pto_latitude = null;
    form.pto_longitude = null;
    form.pto_hr_prevista = '';
    form.pto_tin_id = null;
    munId.value = null;
    bairroInitial.value = null;
    instituicaoInitial.value = null;
    errors.value = {};
    editingId.value = null;
    showForm.value = false;
};

const openCreate = () => {
    resetForm();
    showForm.value = true;
};

const openEdit = (p: PontoRota) => {
    resetForm();
    editingId.value = p.pto_id;
    form.pto_nome = p.pto_nome;
    form.pto_endereco = p.pto_endereco ?? '';
    form.pto_bai_id = p.pto_bai_id;
    form.pto_latitude = p.pto_latitude;
    form.pto_longitude = p.pto_longitude;
    form.pto_hr_prevista = p.pto_hr_prevista ?? '';
    form.pto_tin_id = p.pto_tin_id;
    munId.value = p.bairro?.bai_mun_id ?? null;
    bairroInitial.value = p.bairro ?? null;
    instituicaoInitial.value = p.instituicao ?? null;
    showForm.value = true;
};

const submit = () => {
    processing.value = true;
    errors.value = {};

    const payload = { ...form } as Record<string, any>;
    const opts = {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => resetForm(),
        onError: (e: Record<string, string>) => { errors.value = e; },
        onFinish: () => { processing.value = false; },
    };

    if (editingId.value) {
        router.put(`/transporte/rotas/${props.rota.rot_id}/pontos/${editingId.value}`, payload, opts);
    } else {
        router.post(`/transporte/rotas/${props.rota.rot_id}/pontos`, payload, opts);
    }
};

const remove = (p: PontoRota) => {
    if (!confirm(`Remover o ponto "${p.pto_nome}"?`)) return;
    router.delete(`/transporte/rotas/${props.rota.rot_id}/pontos/${p.pto_id}`, {
        preserveScroll: true,
        preserveState: true,
    });
};

const reordenar = (novaOrdem: number[]) => {
    router.put(`/transporte/rotas/${props.rota.rot_id}/pontos-reordenar`, { ordem: novaOrdem }, {
        preserveScroll: true,
        preserveState: true,
    });
};

const mover = (index: number, delta: number) => {
    const ids = list.value.map((p) => p.pto_id);
    const alvo = index + delta;
    if (alvo < 0 || alvo >= ids.length) return;
    [ids[index], ids[alvo]] = [ids[alvo], ids[index]];
    reordenar(ids);
};

const refreshing = ref(false);
const refresh = () => {
    refreshing.value = true;
    router.reload({
        only: ['pontos', 'passageiros', 'flash'],
        preserveScroll: true,
        preserveState: true,
        onFinish: () => { refreshing.value = false; },
    });
};
</script>

<template>
    <div class="grid gap-6">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-sm font-semibold">Pontos de Parada</h3>
                <p class="text-xs text-muted-foreground">Sequência do trajeto — use as setas para reordenar.</p>
            </div>
            <div class="flex items-center gap-2">
                <Button type="button" size="sm" variant="outline" :disabled="refreshing" title="Atualizar" @click="refresh">
                    <RefreshCw :class="['mr-1 size-4', refreshing && 'animate-spin']" /> Atualizar
                </Button>
                <Button v-if="!showForm" type="button" size="sm" variant="outline" @click="openCreate">
                    <Plus class="mr-1 size-4" /> Adicionar ponto
                </Button>
            </div>
        </div>

        <!-- Form add/edit -->
        <div v-if="showForm" class="rounded-xl border border-indigo-200 bg-indigo-50/50 p-5 shadow-sm dark:border-indigo-800 dark:bg-indigo-900/20">
            <div class="mb-4 flex items-center justify-between">
                <h4 class="text-sm font-medium">{{ editingId ? 'Editar ponto' : 'Novo ponto' }}</h4>
                <button type="button" class="rounded p-1 hover:bg-muted" @click="resetForm"><X class="size-4" /></button>
            </div>

            <div class="grid gap-4 sm:grid-cols-12">
                <div class="grid gap-1.5 sm:col-span-6">
                    <FormLabel :required="true">Nome / referência</FormLabel>
                    <Input v-model="form.pto_nome" maxlength="120" placeholder="Ex.: Mercadinho do Zé" :class="{ 'border-rose-500': errors.pto_nome }" />
                    <InputError :message="errors.pto_nome" />
                </div>
                <div class="grid gap-1.5 sm:col-span-4">
                    <FormLabel>Endereço</FormLabel>
                    <Input v-model="form.pto_endereco" maxlength="200" placeholder="Rua, número..." />
                    <InputError :message="errors.pto_endereco" />
                </div>
                <div class="grid gap-1.5 sm:col-span-2">
                    <FormLabel>Horário previsto</FormLabel>
                    <Input v-model="form.pto_hr_prevista" type="time" />
                    <InputError :message="errors.pto_hr_prevista" />
                </div>

                <div class="grid gap-1.5 sm:col-span-3">
                    <FormLabel>Município</FormLabel>
                    <MunicipioCombobox v-model="munId" />
                </div>
                <div class="grid gap-1.5 sm:col-span-3">
                    <FormLabel>Bairro</FormLabel>
                    <BairroCombobox v-model="form.pto_bai_id" :mun-id="munId" :initial="bairroInitial as any" :invalid="!!errors.pto_bai_id" />
                    <InputError :message="errors.pto_bai_id" />
                </div>
                <div class="grid gap-1.5 sm:col-span-6">
                    <FormLabel>Instituição (destino)</FormLabel>
                    <InstituicaoCombobox v-model="form.pto_tin_id" :initial="instituicaoInitial" />
                    <InputError :message="errors.pto_tin_id" />
                </div>

                <div class="grid gap-1.5 sm:col-span-3">
                    <FormLabel>Latitude</FormLabel>
                    <Input v-model.number="form.pto_latitude" type="number" step="any" min="-90" max="90" placeholder="-3.7436121" />
                    <InputError :message="errors.pto_latitude" />
                </div>
                <div class="grid gap-1.5 sm:col-span-3">
                    <FormLabel>Longitude</FormLabel>
                    <Input v-model.number="form.pto_longitude" type="number" step="any" min="-180" max="180" placeholder="-38.5266704" />
                    <InputError :message="errors.pto_longitude" />
                </div>
            </div>

            <div class="mt-4 flex justify-end gap-2">
                <Button type="button" variant="outline" size="sm" @click="resetForm">Cancelar</Button>
                <Button type="button" size="sm" :disabled="processing" class="bg-indigo-600 text-white hover:bg-indigo-700" @click="submit">
                    <Loader2 v-if="processing" class="mr-1 size-4 animate-spin" />
                    {{ editingId ? 'Salvar' : 'Adicionar' }}
                </Button>
            </div>
        </div>

        <!-- Estado vazio -->
        <div v-if="list.length === 0 && !showForm" class="rounded-xl border-2 border-dashed bg-card py-12 text-center text-sm text-muted-foreground shadow-sm">
            Nenhum ponto de parada. Clique em "Adicionar ponto" para começar.
        </div>

        <!-- Lista -->
        <div v-if="list.length > 0" class="rounded-xl border bg-card shadow-sm">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b bg-muted/30 text-left text-xs uppercase tracking-wide text-muted-foreground">
                        <th class="w-24 px-4 py-3 text-center">Ordem</th>
                        <th class="px-4 py-3">Ponto</th>
                        <th class="px-4 py-3">Localização</th>
                        <th class="px-4 py-3 text-center">Horário</th>
                        <th class="px-4 py-3 text-center">Passag.</th>
                        <th class="w-28 px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(p, i) in list" :key="p.pto_id" class="border-b last:border-0 hover:bg-muted/20">
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-center gap-1">
                                <span class="grid size-6 place-items-center rounded-full bg-indigo-100 text-xs font-semibold text-indigo-700 dark:bg-indigo-900/50 dark:text-indigo-300">{{ i + 1 }}</span>
                                <div class="flex flex-col">
                                    <button type="button" class="rounded p-0.5 hover:bg-muted disabled:opacity-30" :disabled="i === 0" title="Subir" @click="mover(i, -1)"><ArrowUp class="size-3.5" /></button>
                                    <button type="button" class="rounded p-0.5 hover:bg-muted disabled:opacity-30" :disabled="i === list.length - 1" title="Descer" @click="mover(i, 1)"><ArrowDown class="size-3.5" /></button>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <div class="font-medium">{{ p.pto_nome }}</div>
                            <div v-if="p.instituicao" class="mt-0.5 inline-flex items-center gap-1 text-xs text-muted-foreground">
                                <Landmark class="size-3" /> {{ p.instituicao.tin_nome }}
                            </div>
                        </td>
                        <td class="px-4 py-3 text-xs text-muted-foreground">
                            <div v-if="p.pto_endereco">{{ p.pto_endereco }}</div>
                            <div v-if="p.bairro">{{ p.bairro.bai_nome }}</div>
                            <div v-if="p.pto_latitude != null && p.pto_longitude != null" class="inline-flex items-center gap-1 text-emerald-600">
                                <MapPin class="size-3" /> Georreferenciado
                            </div>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span v-if="p.pto_hr_prevista" class="inline-flex items-center gap-1 text-xs">
                                <Clock class="size-3 text-muted-foreground" /> {{ p.pto_hr_prevista }}
                            </span>
                            <span v-else class="text-muted-foreground">—</span>
                        </td>
                        <td class="px-4 py-3 text-center text-muted-foreground">{{ p.total_passageiros ?? 0 }}</td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-1">
                                <button type="button" class="rounded p-1.5 hover:bg-muted" title="Editar" @click="openEdit(p)"><Pencil class="size-4 text-indigo-600" /></button>
                                <button type="button" class="rounded p-1.5 hover:bg-muted" title="Remover" @click="remove(p)"><Trash2 class="size-4 text-rose-500" /></button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
