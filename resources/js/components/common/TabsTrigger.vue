<script setup lang="ts">
import { computed, inject, type Ref } from 'vue';

const props = defineProps<{
    value: string;
    hasError?: boolean;
}>();

const active = inject<Ref<string>>('tabs:active');
const isActive = computed(() => active?.value === props.value);

const select = () => {
    if (active) active.value = props.value;
};
</script>

<template>
    <button
        type="button"
        role="tab"
        :aria-selected="isActive"
        :class="[
            'relative whitespace-nowrap rounded-md px-4 py-2 text-sm font-medium transition-all focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-500',
            isActive
                ? 'bg-sky-600 text-white shadow-sm dark:bg-sky-500'
                : 'text-muted-foreground hover:bg-background hover:text-foreground',
        ]"
        @click="select"
    >
        <slot />
        <span
            v-if="hasError && !isActive"
            class="absolute -right-0.5 -top-0.5 size-2 rounded-full bg-rose-500"
        />
    </button>
</template>
