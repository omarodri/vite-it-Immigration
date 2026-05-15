/**
 * Triple-click activation composable for the extended (hidden) tab.
 *
 * Intentionally uses neutral naming in the implementation: no business
 * strings ("IRCC", "secret", "vault") appear here. The composable simply
 * detects a triple-click on any element, calls a backend availability
 * endpoint, and exposes a reactive `visible` flag.
 */

import { ref } from 'vue';
import type { Ref } from 'vue';
import irccCredentialsService from '@/services/irccCredentialsService';

const MAX_CLICK_GAP_MS = 800;
const TRIGGER_COUNT = 3;

export function useExtendedTab(caseId: Ref<number | undefined>) {
    const visible = ref(false);
    const clickCount = ref(0);
    const lastClickAt = ref(0);
    const isChecking = ref(false);

    function onTriggerClick(): void {
        const now = Date.now();
        if (now - lastClickAt.value > MAX_CLICK_GAP_MS) {
            clickCount.value = 0;
        }
        clickCount.value++;
        lastClickAt.value = now;

        if (clickCount.value >= TRIGGER_COUNT) {
            clickCount.value = 0;
            void checkAccess();
        }
    }

    async function checkAccess(): Promise<void> {
        if (isChecking.value || !caseId.value) return;
        isChecking.value = true;
        try {
            const { data } = await irccCredentialsService.getAvailability(caseId.value);
            if (data.available) {
                visible.value = true;
            }
        } catch {
            // Silent fail — no access, no UI change.
        } finally {
            isChecking.value = false;
        }
    }

    function deactivate(): void {
        visible.value = false;
        clickCount.value = 0;
        lastClickAt.value = 0;
    }

    return { visible, isChecking, onTriggerClick, deactivate };
}

export default useExtendedTab;
