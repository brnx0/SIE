<script setup lang="ts">
import InputError from '@/components/common/InputError.vue';
import TextLink from '@/components/common/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <AuthBase title="Bem-vindo de volta" description="Acesse sua conta para gerenciar a vida acadêmica.">
        <Head title="Entrar" />

        <div v-if="status" class="mb-4 rounded-md bg-emerald-50 px-4 py-3 text-center text-sm font-medium text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300">
            {{ status }}
        </div>

        <form @submit.prevent="submit" class="flex flex-col gap-5">
            <div class="grid gap-2">
                <Label for="email">E-mail</Label>
                <Input
                    id="email"
                    type="email"
                    required
                    autofocus
                    tabindex="1"
                    autocomplete="email"
                    v-model="form.email"
                    placeholder="seu@email.com"
                />
                <InputError :message="form.errors.email" />
            </div>

            <div class="grid gap-2">
                <div class="flex items-center justify-between">
                    <Label for="password">Senha</Label>
                    <TextLink v-if="canResetPassword" :href="route('password.request')" class="text-xs" tabindex="5">
                        Esqueci minha senha
                    </TextLink>
                </div>
                <Input
                    id="password"
                    type="password"
                    required
                    tabindex="2"
                    autocomplete="current-password"
                    v-model="form.password"
                    placeholder="••••••••"
                />
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
                class="w-full bg-sky-600 text-white hover:bg-sky-700 dark:bg-sky-500 dark:hover:bg-sky-400"
            >
                <LoaderCircle v-if="form.processing" class="mr-2 h-4 w-4 animate-spin" />
                Entrar
            </Button>

            <p class="text-center text-sm text-muted-foreground">
                Ainda não tem conta?
                <TextLink :href="route('register')" :tabindex="5">Cadastre-se</TextLink>
            </p>
        </form>
    </AuthBase>
</template>
