<script setup lang="ts">
defineProps<{
    message?: string|null;
}>();
</script>

<template>
    <!-- Reserva a altura de uma linha mesmo sem erro: evita que o texto do erro
         estique a célula do grid e desalinhe os campos vizinhos ao aparecer. -->
    <div class="min-h-[1.25rem]">
        <p v-show="message" class="text-sm leading-5 text-red-600 dark:text-red-500">
            {{ message }}
        </p>
    </div>
</template>
