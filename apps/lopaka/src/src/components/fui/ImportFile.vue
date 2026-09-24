<script
    lang="ts"
    setup
>
import { defineProps, onMounted, onUnmounted, ref } from 'vue';
import { useSession } from '../../core/session';
import { logEvent, readTextFileAsync } from '../../utils';

const props = defineProps<{
    title: string;
    cloudTitle?: string;
    active?: boolean;
    type?: 'image' | 'code';
    project_id?: number;
    isSandbox?: boolean;
}>();

const emit = defineEmits(['setActiveTab', 'uploading']);
const cloudOpenRef = ref(false);

const fileInput = ref(null);

const session = useSession();
const fileLoadedCounter = ref(0);
const isLoading = ref(false);

/* cloud import dialog */
const cloudOpen = ref(false);
const CLOUD_API = '/apps/cloud/api.php';

const ROOTS = [
    { key: 'main', labelKey: 'saveRootMain' },
    { key: 'temp', labelKey: 'saveRootTemp' },
    { key: 'documents', labelKey: 'saveRootDocuments' },
    { key: 'photos', labelKey: 'saveRootPhotos' },
    { key: 'pdf', labelKey: 'saveRootPdf' },
    { key: 'archive', labelKey: 'saveRootArchive' },
];

interface CloudEntry {
    name: string;
    path: string;
    type: 'dir' | 'file';
    size?: number | null;
}
interface CloudRoot {
    key: string;
    label: string;
    isGroup?: boolean;
}

function tr(s: string): string {
    const i18n = (window as any).__lopakaI18n;
    if (i18n?.translate) return i18n.translate(s) ?? s;
    return s;
}

const groups = ref<CloudRoot[]>([]);
const currentRoot = ref<CloudRoot>({ key: 'main', label: 'Folders' });
const currentPath = ref('');
const entries = ref<CloudEntry[]>([]);
const loadingList = ref(false);
const loadingFile = ref(false);
const cloudError = ref('');
const jsonFilter = ref(props.type !== 'image');

async function apiGet(action: string, params: Record<string, string> = {}) {
    const url = new URL(CLOUD_API, window.location.origin);
    url.searchParams.set('action', action);
    Object.entries(params).forEach(([k, v]) => url.searchParams.set(k, v));
    const res = await fetch(url.toString(), { credentials: 'same-origin' });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    return res.json();
}

async function loadGroups() {
    try {
        const resp = await apiGet('groups_list');
        if (resp?.ok && Array.isArray(resp.groups)) {
            groups.value = resp.groups.map((g: any) => ({ key: 'group:' + g.id, label: g.name, isGroup: true }));
        }
    } catch {
        /* not fatal */
    }
}

async function loadDir() {
    loadingList.value = true;
    cloudError.value = '';
    try {
        const resp = await apiGet('list', { root: currentRoot.value.key, path: currentPath.value });
        if (resp?.error) throw new Error(resp.error);
        entries.value = resp.items || [];
    } catch (e: any) {
        entries.value = [];
        if (!/not found/i.test(e.message || '')) cloudError.value = e.message || String(e);
    } finally {
        loadingList.value = false;
    }
}

async function openCloud() {
    cloudOpenRef.value = true;
    cloudError.value = '';
    await loadGroups();
    await loadDir();
}

function selectRoot(r: CloudRoot) {
    currentRoot.value = r;
    currentPath.value = '';
    loadDir();
}

function openDir(e: CloudEntry) {
    currentPath.value = currentPath.value ? `${currentPath.value}/${e.path}` : e.path;
    loadDir();
}

function goUp() {
    if (!currentPath.value) return;
    const parts = currentPath.value.split('/');
    parts.pop();
    currentPath.value = parts.join('/');
    loadDir();
}

function fileMatches(e: CloudEntry): boolean {
    if (e.type === 'dir') return true;
    if (!jsonFilter.value) return true;
    return /\.json$/i.test(e.name);
}

function pickFile(e: CloudEntry) {
    if (e.type === 'dir') {
        openDir(e);
        return;
    }
    importFromCloud(e);
}

async function importFromCloud(e: CloudEntry) {
    loadingFile.value = true;
    cloudError.value = '';
    try {
        const url = new URL(CLOUD_API, window.location.origin);
        url.searchParams.set('action', 'raw');
        url.searchParams.set('root', currentRoot.value.key);
        url.searchParams.set('path', currentPath.value ? `${currentPath.value}/${e.path}` : e.path);
        const res = await fetch(url.toString(), { credentials: 'same-origin' });
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const text = await res.text();
        if (props.type === 'image') {
            // data URL images are not expected in raw import; treat as code/text
            session.importCode(text, true);
        } else {
            session.importCode(text, true);
        }
        emit('setActiveTab', 'code');
        cloudOpenRef.value = false;
    } catch (e: any) {
        cloudError.value = e.message || String(e);
    } finally {
        loadingFile.value = false;
    }
}

function onKey(ev: KeyboardEvent) {
    if (ev.key === 'Escape') cloudOpenRef.value = false;
}
onMounted(() => document.addEventListener('keydown', onKey));
onUnmounted(() => document.removeEventListener('keydown', onKey));

async function onFileChange(e) {
    const file = e.target.files[0];
    if (!file?.name) {
        return;
    }
    const fileResult = await readTextFileAsync(file);
    session.importCode(fileResult, true);
    emit('setActiveTab', 'code');

    isLoading.value = false;
    resetFileInput();
}

function resetFileInput() {
    fileInput.value = null;
    fileLoadedCounter.value++;
}
</script>
<template>
    <label
        class="btn font-sans uppercase leading-none btn-outline btn-sm cursor-pointer flex items-center content-center gap-2"
        @click="logEvent(`button_import_${type}`)"
    >
        <slot></slot>
        <input
            type="file"
            style="position: fixed; top: -100%"
            :accept="type === 'image' ? 'image/*' : '.c,.cpp,.ino,.txt,.xbm'"
            @change="onFileChange"
            ref="fileInput"
            :key="fileLoadedCounter"
            :multiple="type === 'image'"
            :disabled="isLoading"
        />
        {{ title }}
        <div
            v-if="isLoading"
            class="loading loading-sm loading-spinner"
        ></div>
    </label>

    <!-- small cloud import button next to slot content -->
    <button
        type="button"
        class="btn font-sans uppercase leading-none btn-outline btn-sm fui-cloud-import-btn"
        :title="tr('Import from cloud')"
        @click.stop="cloudOpenRef = true"
    >
        <span class="flex items-center gap-2">☁ {{ cloudTitle ?? tr('Cloud') }}</span>
    </button>

    <!-- Cloud import modal -->
    <Teleport to="body">
    <div
        v-if="cloudOpenRef"
        class="modal font-sans opacity-100 pointer-events-auto"
        @click.self="cloudOpenRef = false"
    >
        <div class="fui-save-modal modal-box border border-base-300 pointer-events-auto opacity-100">
            <h3 class="fui-save-modal__title">{{ tr('Import from cloud') }}</h3>

            <div class="fui-save-roots">
                <button
                    v-for="r in ROOTS"
                    :key="r.key"
                    type="button"
                    class="fui-save-root"
                    :class="{ 'is-active': currentRoot.key === r.key }"
                    @click="selectRoot(r)"
                >
                    {{ tr(r.labelKey) }}
                </button>
                <button
                    v-for="g in groups"
                    :key="g.key"
                    type="button"
                    class="fui-save-root fui-save-root--group"
                    :class="{ 'is-active': currentRoot.key === g.key }"
                    @click="selectRoot(g)"
                >
                    {{ g.label }}
                </button>
            </div>

            <div class="fui-save-breadcrumbs">
                <button
                    type="button"
                    class="fui-save-crumb"
                    :disabled="!currentPath"
                    @click="goUp"
                >
                    ↑ ..
                </button>
                <span class="fui-save-crumb-path">{{ currentRoot.label }}{{ currentPath ? '/' + currentPath : '' }}</span>
            </div>

            <div class="fui-save-dirs">
                <div
                    v-if="loadingList"
                    class="fui-save-loading"
                >
                    <span class="loading loading-spinner loading-sm"></span>
                </div>
                <div
                    v-else-if="!entries.length"
                    class="fui-save-empty"
                >
                    {{ tr('No files') }}
                </div>
                <template v-else>
                    <button
                        v-for="e in entries"
                        :key="e.path"
                        type="button"
                        class="fui-save-dir"
                        :class="{ 'fui-save-dir--dim': !fileMatches(e) }"
                        @click="pickFile(e)"
                    >
                        {{ e.type === 'dir' ? '📁' : '📄' }} {{ e.name }}
                    </button>
                </template>
            </div>

            <div
                v-if="cloudError"
                class="fui-save-error"
            >
                {{ cloudError }}
            </div>

            <div class="fui-save-actions">
                <button
                    type="button"
                    class="btn btn-ghost btn-sm"
                    @click="cloudOpen = false"
                >
                    {{ tr('Cancel') }}
                </button>
            </div>
        </div>
    </div>
    </Teleport>
</template>
