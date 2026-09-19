/**
 * Persian TTS for the pickup display.
 *
 * Strategy (per docs/PHASE-1-PLAN.md step 7):
 * 1. `speech` mode — Web Speech API with a fa-IR voice when the browser has one.
 * 2. `audio` mode — recorded clips (public/audio/tts/*.mp3) played in sequence,
 *    immune to missing OS voices. Used as fallback AND as the display default.
 */

let cachedVoices = null;

/**
 * All Persian voices available in this browser (may be empty on Windows).
 */
export function persianVoices() {
    if (typeof speechSynthesis === 'undefined') {
        return [];
    }

    cachedVoices ??= speechSynthesis.getVoices();

    return cachedVoices.filter((voice) => voice.lang.toLowerCase().startsWith('fa'));
}

/**
 * Which mode the display should use.
 *
 * @returns {'audio' | 'speech'}
 */
export function preferredMode() {
    return persianVoices().length > 0 ? 'speech' : 'audio';
}

const FA_DIGITS = '۰۱۲۳۴۵۶۷۸۹';

/**
 * Latin digits → Persian digits (for clip naming we keep latin numbers).
 */
export function toLatinDigits(value) {
    return String(value).replace(/[۰-۹]/g, (d) => FA_DIGITS.indexOf(d));
}

/**
 * Split a number into spoken parts: ۲۳ → ["20", "3"] handled by clip names.
 */
function numberParts(number) {
    const n = Number(number);

    if (Number.isNaN(n) || n < 0) {
        return [];
    }

    if (n === 0) {
        return ['0'];
    }

    const parts = [];
    const hundreds = Math.floor(n / 100);
    const remainder = n % 100;

    if (hundreds > 0) {
        parts.push(String(hundreds) + '00');
    }

    if (remainder > 0) {
        parts.push(String(remainder));
    }

    return parts;
}

const CLIP_MAP = {
    shomare: 'shomare',
    size: 'size',
    meez: 'meez',
};

/**
 * Clip filename for a token: "shomare", "meez", "size", numbers 1..99 & 100s.
 */
function clipFile(token) {
    if (CLIP_MAP[token]) {
        return CLIP_MAP[token];
    }

    return toLatinDigits(token);
}

/**
 * Announce via recorded clips: "شماره سه، میز پنج".
 *
 * @param {(token: string) => string} resolveClip maps token → audio URL
 */
function speakWithAudio(tokens, resolveClip) {
    const audio = new Audio();
    let index = 0;

    return new Promise((resolve) => {
        const playNext = () => {
            if (index >= tokens.length) {
                resolve();
                return;
            }

            audio.src = resolveClip(clipFile(tokens[index++]));
            audio.onended = playNext;
            audio.onerror = playNext;
            audio.play().catch(playNext);
        };

        playNext();
    });
}

/**
 * Announce via Web Speech API.
 */
function speakWithSpeech(text, voice) {
    return new Promise((resolve) => {
        const utterance = new SpeechSynthesisUtterance(text);
        utterance.lang = 'fa-IR';
        utterance.rate = 0.9;

        if (voice) {
            utterance.voice = voice;
        }

        utterance.onend = resolve;
        utterance.onerror = resolve;

        speechSynthesis.speak(utterance);
    });
}

/**
 * Build the announcement tokens for an order.
 *
 * @param {{orderNumber: number|null, tableLabel: string|null}} order
 * @returns {string[]} e.g. ["shomare", "5", "meez", "3"]
 */
export function announcementTokens({ orderNumber, tableLabel }) {
    const tokens = ['shomare'];

    tokens.push(...numberParts(orderNumber));

    if (tableLabel) {
        const tableNumber = toLatinDigits(String(tableLabel).replace(/[^۰-۹0-9]/g, ''));

        if (tableNumber !== '') {
            tokens.push('meez', ...numberParts(tableNumber));
        }
    }

    return tokens;
}

/**
 * Speak an announcement in the given mode.
 *
 * @param {{orderNumber: number|null, tableLabel: string|null}} order
 * @param {{mode?: 'audio'|'speech', voice?: SpeechSynthesisVoice|null, clipUrl?: (token: string) => string}} options
 */
export async function announceOrder(order, options = {}) {
    const mode = options.mode ?? preferredMode();

    if (mode === 'speech') {
        const voice = options.voice ?? persianVoices()[0] ?? null;
        const text = tokensToText(announcementTokens(order));
        await speakWithSpeech(text, voice);
        return;
    }

    const resolveClip = options.clipUrl ?? ((token) => `/audio/tts/${token}.mp3`);
    await speakWithAudio(announcementTokens(order), resolveClip);
}

/**
 * Human-readable text for speech mode: "شماره ۵، میز ۳".
 */
function tokensToText(tokens) {
    return tokens
        .map((token) => (token === 'shomare' ? 'شماره' : token === 'meez' ? 'میز' : toLatinDigits(token)))
        .join(' ');
}
