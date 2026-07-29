<script setup lang="ts">
import InputError from '@/components/common/InputError.vue';
import TextLink from '@/components/common/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { Eye, EyeOff, LoaderCircle, ShieldAlert } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();

// Mensagem de sessão expirada (flash error vindo do redirect de logout).
const page = usePage();
const flashError = computed(() => (page.props as any).flash?.error as string | undefined);

const form = useForm({
    login: '',
    password: '',
    remember: false,
});

const showPassword = ref(false);

// Login em minúsculas — casa com o cadastro (normalizado no backend também).
watch(() => form.login, (v) => {
    const lower = v.toLowerCase();
    if (lower !== v) form.login = lower;
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <AuthBase title="Acesso ao sistema" description="Informe suas credenciais institucionais para continuar.">
        <Head title="Entrar" />

        <div v-if="status" class="mb-4 rounded-md bg-emerald-50 px-4 py-3 text-center text-sm font-medium text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300">
            {{ status }}
        </div>

        <div v-if="flashError" class="mb-4 flex items-center gap-2 rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700 dark:border-rose-900/50 dark:bg-rose-900/30 dark:text-rose-300">
            <ShieldAlert class="size-4 shrink-0" />
            {{ flashError }}
        </div>

        <form @submit.prevent="submit" class="flex flex-col gap-5">
            <div class="grid gap-2">
                <Label for="login">Login</Label>
                <Input
                    id="login"
                    type="text"
                    required
                    autofocus
                    tabindex="1"
                    autocomplete="username"
                    v-model="form.login"
                    placeholder="seu.login"
                />
                <InputError :message="form.errors.login" />
            </div>

            <div class="grid gap-2">
                <div class="flex items-center justify-between">
                    <Label for="password">Senha</Label>
                    <TextLink v-if="canResetPassword" :href="route('password.request')" class="text-xs" tabindex="5">
                        Esqueci minha senha
                    </TextLink>
                </div>
                <div class="relative">
                    <Input
                        id="password"
                        :type="showPassword ? 'text' : 'password'"
                        required
                        tabindex="2"
                        autocomplete="current-password"
                        v-model="form.password"
                        placeholder="••••••••"
                        class="pr-10"
                    />
                    <button
                        type="button"
                        tabindex="-1"
                        :aria-label="showPassword ? 'Ocultar senha' : 'Mostrar senha'"
                        :aria-pressed="showPassword"
                        class="absolute inset-y-0 right-0 flex items-center px-3 text-muted-foreground hover:text-foreground focus:outline-none"
                        @click="showPassword = !showPassword"
                    >
                        <EyeOff v-if="showPassword" class="size-4" />
                        <Eye v-else class="size-4" />
                    </button>
                </div>
                <InputError :message="form.errors.password" />
            </div>

            <Label for="remember" class="flex items-center gap-2 text-sm font-normal text-muted-foreground">
                <Checkbox id="remember" v-model:checked="form.remember" tabindex="4" />
                <span>Manter-me conectado</span>
            </Label>

            <Button
                type="submit"
                tabindex="3"
                :disabled="form.processing"
                class="h-11 w-full bg-indigo-700 text-white shadow-sm transition hover:bg-indigo-800 dark:bg-indigo-600 dark:hover:bg-indigo-500"
            >
                <LoaderCircle v-if="form.processing" class="mr-2 h-4 w-4 animate-spin" />
                Entrar
            </Button>
        </form>
    </AuthBase>
</template>
