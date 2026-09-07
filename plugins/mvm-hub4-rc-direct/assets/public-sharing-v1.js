(() => {
    'use strict';

    const fallbackCopy = (text) => {
        const field = document.createElement('textarea');
        field.value = text;
        field.setAttribute('readonly', 'readonly');
        field.style.position = 'fixed';
        field.style.opacity = '0';
        document.body.appendChild(field);
        field.select();
        const copied = document.execCommand('copy');
        field.remove();
        return copied;
    };

    document.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-mvm-share-copy]');
        if (!button) return;

        const url = String(button.getAttribute('data-mvm-share-copy') || '').trim();
        if (!/^https?:\/\//i.test(url)) return;

        const block = button.closest('[data-mvm-share-v1]');
        const status = block ? block.querySelector('[data-mvm-share-status]') : null;
        const original = button.textContent;
        button.disabled = true;

        try {
            if (navigator.clipboard && window.isSecureContext) {
                await navigator.clipboard.writeText(url);
            } else if (!fallbackCopy(url)) {
                throw new Error('copy_failed');
            }
            button.textContent = 'Gekopieerd';
            if (status) status.textContent = 'Link gekopieerd.';
        } catch (error) {
            if (status) status.textContent = 'Kopiëren lukte niet. Open de pagina-URL handmatig.';
        } finally {
            window.setTimeout(() => {
                button.disabled = false;
                button.textContent = original;
            }, 1600);
        }
    });
})();
