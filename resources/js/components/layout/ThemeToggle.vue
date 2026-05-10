<script setup lang="ts">
import { useAppearance } from '@/composables/useAppearance';
import { Monitor, Moon, Sun } from 'lucide-vue-next';
import { computed } from 'vue';

const { appearance, updateAppearance } = useAppearance();

const order = ['light', 'dark'] as const;

const current = computed(() => {
    if (appearance.value === 'light') return { Icon: Sun, label: 'Tema claro' };
    else return { Icon: Moon, label: 'Tema escuroo' };

});

const cycle = () => {
    const i = order.indexOf(appearance.value as typeof order[number]);
    updateAppearance(order[(i + 1) % order.length]);
};
</script>

<template>
    <button
        type="button"
        :title="current.label"
        :aria-label="current.label"
        @click="cycle"
        class="inline-flex size-9 items-center justify-center rounded-md border border-slate-200 bg-white text-slate-600 transition hover:bg-slate-100 hover:text-slate-900 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-slate-50"
    >
        <component :is="current.Icon" class="size-4" />
    </button>
</template>
