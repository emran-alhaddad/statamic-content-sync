<template>
    <div class="card p-6 space-y-5">
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
            <!-- Strategy controls -->
            <div class="flex flex-wrap items-end gap-4 mb-4">
                <div>
                    <label class="block font-medium mb-1">Review strategy</label>
                    <v-select :options="strategyOptions" :reduce="o => o.value" v-model="strategy" :clearable="false"
                        style="width: 180px" />
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
                <!-- Handle header -->
                <button class="w-full flex items-center justify-between px-3 py-2 bg-gray-100 hover:bg-gray-200"
                    @click="toggle(handle)">
                    <div class="font-semibold">
                        {{ handle }}
                        <span class="text-gray-500">({{ totalInSites(sites) }})</span>
                    </div>
                    <ChevronIcon :open="isOpen(handle)" />
                </button>

                <div v-show="isOpen(handle)" class="p-3 pt-2">
                    <div v-for="(items, site) in sites" :key="handle + '::' + site" class="mb-2 border rounded">
                        <!-- Site header -->
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
                                <div v-show="isOpen('item::' + it.key)" class="px-3 pb-3 space-y-3">
                                    <template v-if="strategy === 'manual'">
                                        <div class="flex flex-wrap gap-3">
                                            <div class="flex-1 min-w-[300px]">
                                                <div class="text-gray-700 font-semibold mb-1">Current</div>
                                                <pre class="code-block" v-html="renderCurrent(it)"></pre>
                                            </div>
                                            <div class="flex-1 min-w-[300px]">
                                                <div class="text-gray-700 font-semibold mb-1">Incoming</div>
                                                <pre class="code-block" v-html="renderIncoming(it)"></pre>
                                            </div>
                                        </div>

                                        <div class="mt-3">
                                            <div class="text-gray-700 font-semibold mb-1">Final merge</div>
                                            <pre
                                                class="code-block">{{ pretty(finalMerge(it, decisions[it.key] || 'incoming')) }}</pre>
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
            groups: null,       // raw groups from server { handle: { site: [ items... ] } }
            type: '',
            open: {},           // accordion state
            strategy: 'manual', // 'manual' | 'auto'
            autoAction: 'incoming',
            decisions: {},      // { key: 'incoming'|'current'|'both' }
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
        };
    },
    computed: {
        /**
         * Client-side filter: hide items where current === incoming.
         * Also rebuild totalChanged.
         */
        filteredGroups() {
            if (!this.groups) return null;
            const out = {};
            let count = 0;

            const isEqual = (a, b) => {
                try { return JSON.stringify(a) === JSON.stringify(b); } catch { return false; }
            };

            Object.entries(this.groups).forEach(([handle, sites]) => {
                const siteMap = {};
                Object.entries(sites).forEach(([site, items]) => {
                    const filtered = (items || []).filter(it => !isEqual(it.current, it.incoming));
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
        // ---------- UI helpers ----------
        isOpen(k) { return !!this.open[k]; },
        toggle(k) { this.$set(this.open, k, !this.open[k]); },
        totalInSites(sites) {
            return Object.values(sites).reduce((n, arr) => n + (Array.isArray(arr) ? arr.length : 0), 0);
        },
        badge(status) {
            return status === 'create' ? 'badge-green'
                : status === 'update' ? 'badge-amber'
                    : 'badge-gray';
        },
        pretty(o) {
            try { return JSON.stringify(o, null, 2); } catch { return String(o); }
        },
        escape(v) {
            return String(v).replace(/[&<>]/g, s => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;' }[s]));
        },
        labelFor(v) {
            const opt = this.acceptOptions.find(o => o.value === v);
            return opt ? opt.label : v;
        },

        // ---------- Diff renderers (full-line highlighting, no unchanged noise) ----------
        renderCurrent(item) {
            if (!item || !item.diff || typeof item.diff !== 'object') {
                return this.escape(this.pretty(item?.current ?? {}));
            }
            const lines = [];
            Object.entries(item.diff).forEach(([path, detail]) => {
                // Show only what matters on CURRENT pane: removed OR changed
                if (detail.status === 'removed' || detail.status === 'changed') {
                    const cur = this.escape(typeof detail.current === 'string'
                        ? detail.current : this.pretty(detail.current));
                    const cls = detail.status === 'removed' ? 'line-del' : 'line-chg';
                    lines.push(`<span class="${cls}">- ${path}: ${cur}</span>`);
                }
            });
            return lines.length ? lines.join('\n') : this.escape(this.pretty(item.current ?? {}));
        },
        renderIncoming(item) {
            if (!item || !item.diff || typeof item.diff !== 'object') {
                return this.escape(this.pretty(item?.incoming ?? {}));
            }
            const lines = [];
            Object.entries(item.diff).forEach(([path, detail]) => {
                // Show only what matters on INCOMING pane: added OR changed
                if (detail.status === 'added' || detail.status === 'changed') {
                    const inc = this.escape(typeof detail.incoming === 'string'
                        ? detail.incoming : this.pretty(detail.incoming));
                    const cls = detail.status === 'added' ? 'line-add' : 'line-chg';
                    lines.push(`<span class="${cls}">+ ${path}: ${inc}</span>`);
                }
            });
            return lines.length ? lines.join('\n') : this.escape(this.pretty(item.incoming ?? {}));
        },

        // ---------- Merge logic ----------
        finalMerge(item, decision) {
            if (decision === 'incoming') return item.incoming;
            if (decision === 'current') return item.current;

            const merge = (a, b) => {
                if (Array.isArray(a) && Array.isArray(b)) return b; // prefer incoming arrays
                if (a && typeof a === 'object' && b && typeof b === 'object') {
                    const out = { ...a };
                    Object.keys(b).forEach(k => { out[k] = merge(a[k], b[k]); });
                    return out;
                }
                return b !== undefined ? b : a;
            };
            return merge(item.current, item.incoming);
        },

        // ---------- File flow ----------
        onFile(e) {
            const f = e.target.files && e.target.files[0];
            if (!f) return;

            this.loading = true;
            this.error = '';
            this.groups = null;
            this.decisions = {};
            this.open = {};

            previewImport(f)
                .then(res => {
                    this.type = res.type;
                    this.groups = res.groups || {};

                    // Defaults for manual decisions
                    if (this.strategy === 'manual') {
                        Object.values(this.groups).forEach(sites => {
                            Object.values(sites).forEach(items => {
                                items.forEach(it => this.$set(this.decisions, it.key, 'incoming'));
                            });
                        });
                    }

                    // Open top-level accordions by default
                    Object.keys(this.groups).forEach(h => this.$set(this.open, h, true));
                })
                .catch(err => {
                    this.error = err?.message || 'Failed to analyze file.';
                })
                .finally(() => {
                    this.loading = false;
                });
        },

        // ---------- Commit ----------
        async commit() {
            if (this.totalChanged === 0) {
                Statamic.$toast.info('Nothing to apply.');
                return;
            }

            this.committing = true;
            try {
                let payload;

                if (this.strategy === 'auto') {
                    payload = { type: this.type, strategy: 'auto', auto_action: this.autoAction };
                } else {
                    // Build decisions only from filtered (changed) items
                    const decisions = [];
                    Object.values(this.filteredGroups).forEach(sites => {
                        Object.values(sites).forEach(items => {
                            items.forEach(it => decisions.push({ key: it.key, action: this.decisions[it.key] || 'incoming' }));
                        });
                    });
                    payload = { type: this.type, strategy: 'manual', decisions };
                }

                const res = await commitImport(payload);
                Statamic.$toast.success(
                    `Updated ${res.results.updated}, Created ${res.results.created}, Skipped ${res.results.skipped}`
                );
            } catch (e) {
                Statamic.$toast.error(e?.message || 'Commit failed.');
            } finally {
                this.committing = false;
            }
        },
    },
};
</script>

<style scoped>
/* Code blocks */
.code-block {
    background: #0b1220;
    color: #e5edff;
    border-radius: 12px;
    padding: .75rem;
    max-height: 260px;
    overflow: auto;
    font-size: 12px;
    line-height: 1.45;
}

/* Full-line highlight */
.line-add,
.line-del,
.line-chg,
.line-ctx {
    display: block;
    padding: 2px 6px;
    border-radius: 6px;
    margin: 1px 0;
    white-space: pre-wrap;
}

.line-add {
    background: rgba(16, 185, 129, .12);
    color: #10b981;
}

/* green */
.line-del {
    background: rgba(239, 68, 68, .12);
    color: #ef4444;
}

/* red */
.line-chg {
    background: rgba(245, 158, 11, .12);
    color: #f59e0b;
}

/* amber */
.line-ctx {
    color: #94a3b8;
}

/* Badges */
.badge {
    font-size: .7rem;
    font-weight: 700;
    padding: .2rem .45rem;
    border-radius: 999px;
}

.badge-green {
    background: #d1fae5;
    color: #065f46;
}

.badge-amber {
    background: #fde68a;
    color: #92400e;
}

.badge-gray {
    background: #e5e7eb;
    color: #374151;
}

/* Small icon button */
.icon-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 34px;
    height: 34px;
    border-radius: 8px;
    border: 1px solid #e5e7eb;
    background: #fff;
}

.icon-btn:hover {
    background: #f9fafb;
}
</style>