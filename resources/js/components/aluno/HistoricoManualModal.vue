<script setup lang="ts">
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import HistoricoManualPanel from '@/components/aluno/HistoricoManualPanel.vue';

const props = defineProps<{ open: boolean; aluno: { aln_id: number; aln_nome: string } | null }>();
const emit = defineEmits<{ (e: 'update:open', v: boolean): void }>();
</script>

<template>
    <Dialog :open="open" @update:open="(v) => emit('update:open', v)">
        <DialogContent
            class="flex max-h-[90vh] w-[95vw] max-w-4xl flex-col gap-0 overflow-hidden p-0"
            @interact-outside="(e) => e.preventDefault()"
            @escape-key-down="(e) => e.preventDefault()"
        >
            <DialogHeader class="border-b p-4">
                <DialogTitle>Histórico Escolar — Lançamento Manual{{ aluno ? ` · ${aluno.aln_nome}` : '' }}</DialogTitle>
            </DialogHeader>

            <HistoricoManualPanel :aluno="props.aluno" :active="props.open" />
        </DialogContent>
    </Dialog>
</template>
