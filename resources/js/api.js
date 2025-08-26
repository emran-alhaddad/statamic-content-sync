// resources/js/api.js
const BASE = (typeof window !== 'undefined' && window.__CONTENT_SYNC_BASE__) || '/cp/content-sync';
const CSRF = (typeof window !== 'undefined' && window.__CONTENT_SYNC_CSRF__) || (window.Statamic && Statamic.csrfToken) || '';

export async function fetchOptions(type) {
  const res = await fetch(`${BASE}/options?type=${encodeURIComponent(type)}`, { credentials: 'same-origin' });
  if (!res.ok) throw new Error('Options request failed');
  return (await res.json()).options || [];
}

export async function fetchSites() {
  const res = await fetch(`${BASE}/options?type=sites`, { credentials: 'same-origin' });
  if (!res.ok) throw new Error('Sites request failed');
  return (await res.json()).options || [];
}

export async function exportPayload(payload) {
  const fd = new FormData();
  Object.entries(payload).forEach(([k, v]) => fd.append(k, Array.isArray(v) ? JSON.stringify(v) : (v ?? '')));

  const res = await fetch(`${BASE}/export`, {
    method: 'POST',
    headers: { 'X-CSRF-TOKEN': CSRF },
    credentials: 'same-origin',
    body: fd
  });

  if (!res.ok) throw new Error(`Export failed (${res.status})`);
  const blob = await res.blob();

  // filename from Content-Disposition
  let filename = 'export.json';
  const disp = res.headers.get('Content-Disposition') || '';
  const m = /filename="?([^"]+)"?/i.exec(disp);
  if (m) filename = m[1];

  const url = URL.createObjectURL(blob);
  const a = document.createElement('a'); a.href = url; a.download = filename;
  document.body.appendChild(a); a.click(); a.remove(); URL.revokeObjectURL(url);

  try { const txt = await blob.text(); const json = JSON.parse(txt); return { count: (json.items || []).length, path: filename }; }
  catch { return { count: 0, path: filename }; }
}

export async function previewImport(file) {
  const fd = new FormData(); fd.append('file', file);
  const res = await fetch(`${BASE}/import/preview`, {
    method: 'POST',
    headers: { 'X-CSRF-TOKEN': CSRF },
    credentials: 'same-origin',
    body: fd
  });
  if (!res.ok) throw new Error(`Preview failed (${res.status})`);
  return res.json();
}

export async function commitImport(payload) {
  const res = await fetch(`${BASE}/import/commit`, {
    method: 'POST',
    headers: { 'X-CSRF-TOKEN': CSRF, 'Content-Type': 'application/json' },
    credentials: 'same-origin',
    body: JSON.stringify(payload)
  });
  if (!res.ok) throw new Error(`Commit failed (${res.status})`);
  return res.json();
}
