<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { router } from '@inertiajs/vue3';
import { RefreshCw } from 'lucide-vue-next';
import { ref } from 'vue';

// Atualiza os registros da tela atual (outros usuários podem inserir/alterar).
// Recarrega todas as props da página fresh (inclui flash → sem repetir toast),
// preservando scroll, filtros locais e a aba ativa.
const refreshing = ref(false);

const refresh = () => {
    refreshing.value = true;
    router.reload({
        preserveScroll: true,
        preserveState: true,
        onFinish: () => {
            refreshing.value = false;
        },
    });
};
</script>

<template>
    <Button
        type="button"
        variant="outline"
        class="gap-1.5"
        :disabled="refreshing"
        title="Atualizar registros"
        @click="refresh"
    >
        <RefreshCw :class="['size-4', refreshing && 'animate-spin']" />
        Atualizar
    </Button>
</template>
