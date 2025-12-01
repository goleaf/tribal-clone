'use strict';
// JavaScript helper functions

/**
 * Format a number with thousand separators
 * @param {number} number Number to format
 * @returns {string} Formatted number
 */
function formatNumber(number) {
    try {
        return new Intl.NumberFormat('en-US').format(number);
    } catch (e) {
        // Fallback for unexpected input
        return `${number}`;
    }
}

/**
 * Format seconds into HH:MM:SS
 * @param {number} seconds Time in seconds
 * @returns {string} Formatted time
 */
function formatTime(seconds) {
    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    const s = seconds % 60;
    return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
}

/**
 * Format remaining time until task finishes.
 * @param {number} finishTime Finish timestamp (seconds).
 * @returns {string} Formatted remaining time (e.g., "1h 30m 15s").
 */
function getRemainingTimeText(finishTime) {
    if (finishTime === null) return '';
    const finishTimeMillis = finishTime * 1000;
    const currentTimeMillis = new Date().getTime();
    const remainingMillis = finishTimeMillis - currentTimeMillis;

    if (remainingMillis <= 0) return 'Completed!';

    const seconds = Math.floor((remainingMillis / 1000) % 60);
    const minutes = Math.floor((remainingMillis / (1000 * 60)) % 60);
    const hours = Math.floor((remainingMillis / (1000 * 60 * 60)) % 24);
    const days = Math.floor(remainingMillis / (1000 * 60 * 60 * 24));

    let timeString = '';
    if (days > 0) timeString += days + 'd ';
    if (hours > 0 || days > 0) timeString += hours + 'h ';
    timeString += minutes + 'm ' + seconds + 's';

    return timeString.trim();
}

/**
 * Clamp a number between min and max.
 * @param {number} value
 * @param {number} min
 * @param {number} max
 * @returns {number}
 */
function clamp(value, min, max) {
    return Math.min(Math.max(value, min), max);
}

/**
 * Debounce a function call.
 * @param {Function} fn
 * @param {number} delay
 * @returns {Function}
 */
function debounce(fn, delay = 250) {
    let timeoutId;
    return (...args) => {
        clearTimeout(timeoutId);
        timeoutId = setTimeout(() => fn(...args), delay);
    };
}

// Expose helpers globally for legacy scripts
window.formatNumber = window.formatNumber || formatNumber;
window.formatTime = window.formatTime || formatTime;
window.getRemainingTimeText = window.getRemainingTimeText || getRemainingTimeText;
window.clamp = window.clamp || clamp;
window.debounce = window.debounce || debounce;
