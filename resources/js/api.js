const base = Statamic.$config.get('cpUrl') + '/content-sync';

export async function fetchOptions(type) {
    const res = await fetch(`${base}/options?type=${encodeURIComponent(type)}`, { credentials: 'same-origin' });
    const json = await res.json();
    return json.options || [];
}

export async function fetchSites() {
    const res = await fetch(`${base}/options?type=sites`, { credentials: 'same-origin' });
    const json = await res.json();
    return json.options || [];
}

/**
 * Export and force a local download (no server-side write).
 * We post payload and receive a Blob with Content-Disposition filename.
 */
export async function exportPayload(payload) {
    const res = await fetch(`${base}/export`, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': Statamic.csrfToken },
        credentials: 'same-origin',
        body: (() => {
            const d = new FormData();
            Object.entries(payload).forEach(([k, v]) => {
                d.append(k, Array.isArray(v) ? JSON.stringify(v) : v ?? '');
            });
            return d;
        })(),
    });

    if (!res.ok) throw new Error('Export failed');

    const blob = await res.blob();

    // Try to infer filename from header; fallback.
    let filename = 'export.json';
    const disp = res.headers.get('Content-Disposition') || '';
    const m = /filename="?([^"]+)"?/i.exec(disp);
    if (m) filename = m[1];

    // Save file
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url; a.download = filename;
    document.body.appendChild(a); a.click();
    a.remove(); window.URL.revokeObjectURL(url);

    // For UI stats, parse the blob (safe, it’s small json)
    try {
        const txt = await blob.text();
        const json = JSON.parse(txt);
        return { count: (json.items || []).length, path: filename, meta: json.__meta || null };
    } catch {
        return { count: 0, path: filename };
    }
}

/** Upload file for preview diff (auto-preview workflow). */
export async function previewImport(file) {
    const d = new FormData();
    d.append('file', file);
    const res = await fetch(`${base}/import/preview`, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': Statamic.csrfToken },
        credentials: 'same-origin',
        body: d,
    });
    if (!res.ok) throw new Error('Preview failed');
    return res.json(); // { type, diffs: [{ key, status, diff, current, incoming }...] }
}

/** Commit decisions: { type, decisions: [{ key, action: 'current'|'incoming'|'both' }] } */
export async function commitImport(payload) {
    const res = await fetch(`${base}/import/commit`, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': Statamic.csrfToken, 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify(payload),
    });
    if (!res.ok) throw new Error('Commit failed');
    return res.json(); // { results: { updated, created, skipped } }
}
