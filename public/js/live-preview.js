(() => {
    const source = document.querySelector('[data-live-preview-source]');
    const mount = document.querySelector('[data-live-preview-mount]');

    if (!source || !mount) {
        return;
    }

    let objectUrl = null;
    let timer = null;

    const render = () => {
        const iframe = document.createElement('iframe');
        iframe.className = 'preview';
        iframe.title = 'Sandboxed company preview';
        iframe.setAttribute('sandbox', 'allow-scripts');

        if (objectUrl) {
            URL.revokeObjectURL(objectUrl);
        }

        objectUrl = URL.createObjectURL(new Blob([source.value], {
            type: 'text/html;charset=utf-8',
        }));
        iframe.src = objectUrl;
        mount.replaceChildren(iframe);
    };

    const schedule = () => {
        window.clearTimeout(timer);
        timer = window.setTimeout(render, 80);
    };

    source.addEventListener('input', schedule);
    source.addEventListener('change', schedule);
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
