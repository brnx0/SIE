<script setup lang="ts">
import { Label } from '@/components/ui/label';
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        for?: string;
        required?: boolean;
    }>(),
    { required: false },
);

const cls = computed(() =>
    props.required
        ? "after:ml-0.5 after:text-rose-600 after:content-['*'] dark:after:text-rose-400"
        : '',
);
</script>

<template>
    <Label :for="props.for" :class="cls">
        <slot />
    </Label>
</template>
