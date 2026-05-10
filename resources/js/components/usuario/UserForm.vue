<script setup lang="ts">
import InputError from '@/components/common/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Link, useForm } from '@inertiajs/vue3';
import { LoaderCircle, Save } from 'lucide-vue-next';
import { computed } from 'vue';

interface UserFormData {
    name: string;
    email: string;
    role: string;
    phone: string;
    active: boolean;
    password?: string;
    password_confirmation?: string;
    [key: string]: any;
}

const props = defineProps<{
    initial?: Partial<UserFormData> & { id?: number };
    roles: Record<string, string>;
    mode: 'create' | 'edit';
}>();

const form = useForm<UserFormData>({
    name: props.initial?.name ?? '',
    email: props.initial?.email ?? '',
    role: props.initial?.role ?? '',
    phone: props.initial?.phone ?? '',
    active: props.initial?.active ?? true,
    password: '',
    password_confirmation: '',
});

const submitLabel = computed(() => (props.mode === 'create' ? 'Cadastrar usuário' : 'Salvar alterações'));

const submit = () => {
    if (props.mode === 'create') {
        form.post('/users');
    } else {
        form.put(`/users/${props.initial?.id}`);
    }
};
</script>

<template>
    <form @submit.prevent="submit" class="grid gap-6">
        <div class="grid gap-4 rounded-xl border bg-card p-6 shadow-sm sm:grid-cols-2">
            <div class="grid gap-2 sm:col-span-2">
                <Label for="name">Nome completo</Label>
                <Input id="name" v-model="form.name" required autofocus placeholder="Ex.: Maria da Silva" />
                <InputError :message="form.errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="email">E-mail</Label>
                <Input id="email" type="email" v-model="form.email" required placeholder="email@instituicao.edu.br" />
                <InputError :message="form.errors.email" />
            </div>

            <div class="grid gap-2">
                <Label for="phone">Telefone</Label>
                <Input id="phone" v-model="form.phone" placeholder="(00) 00000-0000" />
                <InputError :message="form.errors.phone" />
            </div>

            <div class="grid gap-2">
                <Label for="role">Perfil</Label>
                <select
                    id="role"
                    v-model="form.role"
                    class="h-9 rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-ring"
                >
                    <option v-for="(label, key) in roles" :key="key" :value="key">{{ label }}</option>
                </select>
                <InputError :message="form.errors.role" />
            </div>

            <div class="flex items-end">
                <Label for="active" class="flex items-center gap-2 text-sm font-normal">
                    <Checkbox id="active" v-model:checked="form.active" />
                    Usuário ativo
                </Label>
            </div>
        </div>

        <div class="grid gap-4 rounded-xl border bg-card p-6 shadow-sm sm:grid-cols-2">
            <div class="sm:col-span-2">
                <h3 class="text-sm font-semibold">Senha de acesso</h3>
                <p class="text-xs text-muted-foreground">
                    {{ mode === 'edit' ? 'Deixe em branco para manter a senha atual.' : 'Mínimo de 8 caracteres.' }}
                </p>
            </div>
            <div class="grid gap-2">
                <Label for="password">Senha</Label>
                <Input id="password" type="password" v-model="form.password" :required="mode === 'create'" autocomplete="new-password" />
                <InputError :message="form.errors.password" />
            </div>
            <div class="grid gap-2">
                <Label for="password_confirmation">Confirmar senha</Label>
                <Input id="password_confirmation" type="password" v-model="form.password_confirmation" :required="mode === 'create'" autocomplete="new-password" />
            </div>
        </div>

        <div class="flex justify-end gap-2">
            <Link href="/users">
                <Button type="button" variant="outline">Cancelar</Button>
            </Link>
            <Button
                type="submit"
                :disabled="form.processing"
                class="bg-sky-600 text-white hover:bg-sky-700 dark:bg-sky-500 dark:hover:bg-sky-400"
            >
                <LoaderCircle v-if="form.processing" class="mr-2 size-4 animate-spin" />
                <Save v-else class="mr-2 size-4" />
                {{ submitLabel }}
            </Button>
        </div>
    </form>
</template>
