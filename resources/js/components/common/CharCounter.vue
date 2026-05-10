<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    value: string | null | undefined;
    max: number;
}>();

const len = computed(() => (props.value ?? '').length);
const cls = computed(() =>
    len.value >= props.max
        ? 'text-rose-600 dark:text-rose-400'
        : 'text-muted-foreground',
);
</script>

<template>
    <span :class="['text-xs tabular-nums', cls]">{{ len }}/{{ max }}</span>
</template>
