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
                        {{ committing ? 'Committing…' : (strategy === 'auto' ? 'Apply Auto Action' : 'Complete Import') }}
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
import { previewImport, commitImport } from '../api';

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
            contextLines: 6, // show N lines of context around each change (GitHub-like)
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

        // ---- Deep equal (stable) -------------------------------------------------
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

        // ---- What constitutes equality per type ----------------------------------
        relevantPair(item, type) {
            const c = item?.current ?? {}, i = item?.incoming ?? {};
            switch (type) {
                case 'collections': return [{ data: c.data ?? {}, published: 'published' in c ? !!c.published : undefined }, { data: i.data ?? {}, published: 'published' in i ? !!i.published : undefined }];
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

        // ---- Diff engine (key-path oriented) -------------------------------------
        computeDiff(item) {
            const [rc, ri] = this.relevantPair(item, this.type);
            const diff = [];
            const walk = (a, b, path = '') => {
                const aObj = a && typeof a === 'object', bObj = b && typeof b === 'object';
                if (!aObj && !bObj) { if (a !== b) diff.push({ path, status: (a === undefined) ? 'added' : (b === undefined) ? 'removed' : 'changed', current: a, incoming: b }); return; }
                if (Array.isArray(a) || Array.isArray(b)) { if (!this.deepEqual(a ?? [], b ?? [])) diff.push({ path, status: (a === undefined) ? 'added' : (b === undefined) ? 'removed' : 'changed', current: a, incoming: b }); return; }
                const keys = new Set([...(a ? Object.keys(a) : []), ...(b ? Object.keys(b) : [])]);[...keys].sort().forEach(k => walk(a ? a[k] : undefined, b ? b[k] : undefined, path ? `${path}.${k}` : k));
            };
            walk(rc, ri, '');
            return diff;
        },

        // ---- Time precedence (+/- placement per panel) ---------------------------
        toEpoch(val) {
            if (val == null) return 0;
            if (typeof val === 'number') return (val > 1e12) ? val : val * 1000;
            if (typeof val === 'string') {
                const n = Number(val);
                if (!Number.isNaN(n)) return (n > 1e12) ? n : n * 1000;
                const t = Date.parse(val); return Number.isNaN(t) ? 0 : t;
            }
            return 0;
        },
        itemEpoch(obj) {
            // prefer top-level updated_at, fall back to data.updated_at
            return this.toEpoch(obj?.updated_at ?? obj?.data?.updated_at ?? 0);
        },
        fresherSide(item) {
            const cur = this.itemEpoch(item.current), inc = this.itemEpoch(item.incoming);
            if (cur > inc) return 'current';
            if (inc > cur) return 'incoming';
            // tie-breaker: prefer incoming
            return 'incoming';
        },

        // ---- Context windowing helpers -------------------------------------------
        lastKeyFromPath(p) { const parts = p.split('.'); return parts[parts.length - 1]; },
        findAllMatchIndexes(lines, key) {
            const needle = `"${key}"`;
            const idxs = [];
            for (let i = 0; i < lines.length; i++) {
                if (lines[i].includes(needle)) idxs.push(i);
            }
            return idxs;
        },
        mergeWindows(windows) {
            if (!windows.length) return [];
            windows.sort((a, b) => a[0] - b[0]);
            const out = [windows[0].slice()];
            for (let i = 1; i < windows.length; i++) {
                const [s, e] = windows[i], last = out[out.length - 1];
                if (s <= last[1] + 1) { last[1] = Math.max(last[1], e); } else out.push([s, e]);
            }
            return out;
        },

        // Build a panel with GitHub-like context and +/- per recency
        renderPanelWithContext(item, side) {
            const other = side === 'current' ? 'incoming' : 'current';
            const obj = item[side] || {};
            const json = this.pretty(this.relevantPair(item, this.type)[side === 'current' ? 0 : 1]);
            const lines = json.split('\n');

            const changes = this.computeDiff(item);
            if (!changes.length) {
                // no diffs under relevant subset – show nothing
                return this.escape('(no changes)');
            }

            const keyHits = [];
            const changeMap = {}; // lineIndex -> { type: 'add'|'del'|'chg', label:'+|-' }
            const fresher = this.fresherSide(item);

            // collect windows around each changed path (based on last key segment)
            const context = this.contextLines;
            changes.forEach(d => {
                const key = this.lastKeyFromPath(d.path);
                const idxs = this.findAllMatchIndexes(lines, key);
                if (!idxs.length) {
                    // fallback: highlight opening brace of the object
                    idxs.push(Math.max(lines.findIndex(l => l.trim().endsWith('{')), 0));
                }
                idxs.forEach(idx => {
                    keyHits.push([Math.max(0, idx - context), Math.min(lines.length - 1, idx + context)]);
                    // Decide sign/color for this line in this panel
                    let label = '?', type = 'chg';
                    if (d.status === 'changed') {
                        // fresher side gets '+', older gets '-'
                        label = (side === fresher) ? '+' : '-';
                        type = (side === fresher) ? 'add' : 'del';
                    } else if (d.status === 'added') { // exists only in incoming subset
                        if (side === 'incoming') { label = '+'; type = 'add'; } else { label = '-'; type = 'del'; }
                    } else if (d.status === 'removed') { // exists only in current subset
                        if (side === 'current') { label = '+'; type = 'add'; } else { label = '-'; type = 'del'; }
                    }
                    changeMap[idx] = { type, label };
                });
            });

            const windows = this.mergeWindows(keyHits);
            const out = [];
            windows.forEach(([s, e], wi) => {
                if (wi > 0) out.push('<div class="hunk-gap">…</div>');
                for (let i = s; i <= e; i++) {
                    const raw = lines[i];
                    const mark = changeMap[i];
                    if (mark) {
                        const cls = mark.type === 'add' ? 'line-add' : mark.type === 'del' ? 'line-del' : 'line-chg';
                        out.push(`<span class="${cls}">${this.escape(mark.label)} ${this.escape(raw)}</span>`);
                    } else {
                        out.push(this.escape(raw));
                    }
                }
            });

            return out.join('\n');
        },

        // Final merge with green highlights on the changed keys only
        renderFinalWithContext(item, decision) {
            const merged = this.finalMerge(item, decision);
            const json = this.pretty(merged);
            const lines = json.split('\n');

            const changes = this.computeDiff(item);
            if (!changes.length) return this.escape(json);

            const context = this.contextLines;
            const keyHits = [];
            const markIdx = new Set();

            changes.forEach(d => {
                const key = this.lastKeyFromPath(d.path);
                const idxs = this.findAllMatchIndexes(lines, key);
                if (!idxs.length) {
                    idxs.push(Math.max(lines.findIndex(l => l.trim().endsWith('{')), 0));
                }
                idxs.forEach(idx => {
                    keyHits.push([Math.max(0, idx - context), Math.min(lines.length - 1, idx + context)]);
                    markIdx.add(idx);
                });
            });

            const windows = this.mergeWindows(keyHits);
            const out = [];
            windows.forEach(([s, e], wi) => {
                if (wi > 0) out.push('<div class="hunk-gap">…</div>');
                for (let i = s; i <= e; i++) {
                    const raw = lines[i];
                    if (markIdx.has(i)) {
                        out.push(`<span class="line-final">+ ${this.escape(raw)}</span>`);
                    } else {
                        out.push(this.escape(raw));
                    }
                }
            });

            return out.join('\n');
        },

        finalMerge(item, decision) {
            const c = item.current, i = item.incoming;
            if (decision === 'incoming') return i;
            if (decision === 'current') return c;
            const merge = (a, b) => { if (Array.isArray(a) && Array.isArray(b)) return b; if (a && typeof a === 'object' && b && typeof b === 'object') { const out = { ...a }; Object.keys(b).forEach(k => out[k] = merge(a[k], b[k])); return out; } return b !== undefined ? b : a; };
            return merge(c, i);
        },
        autoDefault(item) {
            // if you switch manual->auto mid-review we pick fresher side as sensible default
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

/* Full-line highlights */
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

/* green for + */
.content-sync-import .line-del {
    background: rgba(239, 68, 68, .20);
    color: #fff;
}

/* red for - */
.content-sync-import .line-chg {
    background: rgba(245, 158, 11, .15);
    color: #f59e0b;
}

/* amber (fallback) */

/* Final merge uses green to indicate accepted/affected lines */
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