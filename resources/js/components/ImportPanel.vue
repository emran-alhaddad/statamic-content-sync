<template>
    <div class="content-sync-import card p-6 space-y-5">
        <div class="flex items-start justify-between flex-wrap gap-4">
            <div>
                <div class="font-bold text-lg">Import</div>
                <div class="text-gray-600">
                    Upload an exported JSON. We verify integrity, group by <b>handle → site</b>, and show only changed
                    items.
                </div>
            </div>
            <div class="flex items-center">
                <input type="file" @change="onFile" accept="application/json,.json" />
            </div>
        </div>

        <div v-if="loading" class="py-8 text-center">
            <loading-graphic />
            <div class="mt-2 text-gray-600">Analyzing…</div>
        </div>

        <div v-if="error" class="text-red-700 bg-red-50 border border-red-200 rounded p-3">
            {{ error }}
        </div>

        <div v-if="filteredGroups">
            <!-- Strategy -->
            <div class="flex flex-wrap items-end gap-4 mb-4">
                <div>
                    <label class="block font-medium mb-1">Review strategy</label>
                    <v-select :options="strategyOptions" :reduce="o => o.value" v-model="strategy" :clearable="false"
                        style="width: 200px" />
                </div>

                <div v-if="strategy === 'auto'">
                    <label class="block font-medium mb-1">Accept the change</label>
                    <v-select :options="acceptOptions" :reduce="o => o.value" v-model="autoAction" :clearable="false"
                        style="width: 260px" />
                </div>

                <div class="ml-auto">
                    <button class="btn-primary" :disabled="committing || totalChanged === 0" @click="commit">
                        {{ committing ? 'Committing…' : (strategy === 'auto' ? 'Apply Auto Action' : 'Complete Import')
                        }}
                    </button>
                </div>
            </div>

            <!-- Accordions: handle -> site -> items -->
            <div v-for="(sites, handle) in filteredGroups" :key="handle" class="mb-3 border rounded">
                <button class="w-full flex items-center justify-between px-3 py-2 bg-gray-100 hover:bg-gray-200"
                    @click="toggle(handle)">
                    <div class="font-semibold">
                        {{ handle }} <span class="text-gray-500">({{ totalInSites(sites) }})</span>
                    </div>
                    <ChevronIcon :open="isOpen(handle)" />
                </button>

                <div v-show="isOpen(handle)" class="p-3 pt-2">
                    <div v-for="(items, site) in sites" :key="handle + '::' + site" class="mb-2 border rounded">
                        <button class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100"
                            @click="toggle(handle + '::' + site)">
                            <div>
                                <span class="font-medium">Site:</span> {{ site }}
                                <span class="text-gray-500">({{ items.length }})</span>
                            </div>
                            <ChevronIcon :open="isOpen(handle + '::' + site)" />
                        </button>

                        <div v-show="isOpen(handle + '::' + site)" class="p-3 pt-2 space-y-3">
                            <div v-for="it in items" :key="it.key" class="border rounded">
                                <!-- Item header -->
                                <div class="flex items-center justify-between px-3 py-2">
                                    <div class="font-semibold">
                                        {{ it.key }}
                                        <span class="badge" :class="badge(it.status)">{{ it.status }}</span>
                                    </div>

                                    <div class="flex items-center gap-2">
                                        <div v-if="strategy === 'manual'" class="hidden md:block">
                                            <v-select :options="acceptOptions" :reduce="o => o.value" :clearable="false"
                                                v-model="decisions[it.key]" style="width: 230px" />
                                        </div>
                                        <button class="icon-btn" @click="toggle('item::' + it.key)"
                                            :aria-expanded="isOpen('item::' + it.key)">
                                            <ChevronIcon :open="isOpen('item::' + it.key)" />
                                        </button>
                                    </div>
                                </div>

                                <!-- Item body -->
                                <div v-show="isOpen('item::' + it.key)" class="px-3 pb-3 space-y-4">
                                    <template v-if="strategy === 'manual'">
                                        <!-- SIDE BY SIDE WITH CONTEXT HUNKS -->
                                        <div class="flex flex-nowrap gap-3">
                                            <div class="basis-1/2 min-w-0">
                                                <div class="text-gray-700 font-semibold mb-1">Current</div>
                                                <pre class="code-block"
                                                    v-html="renderPanelWithContext(it, 'current')"></pre>
                                            </div>
                                            <div class="basis-1/2 min-w-0">
                                                <div class="text-gray-700 font-semibold mb-1">Incoming</div>
                                                <pre class="code-block"
                                                    v-html="renderPanelWithContext(it, 'incoming')"></pre>
                                            </div>
                                        </div>

                                        <!-- FINAL MERGE BELOW WITH GREEN HIGHLIGHTS -->
                                        <div class="mt-3">
                                            <div class="text-gray-700 font-semibold mb-1">Final merge</div>
                                            <pre class="code-block"
                                                v-html="renderFinalWithContext(it, decisions[it.key] || autoDefault(it))"></pre>
                                        </div>
                                    </template>

                                    <template v-else>
                                        <div class="text-gray-600 text-sm">Auto strategy will apply:
                                            <b>{{ labelFor(autoAction) }}</b>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-if="totalInSites(sites) === 0" class="text-gray-500 text-sm px-1">
                        No changes under this handle.
                    </div>
                </div>
            </div>

            <div v-if="strategy === 'auto'" class="text-gray-600 text-sm">
                Will apply <b>{{ labelFor(autoAction) }}</b> to {{ totalChanged }} changed item<span
                    v-if="totalChanged !== 1">s</span>.
            </div>
        </div>
    </div>
</template>

<script>
import { previewImport, commitImport } from '../api'
import { diffLines } from 'diff'

const ChevronIcon = {
    name: 'ChevronIcon',
    functional: true,
    props: { open: { type: Boolean, default: false } },
    render(h, { props }) {
        return h('svg', {
            attrs: { viewBox: '0 0 20 20', fill: 'currentColor', width: 18, height: 18, 'aria-hidden': 'true' },
            class: props.open ? 'transform rotate-180 text-gray-600' : 'text-gray-600',
        }, [h('path', { attrs: { d: 'M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 111.06 1.06l-4.24 4.24a.75.75 0 01-1.06 0L5.21 8.29a.75.75 0 01.02-1.08z' } })]);
    }
};

export default {
    name: 'ImportPanel',
    components: { ChevronIcon },
    data() {
        return {
            loading: false,
            error: '',
            groups: null,
            type: '',
            open: {},
            strategy: 'manual',
            autoAction: 'incoming',
            decisions: {},
            committing: false,
            totalChanged: 0,
            strategyOptions: [
                { label: 'Manual (review per item)', value: 'manual' },
                { label: 'Auto (apply one action to all)', value: 'auto' },
            ],
            acceptOptions: [
                { label: 'Accept Incoming changes', value: 'incoming' },
                { label: 'Accept Current changes', value: 'current' },
                { label: 'Accept Both changes', value: 'both' },
            ],
            contextLines: 6,
        };
    },
    computed: {
        filteredGroups() {
            if (!this.groups) return null;
            const out = {};
            let count = 0;

            Object.entries(this.groups).forEach(([handle, sites]) => {
                const siteMap = {};
                Object.entries(sites).forEach(([site, items]) => {
                    const filtered = (items || []).filter(it => !this.isMeaningfullyUnchanged(it, this.type));
                    if (filtered.length) {
                        siteMap[site] = filtered;
                        count += filtered.length;
                    }
                });
                if (Object.keys(siteMap).length) out[handle] = siteMap;
            });

            this.totalChanged = count;
            return out;
        },
    },
    methods: {
        isOpen(k) { return !!this.open[k]; },
        toggle(k) { this.$set(this.open, k, !this.open[k]); },
        totalInSites(sites) { return Object.values(sites).reduce((n, arr) => n + (Array.isArray(arr) ? arr.length : 0), 0); },
        badge(s) { return s === 'create' ? 'badge-green' : s === 'update' ? 'badge-amber' : 'badge-gray'; },
        pretty(o) { try { return JSON.stringify(o, null, 2); } catch { return String(o); } },
        escape(v) { return String(v).replace(/[&<>]/g, s => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;' }[s])); },
        labelFor(v) { const o = this.acceptOptions.find(o => o.value === v); return o ? o.label : v; },

        /* ---------- stable deep equal ---------- */
        stableStringify(o) {
            const seen = new WeakSet();
            const walk = v => {
                if (v && typeof v === 'object') {
                    if (seen.has(v)) return;
                    seen.add(v);
                    if (Array.isArray(v)) return v.map(walk);
                    const out = {}; Object.keys(v).sort().forEach(k => out[k] = walk(v[k])); return out;
                } return v;
            };
            return JSON.stringify(walk(o));
        },
        deepEqual(a, b) { try { return this.stableStringify(a) === this.stableStringify(b); } catch { return false; } },

        /* ---------- subset used for diff per type ---------- */
        relevantPair(item, type) {
            const c = item?.current ?? {}, i = item?.incoming ?? {};
            switch (type) {
                case 'collections': return [
                    { data: c.data ?? {}, published: 'published' in c ? !!c.published : undefined },
                    { data: i.data ?? {}, published: 'published' in i ? !!i.published : undefined }
                ];
                case 'taxonomies':
                case 'globals':
                case 'assets': return [{ data: c.data ?? {} }, { data: i.data ?? {} }];
                case 'navigation': return [{ tree: c.tree ?? [] }, { tree: i.tree ?? [] }];
                default: return [c, i];
            }
        },
        isMeaningfullyUnchanged(item, type) {
            const [rc, ri] = this.relevantPair(item, type);
            return this.deepEqual(rc, ri);
        },

        /* ---------- recency (which side gets '+') ---------- */
        toEpoch(v) {
            if (v == null) return 0;
            if (typeof v === 'number') return (v > 1e12) ? v : v * 1000;
            if (typeof v === 'string') {
                const n = Number(v); if (!Number.isNaN(n)) return (n > 1e12) ? n : n * 1000;
                const t = Date.parse(v); return Number.isNaN(t) ? 0 : t;
            }
            return 0;
        },
        itemEpoch(obj) {
            return this.toEpoch(obj?.updated_at ?? obj?.data?.updated_at ?? 0);
        },
        fresherSide(item) {
            const cur = this.itemEpoch(item.current), inc = this.itemEpoch(item.incoming);
            if (cur > inc) return 'current';
            if (inc > cur) return 'incoming';
            return 'incoming';
        },

        /* ---------- line diff + hunks (no false positives) ---------- */
        buildPanelHunks(item, side) {
            const [rc, ri] = this.relevantPair(item, this.type);
            const aStr = this.pretty(rc);
            const bStr = this.pretty(ri);
            const parts = diffLines(aStr, bStr); // diff current (a) -> incoming (b)
            const fresher = this.fresherSide(item);

            // Build lines for ONE panel; ignore lines that don't exist in that panel.
            const lines = [];
            for (const p of parts) {
                const chunk = (p.value || '').split('\n');
                if (chunk[chunk.length - 1] === '') chunk.pop();

                if (p.added) {
                    if (side === 'incoming') {
                        // exists only in incoming
                        const tag = (fresher === 'incoming') ? '+' : '-';
                        chunk.forEach(line => lines.push({ tag, line }));
                    }
                    // ignore in "current"
                    continue;
                }
                if (p.removed) {
                    if (side === 'current') {
                        // exists only in current
                        const tag = (fresher === 'current') ? '+' : '-';
                        chunk.forEach(line => lines.push({ tag, line }));
                    }
                    // ignore in "incoming"
                    continue;
                }
                // common
                chunk.forEach(line => lines.push({ tag: ' ', line }));
            }

            // Create hunks: group changed lines with N lines of context
            const context = this.contextLines;
            const hunks = [];
            let i = 0;
            while (i < lines.length) {
                if (lines[i].tag !== ' ') {
                    const start = Math.max(0, i - context);
                    let j = i;
                    while (j < lines.length && lines[j].tag !== ' ') j++;
                    const end = Math.min(lines.length, j + context);
                    hunks.push(lines.slice(start, end));
                    i = end;
                } else {
                    i++;
                }
            }
            if (!hunks.length) {
                // no change — show tiny message
                return [[{ tag: ' ', line: '(no changes)' }]];
            }
            return hunks;
        },

        renderPanelWithContext(item, side) {
            const hunks = this.buildPanelHunks(item, side);
            const out = [];
            hunks.forEach((h, idx) => {
                if (idx > 0) out.push('<div class="hunk-gap">…</div>');
                h.forEach(l => {
                    if (l.tag === '+') out.push(`<span class="line-add">+ ${this.escape(l.line)}</span>`);
                    else if (l.tag === '-') out.push(`<span class="line-del">- ${this.escape(l.line)}</span>`);
                    else out.push(this.escape(l.line));
                });
            });
            return out.join('\n');
        },

        /* ---------- final merge (green lines only where different from chosen base) ---------- */
        mergeFinal(current, incoming, decision) {
            if (decision === 'incoming') return incoming;
            if (decision === 'current') return current;
            const merge = (a, b) => {
                if (Array.isArray(a) && Array.isArray(b)) return b;
                if (a && typeof a === 'object' && b && typeof b === 'object') {
                    const out = { ...a };
                    Object.keys(b).forEach(k => out[k] = merge(a?.[k], b[k]));
                    return out;
                }
                return b !== undefined ? b : a;
            };
            return merge(current, incoming);
        },

        renderFinalWithContext(item, decision) {
            const [rc, ri] = this.relevantPair(item, this.type);
            const finalObj = this.mergeFinal(rc, ri, decision);
            const baseStr = this.pretty(decision === 'incoming' ? ri : rc);
            const finalStr = this.pretty(finalObj);

            // Which line numbers in final are "added" vs the chosen base?
            const parts = diffLines(baseStr, finalStr);
            const addedLineIdxs = new Set();
            let cursor = 0;
            for (const p of parts) {
                const lines = (p.value || '').split('\n');
                if (lines[lines.length - 1] === '') lines.pop();
                if (p.added) {
                    for (let k = 0; k < lines.length; k++) addedLineIdxs.add(cursor + k);
                }
                if (!p.removed) cursor += lines.length; // only advance on final side
            }

            const finalLines = finalStr.split('\n');
            // Build hunks around added lines
            const context = this.contextLines;
            const windows = [];
            const idxs = [...addedLineIdxs].sort((a, b) => a - b);
            for (const idx of idxs) {
                windows.push([Math.max(0, idx - context), Math.min(finalLines.length - 1, idx + context)]);
            }
            // merge windows
            const merged = [];
            if (windows.length) {
                merged.push(windows[0].slice());
                for (let i = 1; i < windows.length; i++) {
                    const [s, e] = windows[i], last = merged[merged.length - 1];
                    if (s <= last[1] + 1) last[1] = Math.max(last[1], e); else merged.push([s, e]);
                }
            }

            const out = [];
            if (!merged.length) {
                // No visible change lines in final (edge case).
                finalLines.forEach((t, i) => out.push(this.escape(t)));
                return out.join('\n');
            }

            merged.forEach(([s, e], wi) => {
                if (wi > 0) out.push('<div class="hunk-gap">…</div>');
                for (let i = s; i <= e; i++) {
                    const t = finalLines[i];
                    if (addedLineIdxs.has(i)) out.push(`<span class="line-final">+ ${this.escape(t)}</span>`);
                    else out.push(this.escape(t));
                }
            });
            return out.join('\n');
        },

        autoDefault(item) {
            return this.fresherSide(item);
        },

        async onFile(e) {
            const f = e.target.files && e.target.files[0]; if (!f) return;
            this.loading = true; this.error = ''; this.groups = null; this.decisions = {}; this.open = {};

            try {
                const res = await previewImport(f);
                this.type = res.type; this.groups = res.groups || {};
                Object.keys(this.groups).forEach(h => this.$set(this.open, h, true));
                this.$nextTick(() => {
                    Object.values(this.filteredGroups || {}).forEach(sites => {
                        Object.values(sites).forEach(items => {
                            items.forEach(it => this.$set(this.decisions, it.key, this.autoDefault(it)));
                        });
                    });
                });
            } catch (err) {
                this.error = err?.message || 'Failed to analyze file.';
            } finally {
                this.loading = false;
            }
        },

        async commit() {
            if (this.totalChanged === 0) { Statamic.$toast.info('Nothing to apply.'); return; }
            this.committing = true;
            try {
                let payload;
                if (this.strategy === 'auto') {
                    payload = { type: this.type, strategy: 'auto', auto_action: this.autoAction };
                } else {
                    const decisions = [];
                    Object.values(this.filteredGroups).forEach(sites => {
                        Object.values(sites).forEach(items => {
                            items.forEach(it => decisions.push({ key: it.key, action: this.decisions[it.key] || this.autoDefault(it) }));
                        });
                    });
                    payload = { type: this.type, strategy: 'manual', decisions };
                }
                const res = await commitImport(payload);
                Statamic.$toast.success(`Updated ${res.results.updated}, Created ${res.results.created}, Skipped ${res.results.skipped}`);
            } catch (e) { Statamic.$toast.error(e?.message || 'Commit failed.'); }
            finally { this.committing = false; }
        },
    },
};
</script>

<!-- Not scoped: we namespace under .content-sync-import so v-html spans are styled -->
<style>
.content-sync-import .code-block {
    background: #0b1220;
    color: #e5edff;
    border-radius: 12px;
    padding: .75rem;
    max-height: 330px;
    overflow: auto;
    font-size: 12px;
    line-height: 1.45;
}

.content-sync-import .line-add,
.content-sync-import .line-del,
.content-sync-import .line-chg,
.content-sync-import .line-final {
    display: block;
    padding: 2px 6px;
    border-radius: 6px;
    margin: 1px 0;
    white-space: pre-wrap;
}

.content-sync-import .line-add {
    background: rgba(16, 185, 129, .12);
    color: #10b981;
}

/* + green */
.content-sync-import .line-del {
    background: rgba(239, 68, 68, .20);
    color: #fff;
}

/* - red  */
.content-sync-import .line-chg {
    background: rgba(245, 158, 11, .15);
    color: #f59e0b;
}

.content-sync-import .line-final {
    background: rgba(16, 185, 129, .18);
    color: #c7ffdf;
}

.content-sync-import .hunk-gap {
    color: #9aa4b2;
    text-align: center;
    padding: 4px 0;
    user-select: none;
}

.content-sync-import .badge {
    font-size: .7rem;
    font-weight: 700;
    padding: .2rem .45rem;
    border-radius: 999px;
}

.content-sync-import .badge-green {
    background: #d1fae5;
    color: #065f46;
}

.content-sync-import .badge-amber {
    background: #fde68a;
    color: #92400e;
}

.content-sync-import .badge-gray {
    background: #e5e7eb;
    color: #374151;
}

.content-sync-import .icon-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 34px;
    height: 34px;
    border-radius: 8px;
    border: 1px solid #e5e7eb;
    background: #fff;
}

.content-sync-import .icon-btn:hover {
    background: #f9fafb;
}
</style>
