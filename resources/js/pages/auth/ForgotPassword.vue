<script setup lang="ts">
import InputError from '@/components/common/InputError.vue';
import TextLink from '@/components/common/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';

defineProps<{
    status?: string;
}>();

const form = useForm({
    login: '',
});

const submit = () => {
    form.post(route('password.email'));
};
</script>

<template>
    <AuthLayout title="Esqueci minha senha" description="Informe seu login para receber um link de redefinição por e-mail.">
        <Head title="Esqueci minha senha" />

        <div
            v-if="status"
            class="mb-4 rounded-md bg-emerald-50 px-4 py-3 text-center text-sm font-medium text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300"
        >
            {{ status }}
        </div>

        <div class="space-y-6">
            <form @submit.prevent="submit" class="flex flex-col gap-5">
                <div class="grid gap-2">
                    <Label for="login">Login</Label>
                    <Input
                        id="login"
                        type="text"
                        name="login"
                        autocomplete="username"
                        v-model="form.login"
                        autofocus
                        placeholder="seu.login"
                    />
                    <InputError :message="form.errors.login" />
                </div>

                <Button
                    type="submit"
                    class="w-full bg-sky-600 text-white hover:bg-sky-700 dark:bg-sky-500 dark:hover:bg-sky-400"
                    :disabled="form.processing"
                >
                    <LoaderCircle v-if="form.processing" class="mr-2 h-4 w-4 animate-spin" />
                    Enviar link de redefinição
                </Button>
            </form>

            <div class="space-x-1 text-center text-sm text-muted-foreground">
                <span>Ou volte para</span>
                <TextLink :href="route('login')">entrar</TextLink>
            </div>
        </div>
    </AuthLayout>
</template>
