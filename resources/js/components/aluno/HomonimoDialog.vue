<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { AlertTriangle } from 'lucide-vue-next';

export interface HomonimoMatch {
    aln_id: number;
    aln_nome: string;
    aln_dt_nascimento: string | null;
    aln_cpf: string | null;
    aln_nr_matricula: number | null;
}

defineProps<{
    open: boolean;
    matches: HomonimoMatch[];
    processing?: boolean;
}>();

const emit = defineEmits<{
    (e: 'update:open', v: boolean): void;
    (e: 'confirm'): void;
    (e: 'cancel'): void;
}>();

const fmtDate = (iso: string | null) => {
    if (!iso) return '—';
    const m = iso.match(/^(\d{4})-(\d{2})-(\d{2})/);
    return m ? `${m[3]}/${m[2]}/${m[1]}` : iso;
};

const fmtCpf = (cpf: string | null) => {
    if (!cpf) return '—';
    return cpf.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
};
</script>

<template>
    <Dialog :open="open" @update:open="(v) => emit('update:open', v)">
        <DialogContent class="sm:max-w-2xl">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2 text-amber-700 dark:text-amber-300">
                    <AlertTriangle class="size-5" />
                    Possível homônimo detectado
                </DialogTitle>
                <DialogDescription>
                    Já existe(m) {{ matches.length }} aluno(s) com o mesmo nome e data de nascimento.
                    Verifique antes de prosseguir.
                </DialogDescription>
            </DialogHeader>

            <div class="max-h-80 overflow-auto rounded-lg border">
                <table class="w-full text-left text-sm">
                    <thead class="bg-amber-50 text-xs uppercase text-amber-800 dark:bg-amber-900/30 dark:text-amber-200">
                        <tr>
                            <th class="px-3 py-2">Matrícula</th>
                            <th class="px-3 py-2">Nome</th>
                            <th class="px-3 py-2">Nascimento</th>
                            <th class="px-3 py-2">CPF</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="m in matches" :key="m.aln_id">
                            <td class="px-3 py-2 font-mono tabular-nums">{{ m.aln_nr_matricula ?? '—' }}</td>
                            <td class="px-3 py-2 font-medium">{{ m.aln_nome }}</td>
                            <td class="px-3 py-2 tabular-nums">{{ fmtDate(m.aln_dt_nascimento) }}</td>
                            <td class="px-3 py-2 tabular-nums text-muted-foreground">{{ fmtCpf(m.aln_cpf) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <DialogFooter>
                <Button type="button" variant="outline" :disabled="processing" @click="emit('cancel')">
                    Cancelar
                </Button>
                <Button
                    type="button"
                    :disabled="processing"
                    class="bg-amber-600 hover:bg-amber-700"
                    @click="emit('confirm')"
                >
                    Cadastrar mesmo assim
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
