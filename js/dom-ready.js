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

/**
 * Add an event listener that auto-removes after first call.
 */
export function once(target, eventName, handler, options = {}) {
    const wrapped = (evt) => {
        target.removeEventListener(eventName, wrapped, options);
        handler(evt);
    };
    target.addEventListener(eventName, wrapped, options);
}
