(() => {
    const source = document.querySelector('[data-live-preview-source]');
    const mount = document.querySelector('[data-live-preview-mount]');
    const stage = document.querySelector('[data-preview-stage]');
    const buttons = document.querySelectorAll('[data-preview-mode-button]');

    if (!source || !mount || !stage) {
        return;
    }

    const storageKey = 'cms.previewMode';
    const desktopWidth = Number.parseInt(stage.dataset.desktopWidth || '1280', 10);
    const desktopHeight = Number.parseInt(stage.dataset.desktopHeight || '800', 10);
    const mobileWidth = Number.parseInt(stage.dataset.mobileWidth || '390', 10);
    const mobileHeight = Number.parseInt(stage.dataset.mobileHeight || '844', 10);

    let objectUrl = null;
    let timer = null;

    const currentMode = () => (stage.dataset.previewMode === 'mobile' ? 'mobile' : 'desktop');

    const readPx = (styles, name) => Number.parseFloat(styles.getPropertyValue(name)) || 0;

    const insertBeforeTag = (html, tag, extra) => {
        const pattern = new RegExp(`</${tag}\\s*>`, 'i');
        const match = html.match(pattern);

        if (!match || match.index === undefined) {
            return null;
        }

        return html.slice(0, match.index) + extra + html.slice(match.index);
    };

    const decoratePreviewHtml = (html, mode) => {
        if (mode !== 'mobile') {
            return html;
        }

        const style = `<style data-cms-preview-ui>
html { cursor: grab; scroll-behavior: auto !important; }
html, body { -ms-overflow-style: none; scrollbar-width: none; overscroll-behavior: contain; }
html::-webkit-scrollbar, body::-webkit-scrollbar, *::-webkit-scrollbar { width: 0 !important; height: 0 !important; display: none !important; }
* { scrollbar-width: none; scroll-behavior: auto !important; }
img, svg, video, canvas, iframe, a { -webkit-user-drag: none; user-select: none; }
html.cms-preview-dragging, html.cms-preview-dragging * { cursor: grabbing !important; user-select: none !important; -webkit-user-drag: none !important; }
</style>`;
        const script = `<script data-cms-preview-ui>
(function () {
    var active = false;
    var dragging = false;
    var startX = 0;
    var startY = 0;
    var originX = 0;
    var originY = 0;
    var scroller = null;

    function scrollingElement() {
        return document.scrollingElement || document.documentElement;
    }

    function scrollRoot(node) {
        var el = node && node.nodeType === 1 ? node : (node && node.parentElement);

        while (el && el !== document.body && el !== document.documentElement) {
            var style = window.getComputedStyle(el);
            var canY = (style.overflowY === 'auto' || style.overflowY === 'scroll') && el.scrollHeight > el.clientHeight + 1;
            var canX = (style.overflowX === 'auto' || style.overflowX === 'scroll') && el.scrollWidth > el.clientWidth + 1;

            if (canX || canY) {
                return el;
            }

            el = el.parentElement;
        }

        return scrollingElement();
    }

    function isEditable(node) {
        return Boolean(node && node.closest && node.closest('input, textarea, select, option, [contenteditable="true"]'));
    }

    function isWindowScroller(el) {
        return !el || el === document.documentElement || el === document.body || el === scrollingElement();
    }

    function scrollOf(el) {
        if (isWindowScroller(el)) {
            return { x: window.scrollX, y: window.scrollY };
        }

        return { x: el.scrollLeft, y: el.scrollTop };
    }

    function applyScroll(el, x, y) {
        if (isWindowScroller(el)) {
            window.scrollTo({ left: x, top: y, behavior: 'instant' });
            return;
        }

        if (el.scrollTo) {
            el.scrollTo({ left: x, top: y, behavior: 'instant' });
            return;
        }

        el.scrollLeft = x;
        el.scrollTop = y;
    }

    document.addEventListener('dragstart', function (event) {
        event.preventDefault();
    }, true);

    document.addEventListener('pointerdown', function (event) {
        if (event.button !== 0 || event.pointerType === 'touch' || isEditable(event.target)) {
            return;
        }

        if (event.target && event.target.closest && event.target.closest('img, svg, video, canvas, picture')) {
            event.preventDefault();
        }

        active = true;
        dragging = false;
        scroller = scrollRoot(event.target);
        startX = event.clientX;
        startY = event.clientY;
        var origin = scrollOf(scroller);
        originX = origin.x;
        originY = origin.y;

        if (event.target && event.target.setPointerCapture) {
            try {
                event.target.setPointerCapture(event.pointerId);
            } catch (error) {
                // Capture is best-effort inside the sandboxed preview.
            }
        }
    });

    document.addEventListener('pointermove', function (event) {
        if (!active) {
            return;
        }

        var dx = event.clientX - startX;
        var dy = event.clientY - startY;

        if (!dragging && (Math.abs(dx) > 3 || Math.abs(dy) > 3)) {
            dragging = true;
            document.documentElement.classList.add('cms-preview-dragging');
        }

        if (!dragging) {
            return;
        }

        applyScroll(scroller, originX - dx, originY - dy);
        event.preventDefault();
    }, { passive: false });

    function finish() {
        if (!active) {
            return;
        }

        active = false;
        document.documentElement.classList.remove('cms-preview-dragging');

        if (!dragging) {
            return;
        }

        dragging = false;
        document.addEventListener('click', function (clickEvent) {
            clickEvent.preventDefault();
            clickEvent.stopPropagation();
        }, { capture: true, once: true });
    }

    document.addEventListener('pointerup', finish);
    document.addEventListener('pointercancel', finish);
})();
</script>`;

        let decorated = html;

        if (/<\/head>/i.test(decorated)) {
            decorated = insertBeforeTag(decorated, 'head', style) || decorated;
        } else if (/<\/html>/i.test(decorated)) {
            decorated = insertBeforeTag(decorated, 'html', style) || (style + decorated);
        } else {
            decorated = style + decorated;
        }

        if (/<\/body>/i.test(decorated)) {
            return insertBeforeTag(decorated, 'body', script) || (decorated + script);
        }

        if (/<\/html>/i.test(decorated)) {
            return insertBeforeTag(decorated, 'html', script) || (decorated + script);
        }

        return decorated + script;
    };

    const fit = () => {
        const iframe = mount.querySelector('iframe.preview');

        if (!iframe) {
            return;
        }

        const mode = currentMode();
        const viewportWidth = mode === 'mobile' ? mobileWidth : desktopWidth;
        const viewportHeight = mode === 'mobile' ? mobileHeight : desktopHeight;
        const styles = window.getComputedStyle(stage);
        const paddingX = Number.parseFloat(styles.paddingLeft) + Number.parseFloat(styles.paddingRight);
        const paddingY = Number.parseFloat(styles.paddingTop) + Number.parseFloat(styles.paddingBottom);
        const chromeX = readPx(styles, '--preview-bezel-x') * 2;
        const chromeY = readPx(styles, '--preview-bezel-top')
            + readPx(styles, '--preview-bezel-bottom')
            + readPx(styles, '--preview-stand');
        const availableWidth = Math.max(1, stage.clientWidth - paddingX - chromeX);
        const availableHeight = Math.max(1, stage.clientHeight - paddingY - chromeY);
        const scale = Math.min(1, availableWidth / viewportWidth, availableHeight / viewportHeight);

        iframe.style.width = `${viewportWidth}px`;
        iframe.style.height = `${viewportHeight}px`;
        iframe.style.transform = `scale(${scale})`;
        iframe.style.transformOrigin = 'top left';
        mount.style.width = `${Math.round(viewportWidth * scale)}px`;
        mount.style.height = `${Math.round(viewportHeight * scale)}px`;
    };

    const render = () => {
        const iframe = document.createElement('iframe');
        iframe.className = 'preview';
        iframe.title = 'Sandboxed company preview';
        iframe.setAttribute('sandbox', 'allow-scripts');

        if (objectUrl) {
            URL.revokeObjectURL(objectUrl);
        }

        objectUrl = URL.createObjectURL(new Blob([decoratePreviewHtml(source.value, currentMode())], {
            type: 'text/html;charset=utf-8',
        }));
        iframe.src = objectUrl;
        mount.replaceChildren(iframe);
        fit();
    };

    const apply = (mode, { refresh = true } = {}) => {
        const next = mode === 'mobile' ? 'mobile' : 'desktop';
        const changed = currentMode() !== next;
        stage.dataset.previewMode = next;
        buttons.forEach((button) => {
            button.setAttribute('aria-pressed', String(button.dataset.previewModeButton === next));
        });

        try {
            window.localStorage.setItem(storageKey, next);
        } catch {
            // Ignore quota / private-mode failures.
        }

        if (refresh && changed) {
            render();

            return;
        }

        window.requestAnimationFrame(fit);
    };

    const schedule = () => {
        window.clearTimeout(timer);
        timer = window.setTimeout(render, 80);
    };

    source.addEventListener('input', schedule);
    source.addEventListener('change', schedule);
    buttons.forEach((button) => {
        button.addEventListener('click', () => apply(button.dataset.previewModeButton));
    });
    window.addEventListener('resize', fit);

    if (typeof ResizeObserver === 'function') {
        new ResizeObserver(fit).observe(stage);
    }

    let stored = 'desktop';

    try {
        stored = window.localStorage.getItem(storageKey) || 'desktop';
    } catch {
        stored = 'desktop';
    }

    apply(stored, { refresh: false });
    render();
})();

(() => {
    const subdomainField = document.querySelector('#subdomain');
    const siteLink = document.querySelector('[data-site-link]');
    const template = siteLink?.dataset.siteUrlTemplate;

    if (!siteLink || !template || !(subdomainField instanceof HTMLInputElement)) {
        return;
    }

    const sync = () => {
        const subdomain = subdomainField.value.trim();

        if (subdomain === '') {
            siteLink.setAttribute('aria-disabled', 'true');
            siteLink.removeAttribute('href');

            return;
        }

        siteLink.removeAttribute('aria-disabled');
        siteLink.href = template.replace('{subdomain}', subdomain);
    };

    subdomainField.addEventListener('input', sync);
    subdomainField.addEventListener('change', sync);
    sync();
})();
