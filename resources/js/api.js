export async function fetchOptions(type) {
    const res = await fetch(`/cp/content-sync/options?type=${encodeURIComponent(type)}`, {
        headers: { 'X-CSRF-TOKEN': Statamic.$config.get('csrfToken') }
    });
    return res.json();
}

export async function exportPayload(payload) {
    const res = await fetch('/cp/content-sync/export', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': Statamic.$config.get('csrfToken')
        },
        body: JSON.stringify(payload)
    });
    return res.json();
}

export async function previewImport(file) {
    const fd = new FormData();
    fd.append('file', file);
    const res = await fetch('/cp/content-sync/import/preview', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': Statamic.$config.get('csrfToken') },
        body: fd
    });
    return res.json();
}

export async function commitImport(payload) {
    const res = await fetch('/cp/content-sync/import/commit', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': Statamic.$config.get('csrfToken')
        },
        body: JSON.stringify(payload)
    });
    return res.json();
}
