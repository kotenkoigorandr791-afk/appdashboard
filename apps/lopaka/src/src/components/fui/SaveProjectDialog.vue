<script lang="ts" setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import Icon from '/src/components/layout/Icon.vue';
import { useSession } from '../../core/session';

const emit = defineEmits(['close', 'saved', 'notify']);

const session = useSession();

/* runtime translation via i18n overlay dictionary */
function tr(s: string): string {
    const i18n = (window as any).__lopakaI18n;
    if (i18n?.translate) return i18n.translate(s) ?? s;
    if (i18n?.lang && i18n.lang !== 'en' && (window as any).__lopakaI18nDict) {
        const e = (window as any).__lopakaI18nDict[s];
        if (e) return e[i18n.lang] || s;
    }
    return s;
}
function rootLabel(r: { key: string; labelKey: string }): string {
    return tr(r.labelKey);
}

type Step = 'choose' | 'cloud';
const step = ref<Step>('choose');
const busy = ref(false);
const error = ref('');

/* ---------- project snapshot ---------- */
function buildSnapshot(): { name: string; json: string; blob: Blob } {
    const layers = session.layersManager.layers.map((l) => l.state);
    const payload = {
        app: 'lopaka',
        version: 1,
        saved_at: new Date().toISOString(),
        platform: session.state.platform,
        display: { x: session.state.display.x, y: session.state.display.y },
        scale: session.getScalePercent(),
        color_bg: session.platforms[session.state.platform]?.features?.screenBgColor ?? null,
        layers,
        preview: session.virtualScreen.canvas.toDataURL(),
    };
    const date = new Date().toISOString().slice(0, 19).replace(/[T:]/g, '-');
    const name = `lopaka_${session.state.platform}_${date}.json`;
    const json = JSON.stringify(payload, null, 2);
    return { name, json, blob: new Blob([json], { type: 'application/json' }) };
}

function downloadFile() {
    const { name, blob } = buildSnapshot();
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = name;
    a.click();
    setTimeout(() => URL.revokeObjectURL(url), 5000);
    emit('notify', 'Project saved to file');
    emit('close');
}

/* ---------- cloud integration ---------- */
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
}
interface CloudRoot {
    key: string;
    label: string;
    isGroup?: boolean;
}

const groups = ref<CloudRoot[]>([]);
const webdavAvailable = ref(false);
const currentRoot = ref<CloudRoot>({ key: 'main', label: 'Folders' });
const currentPath = ref('');
const entries = ref<CloudEntry[]>([]);
const fileName = ref('');
const newFolderName = ref('');
const creatingFolder = ref(false);
const loadingList = ref(false);

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
        /* groups unavailable — not fatal */
    }
}

async function openCloud() {
    step.value = 'cloud';
    error.value = '';
    const snapshot = buildSnapshot();
    if (!fileName.value) fileName.value = snapshot.name;
    await loadGroups();
    await loadDir();
}

async function loadDir() {
    loadingList.value = true;
    error.value = '';
    try {
        const resp = await apiGet('list', { root: currentRoot.value.key, path: currentPath.value });
        if (resp?.error) throw new Error(resp.error);
        entries.value = (resp.items || []).filter((i: CloudEntry) => i.type === 'dir');
    } catch (e: any) {
        entries.value = [];
        if (!/not found/i.test(e.message || '')) error.value = e.message || String(e);
    } finally {
        loadingList.value = false;
    }
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

async function createFolder() {
    const name = newFolderName.value.trim();
    if (!name) return;
    creatingFolder.value = true;
    error.value = '';
    try {
        const url = new URL(CLOUD_API, window.location.origin);
        const body = new URLSearchParams();
        body.set('action', 'mkdir');
        body.set('root', currentRoot.value.key);
        body.set('path', currentPath.value ? `${currentPath.value}/${name}` : name);
        const res = await fetch(url.toString(), {
            method: 'POST',
            credentials: 'same-origin',
            body,
        });
        const resp = await res.json();
        if (resp?.error) throw new Error(resp.error);
        newFolderName.value = '';
        await loadDir();
    } catch (e: any) {
        error.value = e.message || String(e);
    } finally {
        creatingFolder.value = false;
    }
}

async function saveToCloud() {
    busy.value = true;
    error.value = '';
    try {
        const name = fileName.value.trim() || buildSnapshot().name;
        const path = currentPath.value ? `${currentPath.value}/${name}` : name;
        const blob = buildSnapshot().blob;
        const fd = new FormData();
        fd.append('action', 'upload');
        fd.append('root', currentRoot.value.key);
        fd.append('path', path);
        fd.append('file', blob, name);
        const res = await fetch(CLOUD_API, { method: 'POST', credentials: 'same-origin', body: fd });
        const resp = await res.json();
        if (resp?.error) throw new Error(resp.error);
        emit('notify', 'Project saved to cloud: ' + name);
        emit('saved', { root: currentRoot.value.key, path });
        emit('close');
    } catch (e: any) {
        error.value = e.message || String(e);
    } finally {
        busy.value = false;
    }
}

/* esc closes */
function onKey(e: KeyboardEvent) {
    if (e.key === 'Escape') emit('close');
}
onMounted(() => document.addEventListener('keydown', onKey));
onUnmounted(() => document.removeEventListener('keydown', onKey));
</script>
<template>
    <div
        class="modal font-sans opacity-100 pointer-events-auto"
        @click.self="emit('close')"
    >
        <div class="fui-save-modal modal-box border border-base-300 pointer-events-auto opacity-100">
            <!-- STEP 1: choose destination -->
            <div v-if="step === 'choose'">
                <h3 class="fui-save-modal__title">Save project</h3>
                <p class="fui-save-modal__hint">Choose where to store your project (JSON file)</p>
                <div class="fui-save-modal__choices">
                    <button
                        type="button"
                        class="fui-save-card"
                        @click="downloadFile"
                    >
                        <Icon
                            type="download"
                            pointer
                        />
                        <span
                            class="fui-save-card__title"
                            data-i18n="Download to device"
                            >Download to device</span
                        >
                        <span
                            class="fui-save-card__desc"
                            data-i18n="Save the JSON file to your computer"
                            >Save the JSON file to your computer</span
                        >
                    </button>
                    <button
                        type="button"
                        class="fui-save-card"
                        @click="openCloud"
                    >
                        <Icon
                            type="upload"
                            pointer
                        />
                        <span
                            class="fui-save-card__title"
                            data-i18n="Save to cloud"
                            >Save to cloud</span
                        >
                        <span
                            class="fui-save-card__desc"
                            data-i18n="Store in your cloud storage (folders, groups)"
                            >Store in your cloud storage (folders, groups)</span
                        >
                    </button>
                </div>
            </div>

            <!-- STEP 2: cloud folder picker -->
            <div v-if="step === 'cloud'">
                <h3 class="fui-save-modal__title">Save to cloud</h3>

                <!-- roots -->
                <div class="fui-save-roots">
                    <button
                        v-for="r in ROOTS"
                        :key="r.key"
                        type="button"
                        class="fui-save-root"
                        :class="{ 'is-active': currentRoot.key === r.key && !currentPath.startsWith('..') }"
                        @click="selectRoot(r)"
                    >
                        {{ rootLabel(r) }}
                    </button>
                    <button
                        v-for="g in groups"
                        :key="g.key"
                        type="button"
                        class="fui-save-root fui-save-root--group"
                        :class="{ 'is-active': currentRoot.key === g.key }"
                        @click="selectRoot(g)"
                    >
                        <Icon
                            type="collapse"
                            sm
                        />
                        {{ g.label }}
                    </button>
                </div>

                <!-- breadcrumbs -->
                <div class="fui-save-breadcrumbs">
                    <button
                        type="button"
                        class="fui-save-crumb"
                        @click="goUp"
                        :disabled="!currentPath"
                    >
                        ↑ ..
                    </button>
                    <span class="fui-save-crumb-path">{{ currentRoot.label }}{{ currentPath ? '/' + currentPath : '' }}</span>
                </div>

                <!-- folders -->
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
                        {{ tr("No subfolders") }}
                    </div>
                    <button
                        v-else
                        v-for="e in entries"
                        :key="e.path"
                        type="button"
                        class="fui-save-dir"
                        @click="openDir(e)"
                    >
                        📁 {{ e.name }}
                    </button>
                </div>

                <!-- new folder -->
                <div class="fui-save-newfolder">
                    <input
                        v-model="newFolderName"
                        class="fui-save-input"
                        :placeholder="tr('New folder name')"
                        @keyup.enter="createFolder"
                    />
                    <button
                        type="button"
                        class="btn btn-sm"
                        :disabled="creatingFolder || !newFolderName.trim()"
                        @click="createFolder"
                    >
                        {{ tr("Create folder") }}
                    </button>
                </div>

                <!-- file name -->
                <div class="fui-save-name">
                    <label
                        class="fui-save-name__label"
                        for="save-file-name"
                        >{{ tr("File name:") }}</label
                    >
                    <input
                        id="save-file-name"
                        v-model="fileName"
                        class="fui-save-input"
                        @keyup.enter="saveToCloud"
                    />
                </div>

                <div
                    v-if="error"
                    class="fui-save-error"
                >
                    {{ error }}
                </div>

                <div class="fui-save-actions">
                    <button
                        type="button"
                        class="btn btn-ghost btn-sm"
                        @click="step = 'choose'"
                    >
                        {{ tr('Back') }}
                    </button>
                    <button
                        type="button"
                        class="btn btn-primary btn-sm"
                        :disabled="busy"
                        @click="saveToCloud"
                    >
                        <span
                            v-if="busy"
                            class="loading loading-spinner loading-xs"
                        ></span>
                        {{ tr("Save") }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
<style lang="css">
.fui-save-modal {
    max-width: 520px;
    width: 92vw;
    background: var(--secondary-color);
    color: var(--text-color, inherit);
    border-radius: 14px;
}

.fui-save-modal__title {
    font-size: 16px;
    font-weight: 700;
    margin-bottom: 4px;
}

.fui-save-modal__hint {
    font-size: 12px;
    opacity: 0.7;
    margin-bottom: 16px;
}

.fui-save-modal__choices {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}

.fui-save-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    padding: 20px 12px;
    border: 1px solid var(--border-color, rgba(255, 255, 255, 0.12));
    border-radius: 12px;
    background: color-mix(in srgb, var(--bg-color) 50%, transparent);
    color: inherit;
    cursor: pointer;
    transition: border-color 0.15s, background 0.15s;
}

.fui-save-card:hover {
    border-color: var(--accent-color);
    background: color-mix(in srgb, var(--accent-color) 8%, transparent);
}

.fui-save-card__title {
    font-weight: 700;
    font-size: 13px;
}

.fui-save-card__desc {
    font-size: 11px;
    opacity: 0.65;
    text-align: center;
}

/* roots */
.fui-save-roots {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-bottom: 10px;
}

.fui-save-root {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 5px 10px;
    font-size: 12px;
    border: 1px solid var(--border-color, rgba(255, 255, 255, 0.12));
    border-radius: 999px;
    background: transparent;
    color: inherit;
    cursor: pointer;
    transition: all 0.12s;
}

.fui-save-root:hover {
    border-color: var(--accent-color);
}

.fui-save-root.is-active {
    background: var(--accent-color);
    border-color: var(--accent-color);
    color: #fff;
}

.fui-save-root--group {
    border-style: dashed;
}

/* breadcrumbs */
.fui-save-breadcrumbs {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
    min-width: 0;
}

.fui-save-crumb {
    border: 1px solid var(--border-color, rgba(255, 255, 255, 0.12));
    background: transparent;
    color: inherit;
    border-radius: 6px;
    padding: 2px 8px;
    cursor: pointer;
    font-size: 12px;
}

.fui-save-crumb:disabled {
    opacity: 0.4;
    cursor: default;
}

.fui-save-crumb-path {
    font-size: 12px;
    opacity: 0.7;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    min-width: 0;
}

/* folder list */
.fui-save-dirs {
    max-height: 200px;
    overflow-y: auto;
    border: 1px solid var(--border-color, rgba(255, 255, 255, 0.1));
    border-radius: 8px;
    margin-bottom: 10px;
    min-height: 60px;
}

.fui-save-dir {
    display: block;
    width: 100%;
    text-align: left;
    padding: 7px 12px;
    background: transparent;
    border: none;
    color: inherit;
    font-size: 13px;
    cursor: pointer;
}

.fui-save-dir:hover {
    background: color-mix(in srgb, var(--accent-color) 12%, transparent);
}

.fui-save-loading,
.fui-save-empty {
    padding: 16px;
    text-align: center;
    font-size: 12px;
    opacity: 0.6;
}

/* inputs */
.fui-save-input {
    background: var(--bg-color, rgba(0, 0, 0, 0.3));
    border: 1px solid var(--border-color, rgba(255, 255, 255, 0.15));
    border-radius: 6px;
    padding: 6px 10px;
    color: inherit;
    font-size: 13px;
    flex: 1;
    min-width: 0;
}

.fui-save-input:focus {
    outline: none;
    border-color: var(--accent-color);
}

.fui-save-newfolder {
    display: flex;
    gap: 8px;
    margin-bottom: 10px;
}

.fui-save-name {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 12px;
}

.fui-save-name__label {
    font-size: 13px;
    white-space: nowrap;
}

.fui-save-error {
    color: var(--danger-color, #eb4e27);
    font-size: 12px;
    margin-bottom: 10px;
}

.fui-save-actions {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
}

@media (max-width: 768px) {
    .fui-save-modal__choices {
        grid-template-columns: 1fr;
    }
}
</style>
