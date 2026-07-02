<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { sessionExpired, forcarLogout } from '@/lib/session';
import { LogOut, ShieldAlert } from 'lucide-vue-next';
import { ref } from 'vue';

const saindo = ref(false);

const sair = () => {
    saindo.value = true;
    forcarLogout();
};
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
        >
            <div
                v-if="sessionExpired"
                class="fixed inset-0 z-[100] grid place-items-center bg-slate-900/60 p-4 backdrop-blur-sm"
            >
                <div class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex items-start gap-4">
                        <div class="grid size-11 shrink-0 place-items-center rounded-full bg-rose-100 text-rose-600 dark:bg-rose-900/40 dark:text-rose-300">
                            <ShieldAlert class="size-6" />
                        </div>
                        <div class="min-w-0">
                            <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-50">Sessão expirada</h2>
                            <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">
                                Sua sessão expirou por inatividade. Por segurança, você será desconectado.
                                Faça login novamente para continuar.
                            </p>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end">
                        <Button
                            :disabled="saindo"
                            class="bg-indigo-600 text-white hover:bg-indigo-700"
                            @click="sair"
                        >
                            <LogOut class="mr-2 size-4" />
                            {{ saindo ? 'Saindo…' : 'Ir para o login' }}
                        </Button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
