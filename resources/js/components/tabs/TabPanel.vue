<script setup lang="ts">
import { provide } from 'vue';
import { TAB_ID } from '@/lib/tabRegistry';

const props = defineProps<{ tabId: string }>();
provide(TAB_ID, props.tabId);
</script>

<template>
    <div class="h-full">
        <slot />
    </div>
</template>
