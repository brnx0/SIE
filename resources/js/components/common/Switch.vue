<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    modelValue: boolean;
    id?: string;
    disabled?: boolean;
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: boolean): void;
}>();

const checked = computed(() => !!props.modelValue);

const toggle = () => {
    if (props.disabled) return;
    emit('update:modelValue', !checked.value);
};
</script>

<template>
    <button
        :id="id"
        type="button"
        role="switch"
        :aria-checked="checked"
        :disabled="disabled"
        :class="[
            'inline-flex h-6 w-11 shrink-0 cursor-pointer items-center rounded-full border border-transparent transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-500 disabled:cursor-not-allowed disabled:opacity-50',
            checked
                ? 'bg-sky-600 dark:bg-sky-500'
                : 'bg-input',
        ]"
        @click="toggle"
    >
        <span
            :class="[
                'pointer-events-none inline-block size-5 transform rounded-full bg-white shadow ring-0 transition-transform',
                checked ? 'translate-x-5' : 'translate-x-0.5',
            ]"
        />
    </button>
</template>
