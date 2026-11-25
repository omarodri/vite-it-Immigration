<template>
    <!-- Spec 71 R8 — keyboard-accessible alternative to drag & drop -->
    <div ref="rootEl" class="relative" @keydown.esc.stop.prevent="close(true)" @focusout="onFocusOut">
        <button
            ref="triggerEl"
            type="button"
            class="hover:text-primary"
            :title="$t('scrum_move_to')"
            :aria-label="$t('scrum_move_to')"
            aria-haspopup="menu"
            :aria-expanded="isOpen"
            @click.stop="toggle"
            @keydown.enter.stop
            @keydown.down.prevent.stop="openAndFocus(0)"
        >
            <icon-arrow-forward class="w-4 h-4 rtl:rotate-180" />
        </button>
        <ul
            v-if="isOpen"
            role="menu"
            :aria-label="$t('scrum_move_to')"
            class="absolute z-30 mt-1 ltr:right-0 rtl:left-0 min-w-[11rem] max-w-[16rem] rounded-md bg-white dark:bg-[#1b2e4b] shadow-lg border border-white-light dark:border-[#253b5c] py-1 text-sm text-black dark:text-white-dark"
            @click.stop
            @keydown.enter.stop
        >
            <li role="presentation" class="px-3 py-1 text-xs text-gray-400">{{ $t('scrum_move_to') }}</li>
            <li v-for="(column, i) in targets" :key="column.id" role="none">
                <button
                    :ref="(el) => setItemRef(el, i)"
                    type="button"
                    role="menuitem"
                    class="w-full truncate text-start px-3 py-1.5 outline-none hover:bg-primary/10 focus:bg-primary/10"
                    @click="select(column.id)"
                    @keydown.down.prevent="focusItem(i + 1)"
                    @keydown.up.prevent="focusItem(i - 1)"
                    @keydown.home.prevent="focusItem(0)"
                    @keydown.end.prevent="focusItem(targets.length - 1)"
                >
                    {{ column.title }}
                </button>
            </li>
        </ul>
    </div>
</template>

<script lang="ts" setup>
import { ref, computed, nextTick, onBeforeUnmount } from 'vue';
import IconArrowForward from '@/components/icon/icon-arrow-forward.vue';
import type { ScrumColumn } from '@/types/scrum';

const props = defineProps<{
    columns: ScrumColumn[];
    currentColumnId: number;
}>();

const emit = defineEmits<{ select: [columnId: number] }>();

const isOpen = ref(false);
const rootEl = ref<HTMLElement | null>(null);
const triggerEl = ref<HTMLButtonElement | null>(null);
const itemEls: HTMLButtonElement[] = [];

const targets = computed(() => props.columns.filter((c) => c.id !== props.currentColumnId));

function setItemRef(el: unknown, i: number) {
    if (el) itemEls[i] = el as HTMLButtonElement;
}

function onDocumentPointerDown(e: PointerEvent) {
    if (rootEl.value && !rootEl.value.contains(e.target as Node)) close(false);
}

function open() {
    isOpen.value = true;
    document.addEventListener('pointerdown', onDocumentPointerDown, true);
}

function close(returnFocus: boolean) {
    if (!isOpen.value) return;
    isOpen.value = false;
    document.removeEventListener('pointerdown', onDocumentPointerDown, true);
    if (returnFocus) triggerEl.value?.focus();
}

function toggle() {
    if (isOpen.value) close(false);
    else openAndFocus(0);
}

async function openAndFocus(index: number) {
    if (!isOpen.value) open();
    await nextTick();
    focusItem(index);
}

function focusItem(index: number) {
    const count = targets.value.length;
    if (count === 0) return;
    itemEls[(index + count) % count]?.focus();
}

/**
 * Close when keyboard focus moves to another element outside the menu (e.g. Tab).
 * relatedTarget is null when clicking a button in macOS Safari/Firefox (buttons do not take
 * focus on click): closing then would unmount the item before its click fires. Outside clicks
 * are handled by the pointerdown listener instead.
 */
function onFocusOut(e: FocusEvent) {
    const next = e.relatedTarget as Node | null;
    if (!next || rootEl.value?.contains(next)) return;
    close(false);
}

function select(columnId: number) {
    close(false);
    emit('select', columnId);
}

onBeforeUnmount(() => document.removeEventListener('pointerdown', onDocumentPointerDown, true));
</script>
