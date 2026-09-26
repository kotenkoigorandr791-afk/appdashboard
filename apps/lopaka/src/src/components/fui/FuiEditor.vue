<script
    setup
    lang="ts"
>
import { computed, onBeforeUnmount, onMounted, ref, ShallowRef, toRefs, watch } from 'vue';
import { Project, ProjectScreen } from '/src/types';
import Button from '/src/components/layout/Button.vue';
import FuiCanvas from '/src/components/fui/FuiCanvas.vue';
import FuiCode from '/src/components/fui/FuiCode.vue';
import FuiEditorSettings from '/src/components/fui/FuiCodeSettings.vue';
import ImportFile from '/src/components/fui/ImportFile.vue';
import Inspector from '/src/components/fui/inspector/Inspector.vue';
import FuiSelectDisplay from '/src/components/fui/FuiSelectDisplay/FuiSelectDisplay.vue';
import FuiSelectPlatform from '/src/components/fui/FuiSelectPlatform.vue';
import FuiSelectScale from '/src/components/fui/FuiSelectScale.vue';
import FuiTools from '/src/components/fui/FuiTools.vue';
import PaintBrushColorInput from './PaintBrushColorInput.vue';
import PaintColorModeToggle from './PaintColorModeToggle.vue';
import Icon from '/src/components/layout/Icon.vue';
import FuiSelectColors from './FuiSelectColors.vue';
import ImportImageWizard from './importImage/ImportImageWizard.vue';
import SaveProjectDialog from './SaveProjectDialog.vue';

import { Point } from '/src/core/point';
import { saveLayers, useSession } from '/src/core/session';
import { FlipperRPC } from '/src/flipper-rpc';
import { FlipperPlatform } from '/src/platforms/flipper';
import { logEvent } from '/src/utils';
import { PaintLayer } from '/src/core/layers/paint.layer';
import { Uint32RawPlatform } from '/src/platforms/uint32-raw';
import { FreestylePlatform } from '/src/platforms/freestyle';
import { U8g2Platform } from '/src/platforms/u8g2';
import { addCustomImage } from '/src/core/session';
import { createScreenAutosave } from '/src/core/screen-autosave';

const props = defineProps<{
    project: Project | null;
    screen: ProjectScreen | null | undefined;
    readonly?: boolean;
    isScreenNotFound: boolean;
    isScreenLoaded: boolean;
}>();

const emit = defineEmits(['openPresenter', 'setInfoMessage', 'setErrorMessage']);

const session = useSession();
const { virtualScreen, state, platforms } = session;
const { platform, warnings } = toRefs(state);
const { immidiateUpdates } = toRefs(session.state);
const flipper: ShallowRef<FlipperRPC> = ref(null);
const uploading = ref(false);
const layersOpen = ref(false);
const saveDialogOpen = ref(false);
const { activeTool, activeLayer } = toRefs(session.editor.state);

const isFlipper = computed(() => platform.value === FlipperPlatform.id);
const isSerialSupported = computed(() => window.navigator.serial !== undefined);
const flipperPreviewBtnText = computed(() => (flipper.value ? 'Disconnect' : 'Live View'));
const isPlatformWithParser = computed(() => platforms[platform.value].hasParser());
const platformFeatures = computed(() => session.getPlatformFeatures(platform.value));
const isPlatformColored = computed(() => Boolean(platformFeatures.value?.hasRGBSupport));
// Respect monochrome support flag for platforms that disable it.
const supportsMonochrome = computed(() => platformFeatures.value?.hasMonochromeSupport ?? true);
const isPaintToolActive = computed(() => activeTool.value?.getName() === 'paint');
const supportsRgbBrushes = computed(() => {
    const features = platformFeatures.value;
    return Boolean(features?.hasRGBSupport);
});
// Hide the color mode toggle when monochrome isn't supported.
const showColorModeToggle = computed(
    () => activeTool.value?.getName() === 'paint' && isPlatformColored.value && supportsMonochrome.value
);
const shouldShowBrushControls = computed(() => {
    if (!supportsRgbBrushes.value) {
        return false;
    }
    return isPaintToolActive.value && session.state.paintColorMode === 'rgb';
});
// Keep autosave tied to the screen id captured at edit time.
const screenAutosave = createScreenAutosave(1000);

watch(immidiateUpdates, (newValue, oldValue) => {
    // Capture the current id so delayed writes cannot target another screen.
    const capturedScreenId = props.screen.id;
    // Capture layers immediately so delayed tasks do not read another screen state later.
    const capturedLayers = session.layersManager.layers.map((layer) => layer.state);
    // Capture preview immediately so delayed updates keep the right thumbnail.
    const capturedPreview = session.virtualScreen.canvas.toDataURL();
    // Debounce autosave while preserving the captured screen id.
    screenAutosave.schedule({
        screenId: capturedScreenId,
        run: (screenId) => {
            // Keep Flipper preview behavior unchanged for autosave events.
            if (flipper.value) {
                sendFlipperImage();
            }
            // Persist the captured snapshot for the exact screen that changed.
            saveLayers(screenId, { layers: capturedLayers, imagePreview: capturedPreview });
            // Refresh only the matching screen thumbnail with the captured preview.
            updateScreenPreview(screenId, capturedPreview);
        },
    });
});
watch(
    () => props.screen?.id,
    (newScreenId, previousScreenId) => {
        // Flush pending autosave before switching context to another screen.
        if (previousScreenId && previousScreenId !== newScreenId) {
            screenAutosave.flush();
        }
    }
);

watch(warnings, () => {
    setWarnings(session.state.warnings);
});

onMounted(() => {
    navigator.serial?.addEventListener('disconnect', flipperDisconnect);
});

onBeforeUnmount(() => {
    // Flush pending autosave so recent edits are not dropped on navigation.
    screenAutosave.flush();
    navigator.serial?.removeEventListener('disconnect', flipperDisconnect);
    handleWizardClose();
});

// Handle wizard save from session
async function handleWizardSave(processedImagesArr) {
    try {
        for (const [name, width, height, image, colorMode] of processedImagesArr) {
            addCustomImage(name, width, height, image, false, undefined, colorMode);
            handleIconSelect({
                name, width, height, icon: image, colorMode
            })
        }
        session.closeImageWizard();
    } catch (error) {
        console.error('Error saving images:', error);
        session.closeImageWizard();
    }
}

function handleWizardClose() {
    session.closeImageWizard();
}

function handleIconSelect(data) {
    session.layersManager.clearSelection();
    const newLayer = new PaintLayer(session.getPlatformFeatures(), session.createRenderer(), data.colorMode);
    newLayer.name = data.name;
    newLayer.size = new Point(data.width, data.height);
    session.layersManager.selectLayer(newLayer);
    data.icon.dataset.name ||= data.name;
    data.icon.dataset.colorMode ||= data.colorMode;
    data.icon.dataset.w ||= `${data.width}`;
    data.icon.dataset.h ||= `${data.height}`;
    newLayer.modifiers.icon.setValue(data.icon);
    const display = session.state.display;
    newLayer.position = new Point(
        Math.floor(display.x / 2 - data.width / 2),
        Math.floor(display.y / 2 - data.height / 2)
    );
    newLayer.updateBounds();
    newLayer.stopEdit();
    session.addLayer(newLayer);
    virtualScreen.redraw();
    session.editor.setTool(null);
    session.closeImageWizard();
}

async function toggleFlipperPreview() {
    if (flipper.value) {
        logEvent('button_flipper', 'disconnect');
        flipperDisconnect();
    } else {
        logEvent('button_flipper', 'connect');
        const flipperRPC = new FlipperRPC();
        const isConnected = await flipperRPC.connect();
        if (isConnected) {
            flipper.value = flipperRPC;
            if (immidiateUpdates.value) {
                sendFlipperImage();
            }
        }
    }
}

function flipperDisconnect() {
    flipper.value.disconnect();
    flipper.value = null;
}

function sendFlipperImage() {
    flipper.value.sendImage(virtualScreen.canvasContext.getImageData(0, 0, 128, 64));
}

function updateScreenPreview(screen_id, imagePreview) {
    if (!props.project.screens) return;
    props.project.screens = props.project.screens.map((item) => {
        if (item && item?.id === screen_id) {
            item.img_preview = imagePreview;
        }
        return item;
    });
}

function copyCode() {
    navigator.clipboard.writeText(session.generateCode().code).then(() => {
        emit('setInfoMessage', 'Copied to clipboard');
    });
    logEvent('button_copy');
}

function setWarnings(warnings) {
    warnings.forEach((warning, idx) => {
        setTimeout(() => warnings.shift(), 3000 * (1 + idx));
    });
}

function onMouseClick() {
    session.layersManager.clearSelection();
    session.virtualScreen.redraw();
}
</script>

<template>
    <!-- Image Wizard Modal -->
    <ImportImageWizard
        v-if="session.state.imageWizard.isOpen"
        @onClose="handleWizardClose"
        @onSave="handleWizardSave"
        @onIconSelect="handleIconSelect"
    />

    <div class="toast toast-top toast-center z-50 pointer-events-none">
        <div class="pointer-events-auto">
        <div
            class="alert alert-info"
            v-if="uploading"
        >
            <span>Uploading images. Please wait...</span>
            <div class="loading"></div>
        </div>
        <slot name="messages"></slot>
        <template v-if="warnings.length">
            <div
                v-for="(warningMessage, idx) in warnings"
                class="alert alert-warning"
                :key="idx"
            >
                <span>{{ warningMessage }}</span>
            </div>
        </template>
        </div>
    </div>

    <!-- Save project dialog -->
    <SaveProjectDialog
        v-if="saveDialogOpen"
        @close="saveDialogOpen = false"
        @notify="(m) => emit('setInfoMessage', m)"
    />

    <!-- Mobile layers modal -->
    <div
        v-if="layersOpen"
        class="fui-layers-modal fixed inset-0 z-50 flex items-end sm:items-center justify-center"
        @click.self="layersOpen = false"
    >
        <div class="fui-layers-modal__backdrop absolute inset-0 bg-black/60" @click="layersOpen = false"></div>
        <div
            class="fui-layers-modal__panel relative bg-secondary rounded-t-xl sm:rounded-xl w-full sm:w-[280px] max-h-[70vh] flex flex-col shadow-2xl"
        >
            <div class="flex items-center justify-between px-4 py-3 border-b border-base-300">
                <span class="font-bold text-sm uppercase tracking-wide">Layers</span>
                <button
                    type="button"
                    class="btn btn-ghost btn-xs"
                    @click="layersOpen = false"
                >✕</button>
            </div>
            <div class="overflow-y-auto px-2 py-2 flex-1 min-h-0">
                <slot name="left"></slot>
            </div>
        </div>
    </div>
    <div
        class="fui-editor flex-grow border-t border-secondary"
        v-if="platform || isScreenNotFound"
    >
        <div class="fui-editor__left border-r border-secondary">
            <slot name="left"></slot>
        </div>
        <div class="fui-editor__top pb-1">
            <slot name="title"></slot>
            <div class="fui-editor-header-row">
                <FuiSelectPlatform></FuiSelectPlatform>
                <FuiSelectDisplay></FuiSelectDisplay>
                <FuiSelectColors
                    v-if="platform !== U8g2Platform.id && platform !== FlipperPlatform.id"
                    :key="platform"
                ></FuiSelectColors>
            </div>
            <div
                class="font-sans flex flex-row gap-4 items-center flex-nowrap fui-toolbar-row"
                v-if="isScreenLoaded && !isScreenNotFound"
            >
                <div class="flex flex-1 items-center justify-start gap-1 min-w-0 fui-tools-area">
                    <FuiTools v-if="!readonly"></FuiTools>
                    <PaintColorModeToggle v-if="!readonly && showColorModeToggle" />
                    <PaintBrushColorInput v-if="!readonly && shouldShowBrushControls" />
                </div>
                <FuiSelectScale></FuiSelectScale>
                <!-- mobile: layers trigger sits inside the frame, same row as zoom -->
                <Button
                    class="fui-layers-modal-trigger"
                    @click="layersOpen = true"
                    title="Show layers"
                >
                    <Icon
                        type="stack"
                        sm
                    />
                    Layers
                </Button>
            </div>
            <div
                v-if="!isScreenLoaded && !isScreenNotFound"
                class="mx-auto py-48 text-center"
            >
                <span class="loading loading-spinner text-primary loading-lg"></span>
            </div>
            <div
                v-if="isScreenNotFound"
                class="mx-auto py-48 text-center"
            >
                <span class="text-xl">404 Screen Not Found</span>
            </div>
        </div>
        <template v-if="isScreenLoaded && !isScreenNotFound">
            <div class="fui-editor__main">
                <div
                    class="fui-editor__canvas rounded-md"
                    @click.self="onMouseClick"
                >
                    <FuiCanvas :readonly="readonly" />
                </div>
            </div>
            <div class="fui-editor__main-right pl-2 border-l border-secondary">
                <Inspector
                    :readonly="readonly"
                    :project="project"
                    :screen="screen"
                />
            </div>
            <div class="fui-editor__bottom flex flex-row justify-center pt-2 border-t border-secondary">
                <div
                    class="flex-1 relative"
                    v-if="platform !== FreestylePlatform.id"
                >
                    <div class="fui-code-topbar">
                        <span class="fui-code-topbar__title">Code</span>
                        <div class="fui-code-topbar__actions flex flex-row gap-2 content-center items-center">
                            <Button
                                secondary
                                title="Save project as JSON (download or cloud)"
                                @click="saveDialogOpen = true"
                            >
                                <Icon
                                    type="download"
                                    pointer
                                />
                                Save
                            </Button>
                            <template v-if="!readonly">
                                <div
                                    class="tooltip tooltip-bottom no-animation"
                                    data-tip="Import code - Experimental: Works best with code generated in Lopaka"
                                >
                                    <ImportFile
                                        type="code"
                                        title="Import"
                                        cloudTitle="Cloud"
                                        v-if="isPlatformWithParser"
                                    >
                                        <Icon
                                            type="download"
                                            pointer
                                        />
                                    </ImportFile>
                                </div>
                            </template>
                            <Button
                                secondary
                                title="Copy source code"
                                @click="copyCode"
                            >
                                <Icon
                                    type="clipboard"
                                    pointer
                                />
                                Copy
                            </Button>
                        </div>
                    </div>
                    <FuiCode :readonly="readonly"></FuiCode>
                </div>
                <div class="fui-editor__bottom-right pl-4">
                    <FuiEditorSettings
                        :updates="immidiateUpdates"
                        v-if="platform !== Uint32RawPlatform.id && platform !== FreestylePlatform.id"
                    />
                </div>
            </div>
        </template>
    </div>
</template>
<style lang="css">
.fui-editor {
    display: grid;
    margin: 0 auto;
    grid-template-columns: 200px 1fr 240px;
    grid-template-rows: auto minmax(0, 1fr) auto;
    grid-column-gap: 8px;
    grid-row-gap: 8px;
    width: 100%;
    height: 100%;
    min-height: 0;
    max-height: 100%;
    overflow: hidden;
}

/* never let the app page itself scroll; inner zones scroll instead */
html,
body {
    height: 100%;
    overflow: hidden;
    overscroll-behavior: none;
}

.fui-editor__left {
    grid-area: 1 / 1 / 3 / 2;
    max-height: 100%;
    overflow-y: auto;
    min-height: 0;
    scrollbar-gutter: auto;
}
/* hide scrollbar when content fits */
@supports selector(::-webkit-scrollbar) {
    .fui-editor__left {
        overflow-y: overlay;
    }
}
.fui-editor__left::-webkit-scrollbar:vertical {
    width: 8px;
}

.fui-editor__top {
    grid-area: 1 / 2 / 2 / 3;
}

.fui-editor__main {
    grid-area: 2 / 2;
    min-height: 0;
    display: flex;
    flex-direction: column;
    overflow: auto;
}

.fui-editor__main-right {
    grid-area: 1 / 3 / 3 / 4;
    padding-right: 12px;
}

.fui-editor__bottom {
    margin: 0 auto;
    grid-area: 3 / 2 / 3 / 4;
    width: 100%;
    min-height: 0;
}

.fui-editor__bottom-right {
    min-width: 230px;
    min-height: 0;
    height: auto;
    max-height: 100%;
    overflow-y: auto;
}

.fui-editor__canvas {
    height: 100%;
    max-height: 100%;
    flex-shrink: 1;
    overflow: hidden;
    position: relative;
}

/* code panel topbar: title + actions, never overlapping code */
.fui-code-topbar {
    display: flex;
    flex-direction: row;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    padding: 4px 10px 4px 14px;
    position: static;
}
/* uniform buttons in code topbar */
.fui-code-topbar__actions .btn {
    height: 32px;
    min-height: 32px;
    border-radius: 8px;
    font-size: 12px;
    padding: 0 12px;
    gap: 6px;
}

.fui-code-topbar__actions {
    flex-wrap: nowrap;
}

.fui-code-topbar__actions > .tooltip {
    height: 32px;
    display: flex;
    align-items: center;
    margin: 0;
    gap: 6px;
}

.fui-code-topbar__actions > .tooltip > * {
    display: inline-flex;
    align-items: center;
}
.fui-cloud-import-btn {
    border-radius: 8px;
    margin-right: 0;
    padding: 0 12px;
    height: 32px;
    min-height: 32px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
.fui-code-topbar__title {
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--inactive-color, #888);
}

/* layers modal on mobile */
.fui-layers-modal-trigger {
    display: none;
}
.fui-layers-modal {
    display: none;
}
/* toolbar row: tools stay in one row, they shrink if needed; zoom pushed right */
.fui-toolbar-row {
    flex-wrap: nowrap;
    min-width: 0;
    justify-content: flex-start;
    gap: 8px;
}
.fui-toolbar-row > * {
    min-width: 0;
}
.fui-toolbar-row .fui-scale {
    margin-left: auto;
    flex: 0 0 auto;
}
.fui-tools-area {
    flex: 1 1 auto;
    min-width: 0;
}
/* tools never wrap to a second row: shrink buttons and icons instead */
.fui-tools-area > div:first-child {
    flex-wrap: nowrap;
    min-width: 0;
    flex: 0 1 auto;
    overflow: hidden;
}
.fui-tools-area > div:first-child > div {
    min-width: 0;
    flex: 0 1 auto;
}
.fui-tools-area .btn-square {
    width: auto !important;
    min-width: 0;
    padding-left: 5px;
    padding-right: 5px;
}
.fui-tools-area .btn-square svg {
    max-width: 100%;
    width: 100% !important;
    height: auto !important;
    max-height: 20px;
    min-width: 0;
}
/* header selects framed */
.fui-editor-header-row {
    display: flex;
    flex-direction: row;
    flex-wrap: wrap;
    align-items: center;
    justify-content: flex-start;
    gap: 4px 10px;
    text-align: left;
    font-size: 0.875rem;
    line-height: 1.25rem;
    margin-bottom: 8px;
    padding: 6px 10px;
    border: 1px solid var(--border-color, rgba(255, 255, 255, 0.15));
    border-radius: 10px;
    background: color-mix(in srgb, var(--bg-color, #000) 35%, transparent);
    min-width: 0;
}
/* zoom block with frame */
.fui-scale {
    border: 1px solid var(--border-color, rgba(255, 255, 255, 0.15));
    border-radius: 8px;
    padding: 2px 8px;
    background: color-mix(in srgb, var(--bg-color, #000) 40%, transparent);
}

/* --- Mobile layout: single column, layers in modal --- */
@media (max-width: 768px) {
    .fui-editor {
        display: flex;
        flex-direction: column;
        height: 100vh;
        height: 100dvh;
        min-height: 0;
        overflow: hidden;
    }
    /* layers trigger lives inside the top frame, same row as zoom */
    .fui-layers-modal-trigger {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        flex: 0 0 auto;
        margin-left: 4px;
        height: 28px;
        min-height: 28px;
        font-size: 11px;
        padding: 0 8px;
        border-radius: 8px;
    }
    /* hide the desktop layers sidebar */
    .fui-editor__left {
        display: none;
    }
    /* modal itself must render on mobile (base rule keeps it hidden) */
    .fui-layers-modal {
        display: flex;
    }
    .fui-editor__top {
        display: flex;
        flex-direction: column;
        gap: 8px;
        flex: 0 0 auto;
        padding: 8px;
        margin: 8px 6px 0;
        border: 1px solid var(--border-color, rgba(255, 255, 255, 0.12));
        border-radius: 12px;
        background: color-mix(in srgb, var(--secondary-color) 55%, transparent);
    }
    .fui-editor__top .fui-editor-header-row {
        flex-wrap: wrap;
        border: none;
        background: transparent;
        padding: 0;
        margin-bottom: 0;
    }
    .fui-editor__top .flex-row {
        flex-wrap: wrap;
        gap: 8px;
    }
    /* narrow screens: tools take the full row, zoom+layers wrap below it */
    .fui-toolbar-row {
        flex-wrap: wrap;
    }
    .fui-toolbar-row .fui-tools-area {
        flex: 1 1 100%;
    }
    .fui-toolbar-row .fui-scale {
        margin-left: 0;
    }
    /* zoom slider shrinks on narrow phones so scale+layers fit one row */
    .fui-scale__range {
        width: 72px !important;
    }
    /* canvas box must clip the (transformed) canvas so it never widens the page */
    .fui-editor__main {
        overflow: hidden;
    }
    .fui-editor__canvas .canvas-wrapper {
        max-width: 100%;
    }
    /* strict zone separation */
    .fui-editor__main {
        margin: 8px 6px 0;
        border: 1px solid var(--border-color, rgba(255, 255, 255, 0.12));
        border-radius: 12px;
        padding: 6px;
        background: color-mix(in srgb, var(--bg-color) 60%, transparent);
    }
    .fui-editor__bottom {
        margin: 8px 6px 0;
        border: 1px solid var(--border-color, rgba(255, 255, 255, 0.12));
        border-radius: 12px;
        padding: 8px;
        background: color-mix(in srgb, var(--secondary-color) 55%, transparent);
    }
    .fui-layers-modal-trigger {
        display: inline-flex;
        margin-left: 4px;
    }
    .fui-editor__main-right {
        margin: 8px 6px 0;
        border: 1px solid var(--border-color, rgba(255, 255, 255, 0.12));
        border-radius: 12px;
        padding: 8px;
        background: color-mix(in srgb, var(--secondary-color) 55%, transparent);
        padding-right: 12px;
    }
    /* page scrolls vertically when boxes don't fit the screen */
    .fui-editor {
        overflow-y: auto;
        overflow-x: hidden;
        overscroll-behavior: contain;
        scrollbar-gutter: stable;
    }
    .fui-editor__main {
        height: auto;
        min-height: 0;
        flex: 0 0 auto;
        /* clip the transformed canvas so it never widens the page */
        overflow-x: hidden;
        overflow-y: visible;
    }
    .fui-editor__canvas {
        height: 38vw;
        max-height: 26vh;
        min-height: 140px;
        flex: 0 0 auto;
        display: flex;
        justify-content: center;
        align-items: center;
        overflow: hidden;
        /* drawing zone: touches must never scroll the page */
        touch-action: none;
    }
    /* canvas scales down to fit viewport width on mobile */
    .fui-editor__canvas .canvas-wrapper {
        transform-origin: 0 0;
    }
    /* touches on the canvas must never scroll the page */
    .fui-editor__canvas,
    .fui-editor__canvas .canvas-wrapper,
    .fui-editor__canvas .fui-grid,
    .fui-editor__canvas .screen {
        touch-action: none;
    }
    .fui-editor__main-right {
        border-left: none !important;
        margin: 8px 6px 0;
        width: calc(100% - 12px);
        padding-right: 12px;
        flex: 0 0 auto;
        min-height: 0;
        max-height: none;
        overflow: visible;
    }
    .fui-editor__bottom {
        flex-direction: column;
        padding: 8px;
        width: calc(100% - 12px);
        flex: 0 0 auto;
        min-height: 0;
        overflow: visible;
    }
    /* code box scrolls inside its own frame on mobile */
    .fui-editor__bottom .fui-code {
        overflow: auto;
        -webkit-overflow-scrolling: touch;
        overscroll-behavior: contain;
    }
    .fui-editor__bottom-right {
        min-width: 0;
        width: 100%;
        height: auto;
        min-height: 0;
        overflow-y: auto;
    }
    .fui-code-topbar {
        justify-content: space-between;
    }
.fui-code {
        height: 18vh;
        min-height: 110px;
    }
    /* topbar row must fit narrow screens: let buttons wrap to a second line */
    .fui-code-topbar__actions {
        flex-wrap: wrap;
        justify-content: flex-start;
        width: 100%;
    }
    .fui-code-topbar__actions .btn {
        padding: 0 8px;
        gap: 4px;
        font-size: 11px;
    }
    .fui-code-topbar__actions .btn svg {
        width: 14px;
        height: 14px;
    }
    .fui-code-topbar__actions > * {
        min-width: 0;
    }
}

@media (max-width: 340px) {
    /* very narrow screens: buttons wrap to two lines instead of overflowing */
    .fui-code-topbar__actions {
        flex-wrap: wrap;
        row-gap: 4px;
    }
    /* the Import+Cloud group also wraps internally */
    .fui-code-topbar__actions > .tooltip {
        flex-wrap: wrap;
        height: auto;
        max-width: 100%;
        row-gap: 4px;
    }
    .fui-code-topbar__actions .btn {
        flex-shrink: 1;
        min-width: 0;
    }
    .fui-code-topbar {
        padding-left: 8px;
        padding-right: 8px;
    }
}

@media (max-width: 420px) {
    .fui-editor__top .flex-row {
        justify-content: flex-start;
    }

    /* keep the three code buttons in one row on small phones */
    .fui-code-topbar__title {
        display: none;
    }
    .fui-code-topbar {
        justify-content: center;
    }
    .fui-code-topbar__actions .btn {
        padding: 0 6px;
        font-size: 11px;
        gap: 3px;
    }

    /* compact inspector: avoid content touching right edge */
    .fui-editor__main-right {
        padding-right: 10px;
        min-height: 28vh;
    }    .fui-code {
        height: 16vh;
        min-height: 100px;
    }
}

/* --- Scrollbars must never stick out past rounded box corners --- */
.fui-editor__left,
.fui-editor__main-right,
.fui-editor__bottom,
.fui-editor__bottom-right,
.fui-code {
    border-radius: 12px;
}
/* scrollable containers clip their scrollbars with the rounded corner */
.fui-editor__left,
.fui-editor__bottom-right {
    overflow-y: auto;
}
.fui-editor__bottom-right {
    border-radius: 12px;
}
.fui-code {
    border-radius: 8px;
}
/* keep gutters visually inside: thin scrollbar + right padding compensation */
.fui-editor__left,
.fui-editor__main-right {
    scrollbar-gutter: stable;
}
.fui-editor__left::-webkit-scrollbar:vertical,
.fui-editor__main-right::-webkit-scrollbar:vertical,
.fui-editor__bottom-right::-webkit-scrollbar:vertical,
.fui-code::-webkit-scrollbar:vertical {
    width: 8px;
}
.fui-code::-webkit-scrollbar:horizontal {
    height: 8px;
}
.fui-editor__left::-webkit-scrollbar-thumb,
.fui-editor__main-right::-webkit-scrollbar-thumb,
.fui-editor__bottom-right::-webkit-scrollbar-thumb,
.fui-code::-webkit-scrollbar-thumb {
    border-radius: 8px;
}
/* on mobile the page itself scrolls, so boxes don't need inner scroll */
@media (max-width: 768px) {
    .fui-editor__left,
    .fui-editor__main-right {
        overflow: visible;
    }
    .fui-code {
        border-radius: 8px;
    }
}
</style>
