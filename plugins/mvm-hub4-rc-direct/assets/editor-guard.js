(() => {
    'use strict';

    const config = window.MvMHub4EditorGuard || {};
    let classicDirty = false;
    let submitting = false;

    const blockEditorDirty = () => {
        try {
            if (!window.wp || !wp.data || !wp.data.select) {
                return false;
            }
            const editor = wp.data.select('core/editor');
            return Boolean(editor && editor.isEditedPostDirty && editor.isEditedPostDirty());
        } catch (error) {
            return false;
        }
    };

    const isDirty = () => !submitting && (classicDirty || blockEditorDirty());
    const message = config.message || 'Je hebt nog niet-opgeslagen wijzigingen.';

    const beforeUnload = (event) => {
        if (!isDirty()) {
            return;
        }
        event.preventDefault();
        event.returnValue = message;
        return message;
    };

    window.addEventListener('beforeunload', beforeUnload);

    document.addEventListener('input', (event) => {
        if (event.target && event.target.closest && event.target.closest('#post, .block-editor')) {
            classicDirty = true;
        }
    }, true);

    document.addEventListener('change', (event) => {
        if (event.target && event.target.closest && event.target.closest('#post, .block-editor')) {
            classicDirty = true;
        }
    }, true);

    document.addEventListener('submit', (event) => {
        if (event.target && event.target.matches && event.target.matches('#post')) {
            submitting = true;
        }
    }, true);

    document.addEventListener('click', (event) => {
        const target = event.target && event.target.closest ? event.target.closest('button, input') : null;
        if (!target) {
            return;
        }

        if (target.matches('#publish, #save-post, .editor-post-publish-button, .editor-post-save-draft')) {
            submitting = true;
            window.setTimeout(() => {
                submitting = false;
                classicDirty = false;
            }, 2500);
        }
    }, true);

    document.addEventListener('keydown', (event) => {
        const reloadShortcut = event.key === 'F5' || ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'r');
        if (!reloadShortcut || !isDirty()) {
            return;
        }
        event.preventDefault();
        window.alert(message);
    }, true);
})();
