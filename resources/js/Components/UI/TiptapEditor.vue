<script setup lang="ts">
import { ref, watch, onBeforeUnmount } from 'vue';
import { useEditor, EditorContent } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import Image from '@tiptap/extension-image';
import Youtube from '@tiptap/extension-youtube';
import Link from '@tiptap/extension-link';
import {
    Bold,
    Italic,
    Strikethrough,
    Heading1,
    Heading2,
    Heading3,
    List,
    ListOrdered,
    Quote,
    Link as LinkIcon,
    Unlink,
    Image as ImageIcon,
    Video,
    Undo,
    Redo,
    Minus,
    Upload,
    X,
    Loader2,
} from 'lucide-vue-next';

const props = withDefaults(
    defineProps<{
        modelValue?: string;
        placeholder?: string;
        uploadUrl?: string;
    }>(),
    {
        modelValue: '',
        placeholder:
            'Mulai menulis artikel yang informatif dan menarik di sini...',
        uploadUrl: '/admin/artikel/upload-image',
    },
);

const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void;
}>();

// Modals state
const isYoutubeModalOpen = ref(false);
const youtubeUrlInput = ref('');

const isImageModalOpen = ref(false);
const imageUrlInput = ref('');
const isUploadingImage = ref(false);
const fileInputRef = ref<HTMLInputElement | null>(null);

const isLinkModalOpen = ref(false);
const linkUrlInput = ref('');

const editor = useEditor({
    content: props.modelValue,
    extensions: [
        StarterKit.configure({
            heading: {
                levels: [1, 2, 3],
            },
        }),
        Image.configure({
            inline: false,
            HTMLAttributes: {
                class: 'rounded-xl max-w-full h-auto my-4 shadow-sm mx-auto',
            },
        }),
        Youtube.configure({
            inline: false,
            width: 640,
            height: 360,
            HTMLAttributes: {
                class: 'rounded-xl overflow-hidden shadow-md my-6 mx-auto max-w-full aspect-video',
            },
        }),
        Link.configure({
            openOnClick: false,
            HTMLAttributes: {
                class: 'text-[#0b8043] dark:text-[#3fcb7a] font-medium underline underline-offset-2 hover:text-[#096836] dark:hover:text-[#34aa66]',
            },
        }),
    ],
    editorProps: {
        attributes: {
            class: 'prose prose-emerald dark:prose-invert max-w-none focus:outline-none min-h-[320px] p-4 text-[#2c3e50] dark:text-[#f0efea] bg-white dark:bg-[#1c1c1c] leading-relaxed font-sans',
        },
    },
    onUpdate: ({ editor }) => {
        emit('update:modelValue', editor.getHTML());
    },
});

// Sync from outside prop if changed externally
watch(
    () => props.modelValue,
    (newValue) => {
        const isSame = editor.value?.getHTML() === newValue;
        if (!isSame && editor.value) {
            editor.value.commands.setContent(newValue || '', {
                emitUpdate: false,
            });
        }
    },
);

onBeforeUnmount(() => {
    editor.value?.destroy();
});

// YouTube embed handler
const openYoutubeModal = () => {
    youtubeUrlInput.value = '';
    isYoutubeModalOpen.value = true;
};

const insertYoutube = () => {
    if (!youtubeUrlInput.value.trim() || !editor.value) return;
    editor.value.commands.setYoutubeVideo({
        src: youtubeUrlInput.value.trim(),
    });
    isYoutubeModalOpen.value = false;
    youtubeUrlInput.value = '';
};

// Image embed & upload handler
const openImageModal = () => {
    imageUrlInput.value = '';
    isImageModalOpen.value = true;
};

const insertImageByUrl = () => {
    if (!imageUrlInput.value.trim() || !editor.value) return;
    editor.value
        .chain()
        .focus()
        .setImage({ src: imageUrlInput.value.trim() })
        .run();
    isImageModalOpen.value = false;
    imageUrlInput.value = '';
};

const triggerFileInput = () => {
    fileInputRef.value?.click();
};

const handleImageUpload = async (event: Event) => {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];
    if (!file || !editor.value) return;

    try {
        isUploadingImage.value = true;
        const formData = new FormData();
        formData.append('image', file);

        const csrfToken =
            (
                document.querySelector(
                    'meta[name="csrf-token"]',
                ) as HTMLMetaElement
            )?.content || '';

        const response = await fetch(props.uploadUrl, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken,
                Accept: 'application/json',
            },
        });

        if (!response.ok) {
            throw new Error('Upload failed');
        }

        const data = await response.json();

        if (data?.url) {
            editor.value.chain().focus().setImage({ src: data.url }).run();
            isImageModalOpen.value = false;
        }
    } catch (error) {
        console.error('Failed to upload inline image:', error);
        alert(
            'Gagal mengunggah foto. Pastikan format gambar sesuai (JPG, PNG, WebP) dan ukuran maksimal 5MB.',
        );
    } finally {
        isUploadingImage.value = false;
        if (target) target.value = '';
    }
};

// Link handler
const openLinkModal = () => {
    if (!editor.value) return;
    const previousUrl = editor.value.getAttributes('link').href || '';
    linkUrlInput.value = previousUrl;
    isLinkModalOpen.value = true;
};

const applyLink = () => {
    if (!editor.value) return;
    if (linkUrlInput.value.trim() === '') {
        editor.value.chain().focus().extendMarkRange('link').unsetLink().run();
    } else {
        editor.value
            .chain()
            .focus()
            .extendMarkRange('link')
            .setLink({ href: linkUrlInput.value.trim(), target: '_blank' })
            .run();
    }
    isLinkModalOpen.value = false;
};
</script>

<template>
    <div
        class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm transition-all focus-within:border-[#0b8043] focus-within:ring-2 focus-within:ring-[#0b8043]/10 dark:border-[#2e2e2e] dark:bg-[#1c1c1c] dark:focus-within:border-[#3fcb7a] dark:focus-within:ring-[#3fcb7a]/15"
    >
        <!-- Editor Toolbar -->
        <div
            v-if="editor"
            class="flex flex-wrap items-center gap-1 border-b border-gray-200 bg-[#fafaf9] p-2 select-none dark:border-[#2e2e2e] dark:bg-[#262626]"
        >
            <!-- Text Styles -->
            <button
                type="button"
                @click="editor.chain().focus().toggleBold().run()"
                :class="[
                    'cursor-pointer rounded-lg p-2 text-sm font-medium transition',
                    editor.isActive('bold')
                        ? 'bg-[#0b8043] text-white shadow-sm dark:bg-[#3fcb7a] dark:text-[#121212]'
                        : 'text-gray-600 hover:bg-gray-200 dark:text-[#a3a3a3] dark:hover:bg-[#3d3d3d] dark:hover:text-[#f0efea]',
                ]"
                title="Tebal (Bold)"
            >
                <Bold class="h-4 w-4" />
            </button>
            <button
                type="button"
                @click="editor.chain().focus().toggleItalic().run()"
                :class="[
                    'cursor-pointer rounded-lg p-2 text-sm transition',
                    editor.isActive('italic')
                        ? 'bg-[#0b8043] text-white shadow-sm dark:bg-[#3fcb7a] dark:text-[#121212]'
                        : 'text-gray-600 hover:bg-gray-200 dark:text-[#a3a3a3] dark:hover:bg-[#3d3d3d] dark:hover:text-[#f0efea]',
                ]"
                title="Miring (Italic)"
            >
                <Italic class="h-4 w-4" />
            </button>
            <button
                type="button"
                @click="editor.chain().focus().toggleStrike().run()"
                :class="[
                    'cursor-pointer rounded-lg p-2 text-sm transition',
                    editor.isActive('strike')
                        ? 'bg-[#0b8043] text-white shadow-sm dark:bg-[#3fcb7a] dark:text-[#121212]'
                        : 'text-gray-600 hover:bg-gray-200 dark:text-[#a3a3a3] dark:hover:bg-[#3d3d3d] dark:hover:text-[#f0efea]',
                ]"
                title="Coret (Strike)"
            >
                <Strikethrough class="h-4 w-4" />
            </button>

            <div class="mx-1 h-5 w-px bg-gray-300 dark:bg-[#3d3d3d]"></div>

            <!-- Headings -->
            <button
                type="button"
                @click="
                    editor.chain().focus().toggleHeading({ level: 1 }).run()
                "
                :class="[
                    'flex cursor-pointer items-center gap-0.5 rounded-lg p-2 text-xs font-bold transition',
                    editor.isActive('heading', { level: 1 })
                        ? 'bg-[#0b8043] text-white shadow-sm dark:bg-[#3fcb7a] dark:text-[#121212]'
                        : 'text-gray-600 hover:bg-gray-200 dark:text-[#a3a3a3] dark:hover:bg-[#3d3d3d] dark:hover:text-[#f0efea]',
                ]"
                title="Judul Utama (H1)"
            >
                <Heading1 class="h-4 w-4" />
            </button>
            <button
                type="button"
                @click="
                    editor.chain().focus().toggleHeading({ level: 2 }).run()
                "
                :class="[
                    'flex cursor-pointer items-center gap-0.5 rounded-lg p-2 text-xs font-bold transition',
                    editor.isActive('heading', { level: 2 })
                        ? 'bg-[#0b8043] text-white shadow-sm dark:bg-[#3fcb7a] dark:text-[#121212]'
                        : 'text-gray-600 hover:bg-gray-200 dark:text-[#a3a3a3] dark:hover:bg-[#3d3d3d] dark:hover:text-[#f0efea]',
                ]"
                title="Sub Judul (H2)"
            >
                <Heading2 class="h-4 w-4" />
            </button>
            <button
                type="button"
                @click="
                    editor.chain().focus().toggleHeading({ level: 3 }).run()
                "
                :class="[
                    'flex cursor-pointer items-center gap-0.5 rounded-lg p-2 text-xs font-bold transition',
                    editor.isActive('heading', { level: 3 })
                        ? 'bg-[#0b8043] text-white shadow-sm dark:bg-[#3fcb7a] dark:text-[#121212]'
                        : 'text-gray-600 hover:bg-gray-200 dark:text-[#a3a3a3] dark:hover:bg-[#3d3d3d] dark:hover:text-[#f0efea]',
                ]"
                title="Poin Judul (H3)"
            >
                <Heading3 class="h-4 w-4" />
            </button>

            <div class="mx-1 h-5 w-px bg-gray-300 dark:bg-[#3d3d3d]"></div>

            <!-- Lists -->
            <button
                type="button"
                @click="editor.chain().focus().toggleBulletList().run()"
                :class="[
                    'cursor-pointer rounded-lg p-2 text-sm transition',
                    editor.isActive('bulletList')
                        ? 'bg-[#0b8043] text-white shadow-sm dark:bg-[#3fcb7a] dark:text-[#121212]'
                        : 'text-gray-600 hover:bg-gray-200 dark:text-[#a3a3a3] dark:hover:bg-[#3d3d3d] dark:hover:text-[#f0efea]',
                ]"
                title="Daftar Poin (Bullet List)"
            >
                <List class="h-4 w-4" />
            </button>
            <button
                type="button"
                @click="editor.chain().focus().toggleOrderedList().run()"
                :class="[
                    'cursor-pointer rounded-lg p-2 text-sm transition',
                    editor.isActive('orderedList')
                        ? 'bg-[#0b8043] text-white shadow-sm dark:bg-[#3fcb7a] dark:text-[#121212]'
                        : 'text-gray-600 hover:bg-gray-200 dark:text-[#a3a3a3] dark:hover:bg-[#3d3d3d] dark:hover:text-[#f0efea]',
                ]"
                title="Daftar Nomor (Numbered List)"
            >
                <ListOrdered class="h-4 w-4" />
            </button>
            <button
                type="button"
                @click="editor.chain().focus().toggleBlockquote().run()"
                :class="[
                    'cursor-pointer rounded-lg p-2 text-sm transition',
                    editor.isActive('blockquote')
                        ? 'bg-[#0b8043] text-white shadow-sm dark:bg-[#3fcb7a] dark:text-[#121212]'
                        : 'text-gray-600 hover:bg-gray-200 dark:text-[#a3a3a3] dark:hover:bg-[#3d3d3d] dark:hover:text-[#f0efea]',
                ]"
                title="Kutipan (Quote)"
            >
                <Quote class="h-4 w-4" />
            </button>
            <button
                type="button"
                @click="editor.chain().focus().setHorizontalRule().run()"
                class="cursor-pointer rounded-lg p-2 text-sm text-gray-600 transition hover:bg-gray-200 dark:text-[#a3a3a3] dark:hover:bg-[#3d3d3d] dark:hover:text-[#f0efea]"
                title="Garis Pemisah"
            >
                <Minus class="h-4 w-4" />
            </button>

            <div class="mx-1 h-5 w-px bg-gray-300 dark:bg-[#3d3d3d]"></div>

            <!-- Links -->
            <button
                type="button"
                @click="openLinkModal"
                :class="[
                    'cursor-pointer rounded-lg p-2 text-sm transition',
                    editor.isActive('link')
                        ? 'bg-[#0b8043] text-white shadow-sm dark:bg-[#3fcb7a] dark:text-[#121212]'
                        : 'text-gray-600 hover:bg-gray-200 dark:text-[#a3a3a3] dark:hover:bg-[#3d3d3d] dark:hover:text-[#f0efea]',
                ]"
                title="Sisipkan Tautan (Link)"
            >
                <LinkIcon class="h-4 w-4" />
            </button>
            <button
                v-if="editor.isActive('link')"
                type="button"
                @click="editor.chain().focus().unsetLink().run()"
                class="cursor-pointer rounded-lg p-2 text-sm text-red-500 transition hover:bg-red-50 dark:hover:bg-red-950/40"
                title="Hapus Tautan"
            >
                <Unlink class="h-4 w-4" />
            </button>

            <div class="mx-1 h-5 w-px bg-gray-300 dark:bg-[#3d3d3d]"></div>

            <!-- Media: Photo & YouTube -->
            <button
                type="button"
                @click="openImageModal"
                class="flex cursor-pointer items-center gap-1 rounded-lg p-2 text-sm font-medium text-gray-700 transition hover:bg-emerald-50 hover:text-[#0b8043] dark:text-[#f0efea] dark:hover:bg-[#17321f] dark:hover:text-[#3fcb7a]"
                title="Sisipkan Foto / Gambar"
            >
                <ImageIcon
                    class="h-4 w-4 text-emerald-600 dark:text-[#3fcb7a]"
                />
                <span class="text-xs">Foto</span>
            </button>
            <button
                type="button"
                @click="openYoutubeModal"
                class="flex cursor-pointer items-center gap-1 rounded-lg p-2 text-sm font-medium text-gray-700 transition hover:bg-red-50 hover:text-red-600 dark:text-[#f0efea] dark:hover:bg-red-950/40 dark:hover:text-red-400"
                title="Sisipkan Video YouTube"
            >
                <Video class="h-4 w-4 text-red-600 dark:text-red-400" />
                <span class="text-xs">YouTube</span>
            </button>

            <div class="mx-1 h-5 w-px bg-gray-300 dark:bg-[#3d3d3d]"></div>

            <!-- History: Undo / Redo -->
            <button
                type="button"
                @click="editor.chain().focus().undo().run()"
                :disabled="!editor.can().undo()"
                class="cursor-pointer rounded-lg p-2 text-sm text-gray-600 transition hover:bg-gray-200 disabled:opacity-30 disabled:hover:bg-transparent dark:text-[#a3a3a3] dark:hover:bg-[#3d3d3d] dark:hover:text-[#f0efea]"
                title="Urungkan (Undo)"
            >
                <Undo class="h-4 w-4" />
            </button>
            <button
                type="button"
                @click="editor.chain().focus().redo().run()"
                :disabled="!editor.can().redo()"
                class="cursor-pointer rounded-lg p-2 text-sm text-gray-600 transition hover:bg-gray-200 disabled:opacity-30 disabled:hover:bg-transparent dark:text-[#a3a3a3] dark:hover:bg-[#3d3d3d] dark:hover:text-[#f0efea]"
                title="Ulangi (Redo)"
            >
                <Redo class="h-4 w-4" />
            </button>
        </div>

        <!-- Editor Content Area -->
        <EditorContent
            :editor="editor"
            class="min-h-[360px] bg-white text-[#2c3e50] dark:bg-[#1c1c1c] dark:text-[#f0efea]"
        />

        <!-- Hidden input for file upload -->
        <input
            ref="fileInputRef"
            type="file"
            accept="image/png,image/jpeg,image/webp,image/gif"
            class="hidden"
            @change="handleImageUpload"
        />

        <!-- Modal Sisipkan YouTube -->
        <div
            v-if="isYoutubeModalOpen"
            class="animate-in fade-in fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-xs duration-150"
        >
            <div
                class="w-full max-w-md rounded-2xl border border-gray-100 bg-white p-6 shadow-xl dark:border-[#2e2e2e] dark:bg-[#1c1c1c]"
            >
                <div class="mb-4 flex items-center justify-between">
                    <div
                        class="flex items-center gap-2 text-lg font-semibold text-gray-800 dark:text-[#f0efea]"
                    >
                        <Video class="h-5 w-5 text-red-600 dark:text-red-400" />
                        <span>Sisipkan Video YouTube</span>
                    </div>
                    <button
                        type="button"
                        @click="isYoutubeModalOpen = false"
                        class="cursor-pointer rounded-lg p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:text-[#a3a3a3] dark:hover:bg-[#262626] dark:hover:text-[#f0efea]"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </div>
                <p class="mb-4 text-sm text-gray-600 dark:text-[#a3a3a3]">
                    Masukkan URL video YouTube lengkap (contoh:
                    <code
                        class="rounded bg-gray-100 px-1 py-0.5 text-xs text-gray-800 dark:bg-[#262626] dark:text-[#f0efea]"
                        >https://www.youtube.com/watch?v=...</code
                    >).
                </p>
                <input
                    v-model="youtubeUrlInput"
                    type="url"
                    placeholder="https://www.youtube.com/watch?v=..."
                    class="mb-4 w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:border-[#0b8043] focus:ring-2 focus:ring-[#0b8043]/15 focus:outline-none dark:border-[#3d3d3d] dark:bg-[#262626] dark:text-[#f0efea] dark:placeholder-[#737373] dark:focus:border-[#3fcb7a]"
                    @keyup.enter="insertYoutube"
                />
                <div class="flex items-center justify-end gap-2">
                    <button
                        type="button"
                        @click="isYoutubeModalOpen = false"
                        class="cursor-pointer rounded-xl px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100 dark:text-[#a3a3a3] dark:hover:bg-[#262626]"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        @click="insertYoutube"
                        class="cursor-pointer rounded-xl bg-[#0b8043] px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-[#096836] dark:bg-[#3fcb7a] dark:text-[#121212] dark:hover:bg-[#34aa66]"
                    >
                        Sisipkan Video
                    </button>
                </div>
            </div>
        </div>

        <!-- Modal Sisipkan Foto -->
        <div
            v-if="isImageModalOpen"
            class="animate-in fade-in fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-xs duration-150"
        >
            <div
                class="w-full max-w-md rounded-2xl border border-gray-100 bg-white p-6 shadow-xl dark:border-[#2e2e2e] dark:bg-[#1c1c1c]"
            >
                <div class="mb-4 flex items-center justify-between">
                    <div
                        class="flex items-center gap-2 text-lg font-semibold text-gray-800 dark:text-[#f0efea]"
                    >
                        <ImageIcon
                            class="h-5 w-5 text-[#0b8043] dark:text-[#3fcb7a]"
                        />
                        <span>Sisipkan Foto ke Artikel</span>
                    </div>
                    <button
                        type="button"
                        @click="isImageModalOpen = false"
                        class="cursor-pointer rounded-lg p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:text-[#a3a3a3] dark:hover:bg-[#262626] dark:hover:text-[#f0efea]"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </div>

                <!-- Opsi 1: Upload dari Perangkat -->
                <div class="mb-4">
                    <label
                        class="mb-2 block text-xs font-semibold tracking-wider text-gray-600 uppercase dark:text-[#a3a3a3]"
                        >Unggah dari Komputer</label
                    >
                    <button
                        type="button"
                        @click="triggerFileInput"
                        :disabled="isUploadingImage"
                        class="flex w-full cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed border-gray-200 bg-transparent p-4 text-gray-500 transition hover:border-[#0b8043] hover:bg-emerald-50/40 hover:text-[#0b8043] dark:border-[#3d3d3d] dark:bg-[#262626] dark:text-[#a3a3a3] dark:hover:border-[#3fcb7a] dark:hover:bg-[#17321f]/30 dark:hover:text-[#3fcb7a]"
                    >
                        <Loader2
                            v-if="isUploadingImage"
                            class="mb-1 h-6 w-6 animate-spin text-[#0b8043] dark:text-[#3fcb7a]"
                        />
                        <Upload
                            v-else
                            class="mb-1 h-6 w-6 text-gray-400 dark:text-[#a3a3a3]"
                        />
                        <span class="text-sm font-medium">{{
                            isUploadingImage
                                ? 'Mengunggah foto...'
                                : 'Pilih File Gambar'
                        }}</span>
                        <span
                            class="mt-0.5 text-xs text-gray-400 dark:text-[#737373]"
                            >PNG, JPG, WebP maksimal 5MB</span
                        >
                    </button>
                </div>

                <div class="my-3 flex items-center">
                    <div
                        class="grow border-t border-gray-200 dark:border-[#2e2e2e]"
                    ></div>
                    <span
                        class="px-2 text-xs text-gray-400 uppercase dark:text-[#737373]"
                        >atau dari URL</span
                    >
                    <div
                        class="grow border-t border-gray-200 dark:border-[#2e2e2e]"
                    ></div>
                </div>

                <!-- Opsi 2: Link URL Eksternal -->
                <div class="mb-5">
                    <input
                        v-model="imageUrlInput"
                        type="url"
                        placeholder="https://images.unsplash.com/photo-..."
                        class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:border-[#0b8043] focus:ring-2 focus:ring-[#0b8043]/15 focus:outline-none dark:border-[#3d3d3d] dark:bg-[#262626] dark:text-[#f0efea] dark:placeholder-[#737373] dark:focus:border-[#3fcb7a]"
                        @keyup.enter="insertImageByUrl"
                    />
                </div>

                <div class="flex items-center justify-end gap-2">
                    <button
                        type="button"
                        @click="isImageModalOpen = false"
                        class="cursor-pointer rounded-xl px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100 dark:text-[#a3a3a3] dark:hover:bg-[#262626]"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        @click="insertImageByUrl"
                        :disabled="!imageUrlInput.trim()"
                        class="cursor-pointer rounded-xl bg-[#0b8043] px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-[#096836] disabled:opacity-40 dark:bg-[#3fcb7a] dark:text-[#121212] dark:hover:bg-[#34aa66]"
                    >
                        Gunakan URL
                    </button>
                </div>
            </div>
        </div>

        <!-- Modal Tautan (Link) -->
        <div
            v-if="isLinkModalOpen"
            class="animate-in fade-in fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-xs duration-150"
        >
            <div
                class="w-full max-w-md rounded-2xl border border-gray-100 bg-white p-6 shadow-xl dark:border-[#2e2e2e] dark:bg-[#1c1c1c]"
            >
                <div class="mb-4 flex items-center justify-between">
                    <div
                        class="flex items-center gap-2 text-lg font-semibold text-gray-800 dark:text-[#f0efea]"
                    >
                        <LinkIcon
                            class="h-5 w-5 text-[#0b8043] dark:text-[#3fcb7a]"
                        />
                        <span>Sisipkan Tautan URL</span>
                    </div>
                    <button
                        type="button"
                        @click="isLinkModalOpen = false"
                        class="cursor-pointer rounded-lg p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:text-[#a3a3a3] dark:hover:bg-[#262626] dark:hover:text-[#f0efea]"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </div>
                <input
                    v-model="linkUrlInput"
                    type="url"
                    placeholder="https://example.com"
                    class="mb-4 w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:border-[#0b8043] focus:ring-2 focus:ring-[#0b8043]/15 focus:outline-none dark:border-[#3d3d3d] dark:bg-[#262626] dark:text-[#f0efea] dark:placeholder-[#737373] dark:focus:border-[#3fcb7a]"
                    @keyup.enter="applyLink"
                />
                <div class="flex items-center justify-end gap-2">
                    <button
                        type="button"
                        @click="isLinkModalOpen = false"
                        class="cursor-pointer rounded-xl px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100 dark:text-[#a3a3a3] dark:hover:bg-[#262626]"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        @click="applyLink"
                        class="cursor-pointer rounded-xl bg-[#0b8043] px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-[#096836] dark:bg-[#3fcb7a] dark:text-[#121212] dark:hover:bg-[#34aa66]"
                    >
                        Terapkan
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style>
/* Styling khusus untuk konten TipTap editor */
.ProseMirror,
.tiptap {
    outline: none !important;
}

.ProseMirror p.is-editor-empty:first-child::before,
.tiptap p.is-editor-empty:first-child::before {
    color: #9ca3af;
    content: attr(data-placeholder);
    float: left;
    height: 0;
    pointer-events: none;
}

.dark .ProseMirror p.is-editor-empty:first-child::before,
.dark .tiptap p.is-editor-empty:first-child::before {
    color: #737373 !important;
}

.ProseMirror h1,
.tiptap h1 {
    font-size: 1.875rem;
    line-height: 2.25rem;
    font-weight: 800;
    margin-top: 1.5rem;
    margin-bottom: 0.75rem;
    color: #191919;
}

.ProseMirror h2,
.tiptap h2 {
    font-size: 1.5rem;
    line-height: 2rem;
    font-weight: 700;
    margin-top: 1.25rem;
    margin-bottom: 0.5rem;
    color: #191919;
}

.ProseMirror h3,
.tiptap h3 {
    font-size: 1.25rem;
    line-height: 1.75rem;
    font-weight: 600;
    margin-top: 1rem;
    margin-bottom: 0.5rem;
    color: #191919;
}

.ProseMirror p,
.tiptap p {
    margin: 0.75rem 0;
    line-height: 1.75;
}

.ProseMirror blockquote,
.tiptap blockquote {
    border-left: 4px solid #0b8043;
    padding-left: 1rem;
    font-style: italic;
    color: #4b5563;
    margin: 1rem 0;
}

.ProseMirror ul,
.tiptap ul {
    list-style-type: disc;
    padding-left: 1.5rem;
    margin: 0.75rem 0;
}

.ProseMirror ol,
.tiptap ol {
    list-style-type: decimal;
    padding-left: 1.5rem;
    margin: 0.75rem 0;
}

.ProseMirror hr,
.tiptap hr {
    border-color: #e5e7eb;
    margin: 1.5rem 0;
}

/* Dark mode overrides for ProseMirror / TipTap content */
.dark .ProseMirror,
.dark .tiptap {
    color: #f0efea !important;
    background-color: #1c1c1c !important;
}

.dark .ProseMirror h1,
.dark .ProseMirror h2,
.dark .ProseMirror h3,
.dark .ProseMirror strong,
.dark .tiptap h1,
.dark .tiptap h2,
.dark .tiptap h3,
.dark .tiptap strong {
    color: #f0efea !important;
}

.dark .ProseMirror p,
.dark .ProseMirror li,
.dark .tiptap p,
.dark .tiptap li {
    color: #d4d4d4 !important;
}

.dark .ProseMirror a,
.dark .tiptap a {
    color: #3fcb7a !important;
}

.dark .ProseMirror blockquote,
.dark .tiptap blockquote {
    border-left-color: #3fcb7a !important;
    color: #a3a3a3 !important;
}

.dark .ProseMirror hr,
.dark .tiptap hr {
    border-color: #2e2e2e !important;
}

.dark .ProseMirror code,
.dark .tiptap code {
    background-color: #262626 !important;
    color: #3fcb7a !important;
    padding: 0.15rem 0.35rem;
    border-radius: 0.375rem;
}
</style>
