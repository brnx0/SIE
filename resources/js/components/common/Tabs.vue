<script setup lang="ts">
import { computed, provide, ref, watch } from 'vue';

const props = defineProps<{
    modelValue?: string;
    defaultValue?: string;
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void;
}>();

const internal = ref<string>(props.modelValue ?? props.defaultValue ?? '');

watch(
    () => props.modelValue,
    (v) => {
        if (v !== undefined) internal.value = v;
    },
);

const active = computed({
    get: () => internal.value,
    set: (v: string) => {
        internal.value = v;
        emit('update:modelValue', v);
    },
});

provide('tabs:active', active);
</script>

<template>
    <div class="flex flex-col gap-4">
        <slot />
    </div>
</template>
