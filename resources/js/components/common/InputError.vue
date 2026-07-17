<script setup lang="ts">
defineProps<{
    message?: string|null;
}>();
</script>

<template>
    <!-- Reserva a altura de uma linha mesmo sem erro: evita que o texto do erro
         estique a célula do grid e desalinhe os campos vizinhos ao aparecer. -->
    <div class="min-h-[1.25rem]">
        <!-- data-input-error: âncora usada pelo foco automático no primeiro campo
             inválido (lib/focoErro.ts). v-show mantém o <p> no DOM, então lá a
             visibilidade é conferida antes de escolher o alvo. -->
        <p v-show="message" data-input-error class="text-sm leading-5 text-red-600 dark:text-red-500">
            {{ message }}
        </p>
    </div>
</template>
