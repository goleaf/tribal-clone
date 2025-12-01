'use strict';

/**
 * Lightweight helpers for message forms:
 * - prevents accidental double submits
 * - shows live character counts
 */

document.addEventListener('DOMContentLoaded', () => {
    const forms = document.querySelectorAll('form[data-prevent-double-submit="true"]');
    forms.forEach(form => {
        form.addEventListener('submit', () => {
            const submitButtons = form.querySelectorAll('button[type="submit"], input[type="submit"]');
            submitButtons.forEach(btn => btn.disabled = true);
        });
    });

    const counters = document.querySelectorAll('[data-count-target]');
    counters.forEach(counter => {
        const targetId = counter.dataset.countTarget;
        const target = document.getElementById(targetId);
        if (!target) return;

        const max = parseInt(target.getAttribute('maxlength') || '0', 10);
        counter.setAttribute('aria-live', 'polite');
        const update = () => {
            const remaining = max ? (max - target.value.length) : target.value.length;
            counter.textContent = max ? `${remaining} chars left` : `${remaining} chars`;
            if (max) {
                const pct = (target.value.length / max) * 100;
                counter.classList.toggle('near-limit', pct >= 90);
            }
        };

        target.addEventListener('input', update);
        update();
    });
});
