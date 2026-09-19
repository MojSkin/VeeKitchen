/**
 * Formatting helpers shared across pages.
 */

const FA_DIGITS = '۰۱۲۳۴۵۶۷۸۹';

/**
 * Latin digits → Persian digits.
 */
export function faDigits(value) {
    return String(value).replace(/[0-9]/g, (d) => FA_DIGITS[Number(d)]);
}

/**
 * Grouped thousands + Persian digits: 1234567 → "۱٬۲۳۴٬۵۶۷".
 */
export function formatToman(value) {
    const grouped = Number(value).toLocaleString('en-US').replace(/,/g, '٬');

    return faDigits(grouped);
}

/**
 * "۱۲۳٬۴۵۶ تومان"
 */
export function formatTomanWithUnit(value) {
    return `${formatToman(value)} تومان`;
}

/**
 * Persian calendar-less clock format HH:mm in fa digits.
 */
export function formatClock(iso) {
    if (!iso) {
        return '';
    }

    const date = new Date(iso);
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');

    return faDigits(`${hours}:${minutes}`);
}

/**
 * Minutes elapsed since an ISO timestamp (for kitchen urgency colors).
 */
export function minutesSince(iso) {
    if (!iso) {
        return 0;
    }

    return Math.max(0, Math.floor((Date.now() - new Date(iso).getTime()) / 60000));
}

/**
 * Tailwind tone for an order status value (mirrors OrderStatus::tone()).
 */
export function statusTone(status) {
    return {
        awaiting_payment: 'amber',
        queued: 'sky',
        preparing: 'violet',
        ready: 'green',
        delivered: 'slate',
        cancelled: 'red',
    }[status] ?? 'slate';
}
