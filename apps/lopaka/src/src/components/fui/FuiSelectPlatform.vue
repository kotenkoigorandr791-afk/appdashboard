<script
    lang="ts"
    setup
>
import { computed, onMounted, onUnmounted, ref, toRefs, watch } from 'vue';
import { useSession } from '../../core/session';

const session = useSession();
const { preparePlatform } = session;
const { platform } = toRefs(session.state);
watch(platform, async (val) => {
    await preparePlatform(val, true);
});

const isOpen = ref(false);
const rootRef = ref<HTMLElement | null>(null);

const platformList = computed(() => {
    const list: { id: string; name: string }[] = [];
    const platforms = session.platforms;
    if (Array.isArray(platforms)) {
        platforms.forEach((p: any, idx: number) => {
            list.push({ id: p?.id ?? String(idx), name: p?.getName?.() ?? String(p) });
        });
    } else {
        Object.keys(platforms ?? {}).forEach((id) => {
            const p = (platforms as any)[id];
            list.push({ id, name: p?.getName?.() ?? id });
        });
    }
    return list;
});

const currentPlatform = computed(
    () => platformList.value.find((p) => p.id === platform.value)?.name ?? platform.value
);

function select(id: string) {
    platform.value = id;
    isOpen.value = false;
}

function onDocClick(e: MouseEvent) {
    if (rootRef.value && !rootRef.value.contains(e.target as Node)) isOpen.value = false;
}
function onKey(e: KeyboardEvent) {
    if (e.key === 'Escape') isOpen.value = false;
}
onMounted(() => {
    document.addEventListener('click', onDocClick);
    document.addEventListener('keydown', onKey);
});
onUnmounted(() => {
    document.removeEventListener('click', onDocClick);
    document.removeEventListener('keydown', onKey);
});
</script>
<template>
    <div
        class="fui-select fui-platforms"
        ref="rootRef"
    >
        <label
            for="library"
            class="fui-select__label pr-2"
        >
            Library:
        </label>
        <div
            class="fui-platform-dropdown"
            :class="{ 'is-open': isOpen }"
        >
            <button
                id="library"
                type="button"
                class="fui-platform-dropdown__toggle fui-form-input"
                @click="isOpen = !isOpen"
                :aria-expanded="isOpen"
            >
                <span class="fui-platform-dropdown__label">{{ currentPlatform }}</span>
                <span class="fui-platform-dropdown__arrow">
                    <svg
                        width="10"
                        height="6"
                        viewBox="0 0 10 6"
                        fill="none"
                    >
                        <path
                            d="M1 1l4 4 4-4"
                            stroke="currentColor"
                            stroke-width="1.6"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        />
                    </svg>
                </span>
            </button>
            <transition name="fui-dd">
                <ul
                    v-if="isOpen"
                    class="fui-platform-dropdown__menu"
                    role="listbox"
                >
                    <li
                        v-for="p in platformList"
                        :key="p.id"
                    >
                        <button
                            type="button"
                            class="fui-platform-dropdown__item"
                            :class="{ 'is-active': p.id === platform }"
                            role="option"
                            :aria-selected="p.id === platform"
                            @click="select(p.id)"
                        >
                            <span class="fui-platform-dropdown__check">
                                <svg
                                    v-if="p.id === platform"
                                    width="12"
                                    height="12"
                                    viewBox="0 0 12 12"
                                    fill="none"
                                >
                                    <path
                                        d="M2 6.5L4.8 9 10 3.2"
                                        stroke="currentColor"
                                        stroke-width="1.8"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                    />
                                </svg>
                            </span>
                            <span>{{ p.name }}</span>
                        </button>
                    </li>
                </ul>
            </transition>
        </div>
    </div>
</template>
<style lang="css">
.fui-platform-dropdown {
    position: relative;
    min-width: 170px;
}

.fui-platform-dropdown__toggle {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
    gap: 8px;
    cursor: pointer;
    text-align: left;
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.fui-platform-dropdown__toggle:focus,
.fui-platform-dropdown.is-open .fui-platform-dropdown__toggle {
    border-color: var(--accent-color);
    box-shadow: 0 0 0 2px color-mix(in srgb, var(--accent-color) 25%, transparent);
    outline: none;
}

.fui-platform-dropdown__arrow {
    display: inline-flex;
    color: var(--inactive-color, #888);
    transition: transform 0.18s ease;
}

.fui-platform-dropdown.is-open .fui-platform-dropdown__arrow {
    transform: rotate(180deg);
}

.fui-platform-dropdown__menu {
    position: absolute;
    top: calc(100% + 4px);
    left: 0;
    z-index: 60;
    min-width: 100%;
    max-height: 280px;
    overflow-y: auto;
    margin: 0;
    padding: 4px;
    list-style: none;
    background: var(--secondary-color);
    border: 1px solid var(--border-color, rgba(255, 255, 255, 0.15));
    border-radius: 8px;
    box-shadow: 0 8px 24px var(--shadow-color, rgba(0, 0, 0, 0.4));
}

.fui-platform-dropdown__item {
    display: flex;
    align-items: center;
    gap: 6px;
    width: 100%;
    padding: 7px 8px;
    border: none;
    border-radius: 6px;
    background: transparent;
    color: inherit;
    font: inherit;
    text-align: left;
    cursor: pointer;
    transition: background 0.12s ease;
}

.fui-platform-dropdown__item:hover {
    background: color-mix(in srgb, var(--accent-color) 15%, transparent);
}

.fui-platform-dropdown__item.is-active {
    color: var(--accent-color);
    font-weight: 600;
}

.fui-platform-dropdown__check {
    display: inline-flex;
    width: 14px;
    height: 12px;
    flex-shrink: 0;
    color: var(--accent-color);
}

.fui-dd-enter-active,
.fui-dd-leave-active {
    transition: opacity 0.14s ease, transform 0.14s ease;
}

.fui-dd-enter-from,
.fui-dd-leave-to {
    opacity: 0;
    transform: translateY(-4px);
}

@media (max-width: 768px) {
    .fui-platform-dropdown {
        min-width: 150px;
    }
    .fui-platform-dropdown__menu {
        max-height: 220px;
    }
}
</style>
