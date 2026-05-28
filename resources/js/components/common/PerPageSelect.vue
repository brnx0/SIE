<script setup lang="ts">
defineProps<{ modelValue: number }>();
defineEmits<{ 'update:modelValue': [value: number] }>();
</script>

<template>
    <div class="flex items-center gap-2 text-sm text-muted-foreground">
        <span class="whitespace-nowrap">Linhas por página:</span>
        <select
            :value="modelValue"
            @change="$emit('update:modelValue', Number(($event.target as HTMLSelectElement).value))"
            class="h-8 rounded-md border border-input bg-background px-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-background dark:text-foreground"
        >
            <option :value="10">10</option>
            <option :value="25">25</option>
            <option :value="50">50</option>
        </select>
    </div>
</template>
