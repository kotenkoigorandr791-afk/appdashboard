<script lang="ts" setup>
import { computed } from 'vue';

function tr(s: string): string {
    const i18n = (window as any).__lopakaI18n;
    if (i18n?.translate) return i18n.translate(s) ?? s;
    return s;
}

interface ShortcutItem {
    keys: string;
    action: string;
}

interface ShortcutGroup {
    title: string;
    items: ShortcutItem[];
}

const groups = computed<ShortcutGroup[]>(() => {
    const isMac = /mac|iphone|ipad/i.test(navigator.platform || navigator.userAgent);
    const mod = isMac ? '⌘' : 'Ctrl';
    return [
        {
            title: tr('General'),
            items: [
                { keys: `${mod} + Z`, action: tr('Undo') },
                { keys: `${mod} + C`, action: tr('Copy code') },
                { keys: `${mod} + V`, action: tr('Paste / import code') },
                { keys: `Del`, action: tr('Delete selection') },
                { keys: `Esc`, action: tr('Deselect / cancel') },
            ],
        },
        {
            title: tr('Tools'),
            items: [
                { keys: `V`, action: tr('Select tool') },
                { keys: `T`, action: tr('Text tool') },
                { keys: `B`, action: tr('Paint brush') },
                { keys: `R`, action: tr('Rectangle') },
                { keys: `O`, action: tr('Ellipse') },
                { keys: `L`, action: tr('Line') },
                { keys: `I`, action: tr('Image import') },
            ],
        },
        {
            title: tr('Move & resize'),
            items: [
                { keys: `Arrows`, action: tr('Move by 1px') },
                { keys: `Shift + Arrows`, action: tr('Move by 10px') },
                { keys: `Alt + Arrows`, action: tr('Resize by 1px') },
            ],
        },
    ];
});
</script>
<template>
    <div class="fui-shortcuts">
        <div
            v-for="group in groups"
            :key="group.title"
            class="fui-shortcuts__group"
        >
            <div class="fui-shortcuts__title">{{ group.title }}</div>
            <div
                v-for="item in group.items"
                :key="item.keys + item.action"
                class="fui-shortcuts__row"
            >
                <span class="fui-shortcuts__action">{{ item.action }}</span>
                <span class="fui-shortcuts__keys">{{ item.keys }}</span>
            </div>
        </div>
    </div>
</template>
<style lang="css">
.fui-shortcuts {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 16px;
    width: 100%;
}

.fui-shortcuts__group {
    display: flex;
    flex-direction: column;
    gap: 6px;
    padding: 10px 12px;
    border: 1px solid var(--border-color, rgba(255, 255, 255, 0.1));
    border-radius: 10px;
    background: color-mix(in srgb, var(--secondary-color) 60%, transparent);
}

.fui-shortcuts__title {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--accent-color);
    margin-bottom: 2px;
}

.fui-shortcuts__row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    font-size: 12px;
}

.fui-shortcuts__action {
    color: var(--text-color, inherit);
    opacity: 0.85;
}

.fui-shortcuts__keys {
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    font-size: 11px;
    padding: 1px 7px;
    border-radius: 5px;
    background: var(--bg-color, rgba(127, 127, 127, 0.18));
    border: 1px solid var(--border-color, rgba(255, 255, 255, 0.08));
    white-space: nowrap;
}

/* hide shortcuts panel entirely on mobile */
@media (max-width: 768px) {
    .fui-shortcuts {
        display: none;
    }
}
</style>
