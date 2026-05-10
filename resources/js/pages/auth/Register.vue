<script setup lang="ts">
import InputError from '@/components/common/InputError.vue';
import TextLink from '@/components/common/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <AuthBase title="Criar nova conta" description="Cadastre-se para acessar o sistema acadêmico.">
        <Head title="Cadastro" />

        <form @submit.prevent="submit" class="flex flex-col gap-5">
            <div class="grid gap-2">
                <Label for="name">Nome completo</Label>
                <Input id="name" type="text" required autofocus tabindex="1" autocomplete="name" v-model="form.name" placeholder="Como você quer ser chamado(a)" />
                <InputError :message="form.errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="email">E-mail</Label>
                <Input id="email" type="email" required tabindex="2" autocomplete="email" v-model="form.email" placeholder="seu@email.com" />
                <InputError :message="form.errors.email" />
            </div>

            <div class="grid gap-2">
                <Label for="password">Senha</Label>
                <Input id="password" type="password" required tabindex="3" autocomplete="new-password" v-model="form.password" placeholder="Mínimo 8 caracteres" />
                <InputError :message="form.errors.password" />
            </div>

            <div class="grid gap-2">
                <Label for="password_confirmation">Confirmar senha</Label>
                <Input
                    id="password_confirmation"
                    type="password"
                    required
                    tabindex="4"
                    autocomplete="new-password"
                    v-model="form.password_confirmation"
                    placeholder="Repita a senha"
                />
                <InputError :message="form.errors.password_confirmation" />
            </div>

            <Button
                type="submit"
                tabindex="5"
                :disabled="form.processing"
                class="w-full bg-sky-600 text-white hover:bg-sky-700 dark:bg-sky-500 dark:hover:bg-sky-400"
            >
                <LoaderCircle v-if="form.processing" class="mr-2 h-4 w-4 animate-spin" />
                Criar minha conta
            </Button>

            <p class="text-center text-sm text-muted-foreground">
                Já possui cadastro?
                <TextLink :href="route('login')" class="underline underline-offset-4" tabindex="6">Entrar</TextLink>
            </p>
        </form>
    </AuthBase>
</template>
