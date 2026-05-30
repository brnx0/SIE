<script setup lang="ts">
import { Search, X } from 'lucide-vue-next';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps<{
    modelValue: number | null;
    items: { id: number; label: string }[];
    placeholder?: string;
    invalid?: boolean;
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: number | null): void;
}>();

const open = ref(false);
const query = ref('');
const triggerEl = ref<HTMLButtonElement | null>(null);
const inputEl = ref<HTMLInputElement | null>(null);
const dropdownEl = ref<HTMLDivElement | null>(null);

// Position for teleported dropdown
const dropdownStyle = ref({ top: '0px', left: '0px', width: '0px' });

const selected = computed(() =>
    props.modelValue !== null ? (props.items.find((i) => i.id === props.modelValue) ?? null) : null,
);

const filtered = computed(() => {
    const q = query.value.trim().toLowerCase();
    if (!q) return props.items;
    return props.items.filter((i) => i.label.toLowerCase().includes(q));
});

watch(
    () => props.modelValue,
    () => {
        if (props.modelValue === null) query.value = '';
    },
);

const updatePosition = () => {
    if (!triggerEl.value) return;
    const rect = triggerEl.value.getBoundingClientRect();
    const dropdownMaxH = 240 + 37; // max-h-60 (list) + search row
    const spaceBelow = window.innerHeight - rect.bottom - 4;
    const spaceAbove = rect.top - 4;
    const openUpward = spaceBelow < dropdownMaxH && spaceAbove > spaceBelow;

    dropdownStyle.value = openUpward
        ? {
              top: `${rect.top - 4}px`,
              left: `${rect.left}px`,
              width: `${rect.width}px`,
              transform: 'translateY(-100%)',
          }
        : {
              top: `${rect.bottom + 4}px`,
              left: `${rect.left}px`,
              width: `${rect.width}px`,
              transform: '',
          };
};

const choose = (item: { id: number; label: string }) => {
    emit('update:modelValue', item.id);
    open.value = false;
    query.value = '';
};

const clear = () => {
    emit('update:modelValue', null);
    query.value = '';
};

const openPanel = () => {
    updatePosition();
    open.value = true;
    query.value = '';
    nextTick(() => inputEl.value?.focus());
};

const handleOutside = (e: MouseEvent) => {
    const target = e.target as Node;
    const inTrigger = triggerEl.value?.contains(target);
    const inDropdown = dropdownEl.value?.contains(target);
    if (!inTrigger && !inDropdown) {
        open.value = false;
    }
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
            :class="[
                'flex h-9 w-full items-center justify-between rounded-md border bg-background px-3 py-1 text-sm shadow-sm transition focus:outline-none focus-visible:ring-1 focus-visible:ring-ring',
                invalid ? 'border-red-500 ring-1 ring-red-500' : 'border-input',
            ]"
            @click="openPanel"
        >
            <span v-if="selected" class="min-w-0 truncate">{{ selected.label }}</span>
            <span v-else class="min-w-0 truncate text-muted-foreground">{{ placeholder ?? 'Selecione...' }}</span>

            <span
                v-if="selected"
                role="button"
                tabindex="0"
                class="ml-2 shrink-0 rounded p-0.5 hover:bg-muted"
                @click.stop="clear"
                @keydown.enter.stop="clear"
                @keydown.space.stop="clear"
            >
                <X class="size-3.5" />
            </span>
        </button>

        <Teleport to="body">
            <div
                v-if="open"
                ref="dropdownEl"
                class="fixed z-[9999] overflow-hidden rounded-md border bg-popover text-popover-foreground shadow-lg"
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
                </div>

                <ul class="max-h-60 overflow-y-auto py-1 text-sm">
                    <li
                        v-if="filtered.length === 0"
                        class="px-3 py-2 text-muted-foreground"
                    >
                        Nenhum resultado encontrado.
                    </li>
                    <li
                        v-for="item in filtered"
                        :key="item.id"
                        :class="[
                            'flex cursor-pointer items-center px-3 py-2 hover:bg-muted',
                            modelValue === item.id ? 'bg-muted font-medium' : '',
                        ]"
                        @mousedown.prevent="choose(item)"
                    >
                        {{ item.label }}
                    </li>
                </ul>
            </div>
        </Teleport>
    </div>
</template>
