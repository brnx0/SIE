<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { ShieldAlert } from 'lucide-vue-next';

defineProps<{
    open: boolean;
    owner: string;       // nome extraído do erro (já inclui "(registro excluído)" se aplicável)
}>();

const emit = defineEmits<{
    (e: 'update:open', v: boolean): void;
}>();
</script>

<template>
    <Dialog :open="open" @update:open="(v) => emit('update:open', v)">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2 text-rose-600 dark:text-rose-400">
                    <ShieldAlert class="size-5" />
                    CPF já cadastrado
                </DialogTitle>
                <DialogDescription class="pt-2 text-sm text-foreground">
                    Este CPF já pertence ao seguinte funcionário:
                    <br />
                    <span class="mt-1 block font-semibold text-foreground">{{ owner }}</span>
                </DialogDescription>
            </DialogHeader>

            <DialogFooter>
                <Button
                    variant="destructive"
                    @click="emit('update:open', false)"
                >
                    Entendido
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
