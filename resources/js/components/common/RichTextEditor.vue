<script setup lang="ts">
import CharacterCount from '@tiptap/extension-character-count';
import Underline from '@tiptap/extension-underline';
import StarterKit from '@tiptap/starter-kit';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import { Bold, Italic, List, ListOrdered, Strikethrough, Underline as UnderlineIcon } from 'lucide-vue-next';
import { onBeforeUnmount, watch } from 'vue';

const props = withDefaults(defineProps<{
    modelValue: string;
    limit?: number;
    invalid?: boolean;
}>(), {
    limit: 2500,
});

const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void;
}>();

const editor = useEditor({
    content: props.modelValue || '',
    extensions: [
        StarterKit,
        Underline,
        CharacterCount.configure({ limit: props.limit }),
    ],
    editorProps: {
        attributes: {
            class: 'prose prose-sm max-w-none min-h-[140px] px-3 py-2 focus:outline-none dark:prose-invert',
        },
    },
    onUpdate: ({ editor }) => {
        const html = editor.isEmpty ? '' : editor.getHTML();
        emit('update:modelValue', html);
    },
});

// Sincroniza quando o pai troca o valor (ex.: abrir edição de outro registro).
watch(() => props.modelValue, (v) => {
    if (!editor.value) return;
    if (v !== editor.value.getHTML()) {
        editor.value.commands.setContent(v || '', false);
    }
});

onBeforeUnmount(() => editor.value?.destroy());

const chars = () => editor.value?.storage.characterCount.characters() ?? 0;

const btn = (active: boolean) =>
    [
        'inline-flex size-8 items-center justify-center rounded transition',
        active ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/50 dark:text-indigo-300' : 'text-muted-foreground hover:bg-muted',
    ].join(' ');
</script>

<template>
    <div
        :class="[
            'overflow-hidden rounded-md border bg-background shadow-sm',
            invalid ? 'border-red-500 ring-1 ring-red-500' : 'border-input',
        ]"
    >
        <div v-if="editor" class="flex flex-wrap items-center gap-0.5 border-b bg-muted/40 px-2 py-1">
            <button type="button" :class="btn(editor.isActive('bold'))" title="Negrito" @click="editor.chain().focus().toggleBold().run()">
                <Bold class="size-4" />
            </button>
            <button type="button" :class="btn(editor.isActive('italic'))" title="Itálico" @click="editor.chain().focus().toggleItalic().run()">
                <Italic class="size-4" />
            </button>
            <button type="button" :class="btn(editor.isActive('underline'))" title="Sublinhado" @click="editor.chain().focus().toggleUnderline().run()">
                <UnderlineIcon class="size-4" />
            </button>
            <button type="button" :class="btn(editor.isActive('strike'))" title="Tachado" @click="editor.chain().focus().toggleStrike().run()">
                <Strikethrough class="size-4" />
            </button>
            <span class="mx-1 h-5 w-px bg-border"></span>
            <button type="button" :class="btn(editor.isActive('bulletList'))" title="Lista" @click="editor.chain().focus().toggleBulletList().run()">
                <List class="size-4" />
            </button>
            <button type="button" :class="btn(editor.isActive('orderedList'))" title="Lista numerada" @click="editor.chain().focus().toggleOrderedList().run()">
                <ListOrdered class="size-4" />
            </button>
        </div>

        <EditorContent :editor="editor" />

        <div class="flex justify-end border-t bg-muted/20 px-3 py-1 text-xs text-muted-foreground">
            {{ chars() }} / {{ limit }}
        </div>
    </div>
</template>
