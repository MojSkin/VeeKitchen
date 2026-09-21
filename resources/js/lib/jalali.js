/**
 * Jalali presentation helpers — the only date format the UI ever shows.
 *
 * Values travel as Gregorian ISO strings (the backend's wire format), but
 * every rendered day/label goes through here, so Gregorian numerals never
 * reach the screen.
 */
import * as jf from 'date-fns-jalali';
import { faDigits } from './format';

function pad(value) {
    return String(value).padStart(2, '0');
}

/**
 * Jalali day: «۱۴۰۴/۰۷/۰۱» — UTC parts so an end-of-day ISO boundary
 * never slides the day into tomorrow.
 */
export function formatJalaliDay(iso) {
    if (!iso) {
        return '';
    }

    const date = new Date(iso);

    return faDigits(`${jf.getYear(date)}/${pad(jf.getMonth(date) + 1)}/${pad(jf.getDate(date))}`);
}

/**
 * Jalali day + clock: «۱۴۰۴/۰۷/۰۱ · ۱۴:۳۵».
 */
export function formatJalaliDayTime(iso) {
    if (!iso) {
        return '';
    }

    const date = new Date(iso);

    return `${formatJalaliDay(iso)} · ${faDigits(pad(date.getHours()) + ':' + pad(date.getMinutes()))}`;
}

/**
 * Short Jalali label for chart axes: «۱ مهر».
 */
export function formatJalaliShort(iso) {
    if (!iso) {
        return '';
    }

    const MONTHS = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
    const date = new Date(iso);

    return `${faDigits(jf.getDate(date))} ${MONTHS[jf.getMonth(date)]}`;
}

/**
 * The ISO date string (Y-m-d, UTC parts) a Jalali date picker value
 * serializes to — the wire format the backend's date filters expect.
 */
export function jalaliValueToIso(date) {
    const year = date.getFullYear();
    const month = pad(date.getMonth() + 1);
    const day = pad(date.getDate());

    return `${year}-${month}-${day}T12:00:00.000Z`;
}
