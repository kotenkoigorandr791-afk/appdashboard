<script
    lang="ts"
    setup
>
import { nextTick, onMounted, onUnmounted, ref, shallowRef, toRefs, watch } from 'vue';
import { EditorView, lineNumbers, keymap } from '@codemirror/view';
import { EditorState } from '@codemirror/state';
import { cpp } from '@codemirror/lang-cpp';
import { oneDark } from '@codemirror/theme-one-dark';
import { indentWithTab } from '@codemirror/commands';
import { useSession } from '../../core/session';
import { debounce } from '../../utils';

const props = defineProps<{
    readonly: boolean;
}>();

const session = useSession();
const { immidiateUpdates, selectionUpdates } = toRefs(session.state);
const content = shallowRef('');
const editorRef = shallowRef<EditorView | null>(null);
const rootRef = shallowRef<HTMLElement | null>(null);

/* theme sync: oneDark in dark theme, default light in light theme */
const isLight = () => document.documentElement.getAttribute('data-theme') === 'lopaka-light';

function buildExtensions() {
    const exts = [
        lineNumbers(),
        cpp(),
        keymap.of([indentWithTab]),
        EditorView.lineWrapping,
        EditorView.editable.of(!props.readonly),
        EditorView.updateListener.of((update) => {
            if (update.docChanged) {
                content.value = update.state.doc.toString();
            }
        }),
    ];
    if (!isLight()) exts.push(oneDark);
    return exts;
}

function createEditor() {
    if (editorRef.value) {
        editorRef.value.destroy();
    }
    const state = EditorState.create({
        doc: content.value,
        extensions: buildExtensions(),
    });
    editorRef.value = new EditorView({
        state,
        parent: rootRef.value,
    });
}

/* react to theme changes from the dashboard integration */
const themeObserver = new MutationObserver(() => {
    createEditor();
});

onMounted(() => {
    createEditor();
    themeObserver.observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });
    watch(immidiateUpdates, onUpdate, { immediate: true });
});

onUnmounted(() => {
    themeObserver.disconnect();
    editorRef.value?.destroy();
});

watch(
    selectionUpdates,
    () => {
        selectRow(true);
    },
    { deep: true }
);

function selectRow(shouldScroll: boolean) {
    const { selected } = session.layersManager;
    if (selected.length == 1 && editorRef.value) {
        const layer = selected[0];
        const row = layersMap[layer.uid]?.line;
        if (row) {
            const pos = editorRef.value.state.doc.line(Math.min(row + 1, editorRef.value.state.doc.lines)).from;
            editorRef.value.dispatch({
                selection: { anchor: pos },
                effects: shouldScroll ? EditorView.scrollIntoView(pos, { y: 'center' }) : [],
            });
        }
    }
}
function onUpdate() {
    const sourceCode = session.generateCode();
    content.value = sourceCode.code ?? '';
    layersMap = sourceCode.map;
    nextTick(() => {
        if (editorRef.value) {
            const cur = editorRef.value.state.doc.toString();
            if (cur !== content.value) {
                editorRef.value.dispatch({
                    changes: { from: 0, to: cur.length, insert: content.value },
                });
            }
            // While the user is interacting with the canvas, code is regenerated often.
            // Autoscrolling the CodeMirror view can cause the whole page to jump.
            // So we only update selection without scrolling here.
            selectRow(false);
        }
    });
}
let layersMap = {};

function onChange() {
    if (!editorRef.value) return;
    const row = editorRef.value.state.doc.lineAt(editorRef.value.state.selection.main.head).number - 1;
    const uid = Object.keys(layersMap).find((key) => layersMap[key].line === row);
    if (uid) {
        const layer = session.layersManager.getLayer(uid);
        session.layersManager.clearSelection();
        session.virtualScreen.redraw();
        session.layersManager.selectLayer(layer);
    }
}

const debouncedChange = debounce(() => onChange(), 500);

function onPaste(e: ClipboardEvent) {
    if (!rootRef.value?.contains(document.activeElement)) return;
    const text = e.clipboardData?.getData('text/plain');
    if (text) {
        session.importCode(text, true);
    }
}

onMounted(() => {
    document.addEventListener('paste', onPaste);
});

onUnmounted(() => {
    document.removeEventListener('paste', onPaste);
});
</script>
<template>
    <div
        ref="rootRef"
        class="fui-code"
    ></div>
</template>
<style lang="css">
.fui-code {
    height: 25vh;
    min-height: 200px;
    text-transform: none;
    overflow: auto;
    border-radius: 8px;
}

.fui-code:focus {
    outline: none;
}

.fui-code .cm-editor {
    height: 100%;
    font-size: 12px;
    background: color-mix(in srgb, var(--secondary-color) 92%, var(--bg-color));
}

[data-theme='lopaka-light'] .fui-code .cm-editor {
    background: #fafafa;
}

.fui-code .cm-editor.cm-focused {
    outline: none;
}

/* code must be scrollable inside its own box (especially on mobile) */
.fui-code .cm-scroller {
    overflow: auto;
    -webkit-overflow-scrolling: touch;
    overscroll-behavior: contain;
}

.fui-code .cm-gutters {
    background: transparent;
    border-right: 1px solid color-mix(in srgb, var(--border-color, #888) 40%, transparent);
}

[data-theme='lopaka'] .fui-code .cm-gutters {
    background: transparent;
}
</style>
