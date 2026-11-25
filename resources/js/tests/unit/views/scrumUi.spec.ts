import { describe, it, expect, vi, afterEach } from 'vitest';
import { formatDueDate, isOverdue } from '@/views/apps/scrumboard/scrumUi';

describe('scrumboard due dates (calendar dates, no UTC shift)', () => {
    afterEach(() => {
        vi.useRealTimers();
    });

    it('formats the date-only string as is', () => {
        expect(formatDueDate('2026-11-24')).toBe('2026-11-24');
    });

    it('is not overdue on the due day itself, late in the local evening', () => {
        vi.useFakeTimers();
        vi.setSystemTime(new Date(2026, 10, 24, 23, 30)); // local time, Nov 24
        expect(isOverdue('2026-11-24')).toBe(false);
    });

    it('is overdue from the next local day', () => {
        vi.useFakeTimers();
        vi.setSystemTime(new Date(2026, 10, 25, 0, 5)); // local time, Nov 25
        expect(isOverdue('2026-11-24')).toBe(true);
        expect(isOverdue('2026-11-25')).toBe(false);
    });

    it('handles missing dates', () => {
        expect(isOverdue(null)).toBe(false);
        expect(isOverdue(undefined)).toBe(false);
    });
});
