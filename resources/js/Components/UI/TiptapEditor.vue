<script setup lang="ts">
import { ref, watch, onBeforeUnmount } from "vue";
import { useEditor, EditorContent } from "@tiptap/vue-3";
import StarterKit from "@tiptap/starter-kit";
import Image from "@tiptap/extension-image";
import Youtube from "@tiptap/extension-youtube";
import Link from "@tiptap/extension-link";
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
} from "lucide-vue-next";

const props = withDefaults(
    defineProps<{
        modelValue?: string;
        placeholder?: string;
        uploadUrl?: string;
    }>(),
    {
        modelValue: "",
        placeholder:
            "Mulai menulis artikel yang informatif dan menarik di sini...",
        uploadUrl: "/admin/artikel/upload-image",
    },
);

const emit = defineEmits<{
    (e: "update:modelValue", value: string): void;
}>();

// Modals state
const isYoutubeModalOpen = ref(false);
const youtubeUrlInput = ref("");

const isImageModalOpen = ref(false);
const imageUrlInput = ref("");
const isUploadingImage = ref(false);
const fileInputRef = ref<HTMLInputElement | null>(null);

const isLinkModalOpen = ref(false);
const linkUrlInput = ref("");

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
                class: "rounded-xl max-w-full h-auto my-4 shadow-sm mx-auto",
            },
        }),
        Youtube.configure({
            inline: false,
            width: 640,
            height: 360,
            HTMLAttributes: {
                class: "rounded-xl overflow-hidden shadow-md my-6 mx-auto max-w-full aspect-video",
            },
        }),
        Link.configure({
            openOnClick: false,
            HTMLAttributes: {
                class: "text-[#0b8043] font-medium underline underline-offset-2 hover:text-[#096836]",
            },
        }),
    ],
    editorProps: {
        attributes: {
            class: "prose prose-emerald max-w-none focus:outline-none min-h-[320px] p-4 text-[#2c3e50] leading-relaxed font-sans",
        },
    },
    onUpdate: ({ editor }) => {
        emit("update:modelValue", editor.getHTML());
    },
});

// Sync from outside prop if changed externally
watch(
    () => props.modelValue,
    (newValue) => {
        const isSame = editor.value?.getHTML() === newValue;
        if (!isSame && editor.value) {
            editor.value.commands.setContent(newValue || "", {
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
    youtubeUrlInput.value = "";
    isYoutubeModalOpen.value = true;
};

const insertYoutube = () => {
    if (!youtubeUrlInput.value.trim() || !editor.value) return;
    editor.value.commands.setYoutubeVideo({
        src: youtubeUrlInput.value.trim(),
    });
    isYoutubeModalOpen.value = false;
    youtubeUrlInput.value = "";
};

// Image embed & upload handler
const openImageModal = () => {
    imageUrlInput.value = "";
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
    imageUrlInput.value = "";
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
        formData.append("image", file);

        const csrfToken =
            (
                document.querySelector(
                    'meta[name="csrf-token"]',
                ) as HTMLMetaElement
            )?.content || "";

        const response = await fetch(props.uploadUrl, {
            method: "POST",
            body: formData,
            headers: {
                "X-Requested-With": "XMLHttpRequest",
                "X-CSRF-TOKEN": csrfToken,
                Accept: "application/json",
            },
        });

        if (!response.ok) {
            throw new Error("Upload failed");
        }

        const data = await response.json();

        if (data?.url) {
            editor.value.chain().focus().setImage({ src: data.url }).run();
            isImageModalOpen.value = false;
        }
    } catch (error) {
        console.error("Failed to upload inline image:", error);
        alert(
            "Gagal mengunggah foto. Pastikan format gambar sesuai (JPG, PNG, WebP) dan ukuran maksimal 5MB.",
        );
    } finally {
        isUploadingImage.value = false;
        if (target) target.value = "";
    }
};

// Link handler
const openLinkModal = () => {
    if (!editor.value) return;
    const previousUrl = editor.value.getAttributes("link").href || "";
    linkUrlInput.value = previousUrl;
    isLinkModalOpen.value = true;
};

const applyLink = () => {
    if (!editor.value) return;
    if (linkUrlInput.value.trim() === "") {
        editor.value.chain().focus().extendMarkRange("link").unsetLink().run();
    } else {
        editor.value
            .chain()
            .focus()
            .extendMarkRange("link")
            .setLink({ href: linkUrlInput.value.trim(), target: "_blank" })
            .run();
    }
    isLinkModalOpen.value = false;
};
</script>

<template>
    <div
        class="border border-gray-200 rounded-2xl bg-white overflow-hidden shadow-sm transition-all focus-within:border-[#0b8043] focus-within:ring-2 focus-within:ring-[#0b8043]/10"
    >
        <!-- Editor Toolbar -->
        <div
            v-if="editor"
            class="flex flex-wrap items-center gap-1 p-2 bg-[#fafaf9] border-b border-gray-200 select-none"
        >
            <!-- Text Styles -->
            <button
                type="button"
                @click="editor.chain().focus().toggleBold().run()"
                :class="[
                    'p-2 rounded-lg text-sm transition font-medium',
                    editor.isActive('bold')
                        ? 'bg-[#0b8043] text-white shadow-sm'
                        : 'text-gray-600 hover:bg-gray-200',
                ]"
                title="Tebal (Bold)"
            >
                <Bold class="w-4 h-4" />
            </button>
            <button
                type="button"
                @click="editor.chain().focus().toggleItalic().run()"
                :class="[
                    'p-2 rounded-lg text-sm transition',
                    editor.isActive('italic')
                        ? 'bg-[#0b8043] text-white shadow-sm'
                        : 'text-gray-600 hover:bg-gray-200',
                ]"
                title="Miring (Italic)"
            >
                <Italic class="w-4 h-4" />
            </button>
            <button
                type="button"
                @click="editor.chain().focus().toggleStrike().run()"
                :class="[
                    'p-2 rounded-lg text-sm transition',
                    editor.isActive('strike')
                        ? 'bg-[#0b8043] text-white shadow-sm'
                        : 'text-gray-600 hover:bg-gray-200',
                ]"
                title="Coret (Strike)"
            >
                <Strikethrough class="w-4 h-4" />
            </button>

            <div class="w-px h-5 bg-gray-300 mx-1"></div>

            <!-- Headings -->
            <button
                type="button"
                @click="
                    editor.chain().focus().toggleHeading({ level: 1 }).run()
                "
                :class="[
                    'p-2 rounded-lg text-xs font-bold transition flex items-center gap-0.5',
                    editor.isActive('heading', { level: 1 })
                        ? 'bg-[#0b8043] text-white shadow-sm'
                        : 'text-gray-600 hover:bg-gray-200',
                ]"
                title="Judul Utama (H1)"
            >
                <Heading1 class="w-4 h-4" />
            </button>
            <button
                type="button"
                @click="
                    editor.chain().focus().toggleHeading({ level: 2 }).run()
                "
                :class="[
                    'p-2 rounded-lg text-xs font-bold transition flex items-center gap-0.5',
                    editor.isActive('heading', { level: 2 })
                        ? 'bg-[#0b8043] text-white shadow-sm'
                        : 'text-gray-600 hover:bg-gray-200',
                ]"
                title="Sub Judul (H2)"
            >
                <Heading2 class="w-4 h-4" />
            </button>
            <button
                type="button"
                @click="
                    editor.chain().focus().toggleHeading({ level: 3 }).run()
                "
                :class="[
                    'p-2 rounded-lg text-xs font-bold transition flex items-center gap-0.5',
                    editor.isActive('heading', { level: 3 })
                        ? 'bg-[#0b8043] text-white shadow-sm'
                        : 'text-gray-600 hover:bg-gray-200',
                ]"
                title="Poin Judul (H3)"
            >
                <Heading3 class="w-4 h-4" />
            </button>

            <div class="w-px h-5 bg-gray-300 mx-1"></div>

            <!-- Lists -->
            <button
                type="button"
                @click="editor.chain().focus().toggleBulletList().run()"
                :class="[
                    'p-2 rounded-lg text-sm transition',
                    editor.isActive('bulletList')
                        ? 'bg-[#0b8043] text-white shadow-sm'
                        : 'text-gray-600 hover:bg-gray-200',
                ]"
                title="Daftar Poin (Bullet List)"
            >
                <List class="w-4 h-4" />
            </button>
            <button
                type="button"
                @click="editor.chain().focus().toggleOrderedList().run()"
                :class="[
                    'p-2 rounded-lg text-sm transition',
                    editor.isActive('orderedList')
                        ? 'bg-[#0b8043] text-white shadow-sm'
                        : 'text-gray-600 hover:bg-gray-200',
                ]"
                title="Daftar Nomor (Numbered List)"
            >
                <ListOrdered class="w-4 h-4" />
            </button>
            <button
                type="button"
                @click="editor.chain().focus().toggleBlockquote().run()"
                :class="[
                    'p-2 rounded-lg text-sm transition',
                    editor.isActive('blockquote')
                        ? 'bg-[#0b8043] text-white shadow-sm'
                        : 'text-gray-600 hover:bg-gray-200',
                ]"
                title="Kutipan (Quote)"
            >
                <Quote class="w-4 h-4" />
            </button>
            <button
                type="button"
                @click="editor.chain().focus().setHorizontalRule().run()"
                class="p-2 rounded-lg text-sm text-gray-600 hover:bg-gray-200 transition"
                title="Garis Pemisah"
            >
                <Minus class="w-4 h-4" />
            </button>

            <div class="w-px h-5 bg-gray-300 mx-1"></div>

            <!-- Links -->
            <button
                type="button"
                @click="openLinkModal"
                :class="[
                    'p-2 rounded-lg text-sm transition',
                    editor.isActive('link')
                        ? 'bg-[#0b8043] text-white shadow-sm'
                        : 'text-gray-600 hover:bg-gray-200',
                ]"
                title="Sisipkan Tautan (Link)"
            >
                <LinkIcon class="w-4 h-4" />
            </button>
            <button
                v-if="editor.isActive('link')"
                type="button"
                @click="editor.chain().focus().unsetLink().run()"
                class="p-2 rounded-lg text-sm text-red-500 hover:bg-red-50 transition"
                title="Hapus Tautan"
            >
                <Unlink class="w-4 h-4" />
            </button>

            <div class="w-px h-5 bg-gray-300 mx-1"></div>

            <!-- Media: Photo & YouTube -->
            <button
                type="button"
                @click="openImageModal"
                class="p-2 rounded-lg text-sm text-gray-700 hover:bg-emerald-50 hover:text-[#0b8043] transition flex items-center gap-1 font-medium"
                title="Sisipkan Foto / Gambar"
            >
                <ImageIcon class="w-4 h-4 text-emerald-600" />
                <span class="text-xs">Foto</span>
            </button>
            <button
                type="button"
                @click="openYoutubeModal"
                class="p-2 rounded-lg text-sm text-gray-700 hover:bg-red-50 hover:text-red-600 transition flex items-center gap-1 font-medium"
                title="Sisipkan Video YouTube"
            >
                <Video class="w-4 h-4 text-red-600" />
                <span class="text-xs">YouTube</span>
            </button>

            <div class="w-px h-5 bg-gray-300 mx-1"></div>

            <!-- History: Undo / Redo -->
            <button
                type="button"
                @click="editor.chain().focus().undo().run()"
                :disabled="!editor.can().undo()"
                class="p-2 rounded-lg text-sm text-gray-600 hover:bg-gray-200 transition disabled:opacity-30 disabled:hover:bg-transparent"
                title="Urungkan (Undo)"
            >
                <Undo class="w-4 h-4" />
            </button>
            <button
                type="button"
                @click="editor.chain().focus().redo().run()"
                :disabled="!editor.can().redo()"
                class="p-2 rounded-lg text-sm text-gray-600 hover:bg-gray-200 transition disabled:opacity-30 disabled:hover:bg-transparent"
                title="Ulangi (Redo)"
            >
                <Redo class="w-4 h-4" />
            </button>
        </div>

        <!-- Editor Content Area -->
        <EditorContent :editor="editor" class="min-h-[360px]" />

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
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-xs p-4 animate-in fade-in duration-150"
        >
            <div
                class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-gray-100"
            >
                <div class="flex items-center justify-between mb-4">
                    <div
                        class="flex items-center gap-2 text-gray-800 font-semibold text-lg"
                    >
                        <Video class="w-5 h-5 text-red-600" />
                        <span>Sisipkan Video YouTube</span>
                    </div>
                    <button
                        type="button"
                        @click="isYoutubeModalOpen = false"
                        class="p-1 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100"
                    >
                        <X class="w-5 h-5" />
                    </button>
                </div>
                <p class="text-sm text-gray-600 mb-4">
                    Masukkan URL video YouTube lengkap (contoh:
                    <code class="bg-gray-100 px-1 py-0.5 rounded text-xs"
                        >https://www.youtube.com/watch?v=...</code
                    >).
                </p>
                <input
                    v-model="youtubeUrlInput"
                    type="url"
                    placeholder="https://www.youtube.com/watch?v=..."
                    class="w-full px-4 py-2.5 rounded-xl border border-gray-200 focus:outline-none focus:border-[#0b8043] focus:ring-2 focus:ring-[#0b8043]/15 text-sm mb-4"
                    @keyup.enter="insertYoutube"
                />
                <div class="flex items-center justify-end gap-2">
                    <button
                        type="button"
                        @click="isYoutubeModalOpen = false"
                        class="px-4 py-2 rounded-xl text-sm font-medium text-gray-600 hover:bg-gray-100"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        @click="insertYoutube"
                        class="px-4 py-2 rounded-xl text-sm font-semibold text-white bg-[#0b8043] hover:bg-[#096836] shadow-sm transition"
                    >
                        Sisipkan Video
                    </button>
                </div>
            </div>
        </div>

        <!-- Modal Sisipkan Foto -->
        <div
            v-if="isImageModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-xs p-4 animate-in fade-in duration-150"
        >
            <div
                class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-gray-100"
            >
                <div class="flex items-center justify-between mb-4">
                    <div
                        class="flex items-center gap-2 text-gray-800 font-semibold text-lg"
                    >
                        <ImageIcon class="w-5 h-5 text-[#0b8043]" />
                        <span>Sisipkan Foto ke Artikel</span>
                    </div>
                    <button
                        type="button"
                        @click="isImageModalOpen = false"
                        class="p-1 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100"
                    >
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <!-- Opsi 1: Upload dari Perangkat -->
                <div class="mb-4">
                    <label
                        class="block text-xs font-semibold text-gray-600 uppercase tracking-wider mb-2"
                        >Unggah dari Komputer</label
                    >
                    <button
                        type="button"
                        @click="triggerFileInput"
                        :disabled="isUploadingImage"
                        class="w-full border-2 border-dashed border-gray-200 rounded-xl p-4 flex flex-col items-center justify-center text-gray-500 hover:border-[#0b8043] hover:text-[#0b8043] hover:bg-emerald-50/40 transition cursor-pointer"
                    >
                        <Loader2
                            v-if="isUploadingImage"
                            class="w-6 h-6 animate-spin text-[#0b8043] mb-1"
                        />
                        <Upload v-else class="w-6 h-6 mb-1 text-gray-400" />
                        <span class="text-sm font-medium">{{
                            isUploadingImage
                                ? "Mengunggah foto..."
                                : "Pilih File Gambar"
                        }}</span>
                        <span class="text-xs text-gray-400 mt-0.5"
                            >PNG, JPG, WebP maksimal 5MB</span
                        >
                    </button>
                </div>

                <div class="flex items-center my-3">
                    <div class="grow border-t border-gray-200"></div>
                    <span class="px-2 text-xs text-gray-400 uppercase"
                        >atau dari URL</span
                    >
                    <div class="grow border-t border-gray-200"></div>
                </div>

                <!-- Opsi 2: Link URL Eksternal -->
                <div class="mb-5">
                    <input
                        v-model="imageUrlInput"
                        type="url"
                        placeholder="https://images.unsplash.com/photo-..."
                        class="w-full px-4 py-2.5 rounded-xl border border-gray-200 focus:outline-none focus:border-[#0b8043] focus:ring-2 focus:ring-[#0b8043]/15 text-sm"
                        @keyup.enter="insertImageByUrl"
                    />
                </div>

                <div class="flex items-center justify-end gap-2">
                    <button
                        type="button"
                        @click="isImageModalOpen = false"
                        class="px-4 py-2 rounded-xl text-sm font-medium text-gray-600 hover:bg-gray-100"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        @click="insertImageByUrl"
                        :disabled="!imageUrlInput.trim()"
                        class="px-4 py-2 rounded-xl text-sm font-semibold text-white bg-[#0b8043] hover:bg-[#096836] disabled:opacity-40 shadow-sm transition"
                    >
                        Gunakan URL
                    </button>
                </div>
            </div>
        </div>

        <!-- Modal Tautan (Link) -->
        <div
            v-if="isLinkModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-xs p-4 animate-in fade-in duration-150"
        >
            <div
                class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-gray-100"
            >
                <div class="flex items-center justify-between mb-4">
                    <div
                        class="flex items-center gap-2 text-gray-800 font-semibold text-lg"
                    >
                        <LinkIcon class="w-5 h-5 text-[#0b8043]" />
                        <span>Sisipkan Tautan URL</span>
                    </div>
                    <button
                        type="button"
                        @click="isLinkModalOpen = false"
                        class="p-1 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100"
                    >
                        <X class="w-5 h-5" />
                    </button>
                </div>
                <input
                    v-model="linkUrlInput"
                    type="url"
                    placeholder="https://example.com"
                    class="w-full px-4 py-2.5 rounded-xl border border-gray-200 focus:outline-none focus:border-[#0b8043] focus:ring-2 focus:ring-[#0b8043]/15 text-sm mb-4"
                    @keyup.enter="applyLink"
                />
                <div class="flex items-center justify-end gap-2">
                    <button
                        type="button"
                        @click="isLinkModalOpen = false"
                        class="px-4 py-2 rounded-xl text-sm font-medium text-gray-600 hover:bg-gray-100"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        @click="applyLink"
                        class="px-4 py-2 rounded-xl text-sm font-semibold text-white bg-[#0b8043] hover:bg-[#096836] shadow-sm transition"
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
.ProseMirror {
    outline: none !important;
}

.ProseMirror p.is-editor-empty:first-child::before {
    color: #9ca3af;
    content: attr(data-placeholder);
    float: left;
    height: 0;
    pointer-events: none;
}

.ProseMirror blockquote {
    border-left: 4px solid #0b8043;
    padding-left: 1rem;
    font-style: italic;
    color: #4b5563;
    margin: 1rem 0;
}

.ProseMirror ul {
    list-style-type: disc;
    padding-left: 1.5rem;
    margin: 0.75rem 0;
}

.ProseMirror ol {
    list-style-type: decimal;
    padding-left: 1.5rem;
    margin: 0.75rem 0;
}

.ProseMirror hr {
    border-color: #e5e7eb;
    margin: 1.5rem 0;
}
</style>
