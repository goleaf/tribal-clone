document.addEventListener('DOMContentLoaded', () => {
    const navToggle = document.getElementById('nav-toggle');
    const nav = document.getElementById('primary-nav');
    const offlineIndicator = document.getElementById('offline-indicator');
    const requiresOnlineNodes = document.querySelectorAll('[data-requires-online]');

    function setOfflineState(isOffline) {
        window.appOffline = isOffline;
        document.body.classList.toggle('body-offline', isOffline);
        if (offlineIndicator) {
            offlineIndicator.style.display = isOffline ? 'inline-flex' : 'none';
        }
        requiresOnlineNodes.forEach((node) => {
            const tag = (node.tagName || '').toLowerCase();
            const type = (node.getAttribute('type') || '').toLowerCase();
            const isButtonLike = tag === 'button' || (tag === 'input' && (type === 'submit' || type === 'button'));
            if (isButtonLike) {
                node.disabled = isOffline;
            }
            node.classList.toggle('disabled-offline', isOffline);
            if (isOffline) {
                node.setAttribute('aria-disabled', 'true');
            } else {
                node.removeAttribute('aria-disabled');
            }
        });
    }

    window.addEventListener('offline', () => setOfflineState(true));
    window.addEventListener('online', () => setOfflineState(false));
    setOfflineState(!navigator.onLine);

    if (navToggle && nav) {
        const closeNav = () => {
            nav.classList.remove('is-open');
            navToggle.setAttribute('aria-expanded', 'false');
        };

        navToggle.addEventListener('click', (event) => {
            event.stopPropagation();
            const isOpen = nav.classList.toggle('is-open');
            navToggle.setAttribute('aria-expanded', String(isOpen));
        });

        document.addEventListener('click', (event) => {
            if (!nav.classList.contains('is-open')) return;
            const isInsideNav = nav.contains(event.target);
            const isToggle = navToggle.contains(event.target);
            if (!isInsideNav && !isToggle) {
                closeNav();
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && nav.classList.contains('is-open')) {
                closeNav();
            }
        });

        nav.querySelectorAll('a').forEach((link) => {
            link.addEventListener('click', () => {
                closeNav();
            });
        });
    }
});
