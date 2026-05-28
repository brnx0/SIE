<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Download, ChevronDown } from 'lucide-vue-next';
import { ref, onMounted, onBeforeUnmount } from 'vue';

const props = defineProps<{
    baseUrl: string;
    filters: Record<string, string | number | null | undefined>;
}>();

const open = ref(false);
const menuRef = ref<HTMLElement | null>(null);

const close = (e: MouseEvent) => {
    if (menuRef.value && !menuRef.value.contains(e.target as Node)) open.value = false;
};
onMounted(() => document.addEventListener('mousedown', close));
onBeforeUnmount(() => document.removeEventListener('mousedown', close));

const buildUrl = (format: 'csv' | 'pdf') => {
    const params = new URLSearchParams();
    params.set('format', format);
    for (const [k, v] of Object.entries(props.filters)) {
        if (v !== null && v !== undefined && v !== '') params.set(k, String(v));
    }
    return `${props.baseUrl}?${params.toString()}`;
};
</script>

<template>
    <div class="relative" ref="menuRef">
        <Button
            type="button"
            variant="outline"
            class="gap-1.5"
            @click="open = !open"
        >
            <Download class="size-4" />
            Exportar
            <ChevronDown class="size-3.5 opacity-60" />
        </Button>

        <div
            v-if="open"
            class="absolute right-0 z-20 mt-1 w-40 rounded-lg border bg-popover shadow-lg"
            @click="open = false"
        >
            <a
                :href="buildUrl('csv')"
                class="flex w-full items-center gap-2 px-3 py-2.5 text-sm hover:bg-muted rounded-t-lg"
            >
                Exportar CSV
            </a>
            <div class="mx-3 border-t" />
            <a
                :href="buildUrl('pdf')"
                class="flex w-full items-center gap-2 px-3 py-2.5 text-sm hover:bg-muted rounded-b-lg"
            >
                Exportar PDF
            </a>
        </div>
    </div>
</template>
