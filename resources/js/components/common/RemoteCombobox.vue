<script setup lang="ts">
import { Search, X, LoaderCircle } from 'lucide-vue-next';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

/**
 * Combobox com busca REMOTA. Faz fetch em `fetchUrl?q=...` e mapeia a resposta
 * via `idKey`/`labelKey`. Mantém o rótulo do item selecionado mesmo sem re-fetch
 * (via `selectedLabel`, útil ao pré-carregar edição).
 */
const props = withDefaults(defineProps<{
    modelValue: number | null;
    fetchUrl: string;
    idKey?: string;
    labelKey?: string;
    placeholder?: string;
    invalid?: boolean;
    disabled?: boolean;
    selectedLabel?: string | null;
    minChars?: number;
}>(), {
    idKey: 'id',
    labelKey: 'label',
    placeholder: 'Buscar...',
    minChars: 0,
});

const emit = defineEmits<{
    (e: 'update:modelValue', value: number | null): void;
    (e: 'selected', item: { id: number; label: string } | null): void;
}>();

interface Opt { id: number; label: string }

const open = ref(false);
const query = ref('');
const items = ref<Opt[]>([]);
const loading = ref(false);
const localLabel = ref<string | null>(props.selectedLabel ?? null);

const triggerEl = ref<HTMLButtonElement | null>(null);
const inputEl = ref<HTMLInputElement | null>(null);
const dropdownEl = ref<HTMLDivElement | null>(null);
const dropdownStyle = ref({ top: '0px', left: '0px', width: '0px', transform: '' });

watch(() => props.selectedLabel, (v) => { if (v != null) localLabel.value = v; });

const selectedText = computed(() => {
    if (props.modelValue == null) return null;
    const found = items.value.find((i) => i.id === props.modelValue);
    return found?.label ?? localLabel.value ?? `#${props.modelValue}`;
});

let timer: ReturnType<typeof setTimeout> | null = null;
const doFetch = () => {
    if (timer) clearTimeout(timer);
    timer = setTimeout(async () => {
        if (query.value.trim().length < (props.minChars ?? 0)) { items.value = []; return; }
        loading.value = true;
        try {
            const u = new URL(props.fetchUrl, window.location.origin);
            u.searchParams.set('q', query.value.trim());
            const r = await fetch(u, { headers: { Accept: 'application/json' } });
            const data = r.ok ? await r.json() : [];
            items.value = (Array.isArray(data) ? data : []).map((row: Record<string, unknown>) => ({
                id: Number(row[props.idKey]),
                label: String(row[props.labelKey] ?? ''),
            }));
        } catch {
            items.value = [];
        } finally {
            loading.value = false;
        }
    }, 200);
};

watch(query, doFetch);

const updatePosition = () => {
    if (!triggerEl.value) return;
    const rect = triggerEl.value.getBoundingClientRect();
    const maxH = 277;
    const below = window.innerHeight - rect.bottom - 4;
    const above = rect.top - 4;
    const up = below < maxH && above > below;
    dropdownStyle.value = up
        ? { top: `${rect.top - 4}px`, left: `${rect.left}px`, width: `${rect.width}px`, transform: 'translateY(-100%)' }
        : { top: `${rect.bottom + 4}px`, left: `${rect.left}px`, width: `${rect.width}px`, transform: '' };
};

const choose = (item: Opt) => {
    localLabel.value = item.label;
    emit('update:modelValue', item.id);
    emit('selected', item);
    open.value = false;
    query.value = '';
};

const clear = () => {
    emit('update:modelValue', null);
    emit('selected', null);
    localLabel.value = null;
    query.value = '';
};

const openPanel = () => {
    if (props.disabled) return;
    updatePosition();
    open.value = true;
    query.value = '';
    doFetch();
    nextTick(() => inputEl.value?.focus());
};

const handleOutside = (e: MouseEvent) => {
    const t = e.target as Node;
    if (!triggerEl.value?.contains(t) && !dropdownEl.value?.contains(t)) open.value = false;
};

onMounted(() => {
    document.addEventListener('mousedown', handleOutside);
    window.addEventListener('scroll', updatePosition, true);
    window.addEventListener('resize', updatePosition);
});
onBeforeUnmount(() => {
    document.removeEventListener('mousedown', handleOutside);
    window.removeEventListener('scroll', updatePosition, true);
    window.removeEventListener('resize', updatePosition);
});
</script>

<template>
    <div class="relative w-full min-w-0">
        <button
            ref="triggerEl"
            type="button"
            :disabled="disabled"
            :class="[
                'flex h-9 w-full items-center justify-between rounded-md border bg-background px-3 py-1 text-sm shadow-sm transition focus:outline-none focus-visible:ring-1 focus-visible:ring-ring',
                invalid ? 'border-red-500 ring-1 ring-red-500' : 'border-input',
                disabled ? 'cursor-not-allowed opacity-60' : '',
            ]"
            @click="openPanel"
        >
            <span v-if="selectedText" class="min-w-0 truncate">{{ selectedText }}</span>
            <span v-else class="min-w-0 truncate text-muted-foreground">{{ placeholder }}</span>
            <span
                v-if="modelValue != null && !disabled"
                role="button"
                tabindex="0"
                class="ml-2 shrink-0 rounded p-0.5 hover:bg-muted"
                @click.stop="clear"
                @keydown.enter.stop="clear"
            >
                <X class="size-3.5" />
            </span>
        </button>

        <Teleport to="body">
            <div
                v-if="open"
                ref="dropdownEl"
                class="pointer-events-auto fixed z-[9999] overflow-hidden rounded-md border bg-popover text-popover-foreground shadow-lg"
                :style="dropdownStyle"
            >
                <div class="flex items-center border-b px-3">
                    <Search class="size-4 shrink-0 text-muted-foreground" />
                    <input
                        ref="inputEl"
                        v-model="query"
                        type="text"
                        class="h-9 w-full bg-transparent px-2 text-sm placeholder:text-muted-foreground focus:outline-none"
                        placeholder="Filtrar..."
                    />
                    <LoaderCircle v-if="loading" class="size-4 shrink-0 animate-spin text-muted-foreground" />
                </div>
                <ul class="max-h-60 overflow-y-auto py-1 text-sm">
                    <li v-if="!loading && items.length === 0" class="px-3 py-2 text-muted-foreground">Nenhum resultado.</li>
                    <li
                        v-for="item in items"
                        :key="item.id"
                        :class="['flex cursor-pointer items-center px-3 py-2 hover:bg-muted', modelValue === item.id ? 'bg-muted font-medium' : '']"
                        @mousedown.prevent="choose(item)"
                    >
                        {{ item.label }}
                    </li>
                </ul>
            </div>
        </Teleport>
    </div>
</template>
