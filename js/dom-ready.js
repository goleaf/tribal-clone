'use strict';

/**
 * Tiny DOM ready helper to avoid duplicate listeners.
 */
export function onDomReady(callback) {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', callback, { once: true });
    } else {
        callback();
    }
}

/**
 * Safe query helper returning an array.
 */
export function $all(selector, scope = document) {
    return Array.prototype.slice.call(scope.querySelectorAll(selector));
}

/**
 * Legacy-friendly ready helper without modules.
 */
export function ready(callback) {
    onDomReady(callback);
}
