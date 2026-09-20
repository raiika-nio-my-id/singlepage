(() => {
    const form = document.querySelector('[data-editor-form]');
    const source = document.querySelector('[data-live-preview-source]');

    if (!form || !(source instanceof HTMLTextAreaElement)) {
        return;
    }

    const subdomainField = document.querySelector('#subdomain');
    const flags = document.querySelectorAll('[data-unsaved-flag]');
    const cookieMaxAge = 60 * 60 * 24 * 30;

    let writeTimer = null;
    let dirty = false;
    let activeLabel = draftLabel();

    function draftLabel() {
        if (subdomainField instanceof HTMLInputElement) {
            const value = subdomainField.value.trim().toLowerCase();

            return value === '' ? '_new' : value;
        }

        if (subdomainField) {
            const host = subdomainField.textContent.trim().toLowerCase();

            return host.split('.')[0] || '_new';
        }

        return '_new';
    }

    function storageKey(label = activeLabel) {
        return `cms.draft.html.${label}`;
    }

    function cookieName(label = activeLabel) {
        const safe = String(label).replace(/[^a-z0-9_-]/g, '') || 'new';

        return `cms_unsaved_${safe}`;
    }

    function writeCookie(name, value, maxAge) {
        document.cookie = `${name}=${encodeURIComponent(value)}; path=/; max-age=${maxAge}; samesite=strict`;
    }

    function clearCookie(label) {
        writeCookie(cookieName(label), '', 0);
    }

    function readDraft(label) {
        try {
            return window.localStorage.getItem(storageKey(label));
        } catch {
            return null;
        }
    }

    function writeDraft(label, html) {
        try {
            window.localStorage.setItem(storageKey(label), html);
        } catch {
            // Quota / private-mode failures still keep the on-screen unsaved flag.
        }

        writeCookie(cookieName(label), '1', cookieMaxAge);
    }

    function removeDraft(label) {
        try {
            window.localStorage.removeItem(storageKey(label));
        } catch {
            // Ignore storage failures.
        }

        clearCookie(label);
    }

    function setDirty(next) {
        dirty = next;
        flags.forEach((flag) => {
            flag.hidden = !next;
        });
    }

    function persist() {
        const label = draftLabel();

        if (label !== activeLabel) {
            removeDraft(activeLabel);
            activeLabel = label;
        }

        const html = source.value;
        const isDirty = html !== source.defaultValue;

        setDirty(isDirty);

        if (isDirty) {
            writeDraft(activeLabel, html);
        } else {
            removeDraft(activeLabel);
        }
    }

    function schedulePersist() {
        window.clearTimeout(writeTimer);
        writeTimer = window.setTimeout(persist, 160);
    }

    const draft = readDraft(activeLabel);

    if (typeof draft === 'string' && draft !== source.value) {
        source.value = draft;
    }

    persist();

    source.addEventListener('input', schedulePersist);
    source.addEventListener('change', persist);

    if (subdomainField instanceof HTMLInputElement) {
        subdomainField.addEventListener('input', schedulePersist);
        subdomainField.addEventListener('change', persist);
    }

    form.addEventListener('submit', () => {
        window.clearTimeout(writeTimer);
        setDirty(false);
        removeDraft(activeLabel);
    });

    document.addEventListener('keydown', (event) => {
        if (!event.ctrlKey && !event.metaKey) {
            return;
        }

        if (event.altKey || event.shiftKey || event.key.toLowerCase() !== 's') {
            return;
        }

        event.preventDefault();
        form.requestSubmit();
    });

    window.addEventListener('beforeunload', (event) => {
        if (! dirty) {
            return;
        }

        event.preventDefault();
        event.returnValue = '';
    });
})();
