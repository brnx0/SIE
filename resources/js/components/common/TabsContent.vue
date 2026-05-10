<script setup lang="ts">
import { computed, inject, type Ref } from 'vue';

const props = defineProps<{ value: string }>();
const active = inject<Ref<string>>('tabs:active');
const show = computed(() => active?.value === props.value);
</script>

<template>
    <div v-show="show" role="tabpanel" class="focus:outline-none">
        <slot />
    </div>
</template>
