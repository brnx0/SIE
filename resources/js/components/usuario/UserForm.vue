<script setup lang="ts">
import FuncionarioCombobox from '@/components/usuario/FuncionarioCombobox.vue';
import InputError from '@/components/common/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Link, useForm } from '@inertiajs/vue3';
import { LoaderCircle, Save, X } from 'lucide-vue-next';
import { computed } from 'vue';

interface UserFormData {
    name: string;
    email: string;
    roles: string[];
    phone: string;
    active: boolean;
    fun_id: number | null;
    password?: string;
    password_confirmation?: string;
    [key: string]: any;
}

interface FuncionarioInitial { fun_id: number; fun_nome: string; fun_cpf: string | null; }

const props = defineProps<{
    initial?: Partial<UserFormData> & { id?: number };
    roles: Record<string, string>;
    initialFuncionario?: FuncionarioInitial | null;
    mode: 'create' | 'edit';
}>();

const form = useForm<UserFormData>({
    name:                  props.initial?.name ?? '',
    email:                 props.initial?.email ?? '',
    roles:                 props.initial?.roles ?? [],
    phone:                 props.initial?.phone ?? '',
    active:                props.initial?.active ?? true,
    fun_id:                props.initial?.fun_id ?? null,
    password:              '',
    password_confirmation: '',
});

const availableRoles = computed(() =>
    Object.entries(props.roles).filter(([key]) => !form.roles.includes(key))
);

const addRole = (key: string) => {
    if (!form.roles.includes(key)) {
        form.roles.push(key);
    }
};

const removeRole = (key: string) => {
    form.roles = form.roles.filter(r => r !== key);
};

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

            <div class="grid gap-2 sm:col-span-2">
                <Label>Perfis de acesso</Label>
                <div v-if="form.roles.length" class="flex flex-wrap gap-2 mb-1">
                    <span
                        v-for="key in form.roles"
                        :key="key"
                        class="inline-flex items-center gap-1 rounded-full bg-sky-100 px-3 py-1 text-xs font-medium text-sky-700 dark:bg-sky-900/40 dark:text-sky-300"
                    >
                        {{ roles[key] ?? key }}
                        <button type="button" class="ml-0.5 rounded-full p-0.5 hover:bg-sky-200 dark:hover:bg-sky-800" @click="removeRole(key)">
                            <X class="size-3" />
                        </button>
                    </span>
                </div>
                <select
                    v-if="availableRoles.length"
                    class="h-9 rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-ring"
                    @change="(e) => { addRole((e.target as HTMLSelectElement).value); (e.target as HTMLSelectElement).selectedIndex = 0; }"
                >
                    <option value="" disabled selected>Adicionar perfil...</option>
                    <option v-for="[key, label] in availableRoles" :key="key" :value="key">{{ label }}</option>
                </select>
                <p v-if="!form.roles.length" class="text-xs text-destructive">Selecione ao menos um perfil de acesso.</p>
                <InputError :message="(form.errors as any).roles || (form.errors as any)['roles.0']" />
            </div>

            <!-- Colaborador vinculado -->
            <div class="grid gap-2 sm:col-span-2">
                <Label>Colaborador vinculado <span class="text-muted-foreground font-normal">(opcional)</span></Label>
                <FuncionarioCombobox
                    :model-value="form.fun_id"
                    :initial="initialFuncionario ?? null"
                    :invalid="!!form.errors.fun_id"
                    placeholder="Buscar colaborador..."
                    @update:model-value="(v) => (form.fun_id = v)"
                />
                <p class="text-xs text-muted-foreground">Vincula este usuário a um colaborador cadastrado para uso futuro em filtros e relatórios.</p>
                <InputError :message="form.errors.fun_id" />
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
                <Button type="button" variant="outline">Voltar para listagem</Button>
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
