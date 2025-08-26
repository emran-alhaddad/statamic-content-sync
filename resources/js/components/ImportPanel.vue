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

        <div v-if="groups">
            <!-- Strategy controls -->
            <div class="flex flex-wrap items-end gap-4 mb-4">
                <div>
                    <label class="block font-medium mb-1">Review strategy</label>
                    <v-select :options="['manual', 'auto']" v-model="strategy" :clearable="false" style="width: 160px" />
                </div>

                <div v-if="strategy === 'auto'">
                    <label class="block font-medium mb-1">Auto action</label>
                    <v-select :options="['incoming', 'current', 'both']" v-model="autoAction" :clearable="false"
                        style="width: 200px" />
                </div>

                <div class="ml-auto">
                    <button class="btn-primary" :disabled="committing" @click="commit">
                        {{ committing ? 'Committing…' : (strategy === 'auto' ? 'Apply Auto Action' : 'Complete Import') }}
                    </button>
                </div>
            </div>

            <!-- Accordions: handle -> site -> items -->
            <div v-for="(sites, handle) in groups" :key="handle" class="mb-3">
                <div class="bg-gray-100 rounded px-3 py-2 font-semibold cursor-pointer" @click="toggle(handle)">
                    {{ handle }} <span class="text-gray-500">({{ totalInSites(sites) }})</span>
                </div>

                <div v-show="open[handle]" class="mt-2">
                    <div v-for="(items, site) in sites" :key="handle + '::' + site" class="mb-2">
                        <div class="bg-gray-50 rounded px-3 py-2 cursor-pointer" @click="toggle(handle + '::' + site)">
                            <span class="font-medium">Site:</span> {{ site }} <span class="text-gray-500">({{
                                items.length }})</span>
                        </div>

                        <div v-show="open[handle + '::' + site]" class="mt-2 space-y-3">
                            <div v-for="it in items" :key="it.key" class="border rounded p-3">
                                <div class="flex items-center justify-between mb-2">
                                    <div class="font-semibold">
                                        {{ it.key }}
                                        <span class="badge" :class="badge(it.status)">{{ it.status }}</span>
                                    </div>

                                    <div v-if="strategy === 'manual'" class="flex items-center gap-2">
                                        <v-select :options="['incoming', 'current', 'both']" :clearable="false"
                                            v-model="decisions[it.key]" style="width: 170px" />
                                    </div>
                                </div>

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
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="strategy === 'auto'" class="text-gray-600 text-sm">
                All {{ totalChanged }} changed items will be applied as <b>{{ autoAction }}</b>.
            </div>
        </div>
    </div>
</template>

<script>
import { previewImport, commitImport } from '../api';

export default {
    name: 'ImportPanel',
    data() {
        return {
            loading: false,
            error: '',
            groups: null,       // { handle: { site: [ items... ] } }
            type: '',
            open: {},           // accordion state
            strategy: 'manual', // 'manual' | 'auto'
            autoAction: 'incoming',
            decisions: {},      // { key: 'incoming'|'current'|'both' }
            committing: false,
            totalChanged: 0,
        };
    },
    methods: {
        // ---------- UI helpers ----------
        toggle(k) {
            this.$set(this.open, k, !this.open[k]);
        },
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

        // ---------- Diff renderers (needed by the template) ----------
        renderCurrent(item) {
            // Expect item.diff = { path: {status,current,incoming}, ... }
            if (!item || !item.diff || typeof item.diff !== 'object') {
                return this.escape(this.pretty(item?.current ?? {}));
            }
            const lines = [];
            Object.entries(item.diff).forEach(([path, detail]) => {
                const cur = this.escape(
                    typeof detail.current === 'string' ? detail.current : this.pretty(detail.current)
                );
                if (detail.status === 'removed') lines.push(`<span class="line-del">- ${path}: ${cur}</span>`);
                else if (detail.status === 'changed') lines.push(`<span class="line-chg">? ${path}: ${cur}</span>`);
                else if (detail.status === 'added') lines.push(`<span class="line-ctx">  ${path}: (unchanged in current)</span>`);
            });
            return lines.join('\n') || this.escape(this.pretty(item.current ?? {}));
        },
        renderIncoming(item) {
            if (!item || !item.diff || typeof item.diff !== 'object') {
                return this.escape(this.pretty(item?.incoming ?? {}));
            }
            const lines = [];
            Object.entries(item.diff).forEach(([path, detail]) => {
                const inc = this.escape(
                    typeof detail.incoming === 'string' ? detail.incoming : this.pretty(detail.incoming)
                );
                if (detail.status === 'added') lines.push(`<span class="line-add">+ ${path}: ${inc}</span>`);
                else if (detail.status === 'changed') lines.push(`<span class="line-chg">? ${path}: ${inc}</span>`);
                else if (detail.status === 'removed') lines.push(`<span class="line-ctx">  ${path}: (unchanged in incoming)</span>`);
            });
            return lines.join('\n') || this.escape(this.pretty(item.incoming ?? {}));
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
                    // Total changed count
                    this.totalChanged = Object.values(this.groups)
                        .reduce((n, sites) => n + this.totalInSites(sites), 0);

                    // Default manual decisions to 'incoming'
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
            this.committing = true;
            try {
                let payload;

                if (this.strategy === 'auto') {
                    payload = { type: this.type, strategy: 'auto', auto_action: this.autoAction };
                } else {
                    const decisions = [];
                    Object.values(this.groups).forEach(sites => {
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
.code-block {
    background: #0b1220;
    color: #e5edff;
    border-radius: 12px;
    padding: .75rem;
    max-height: 260px;
    overflow: auto;
    font-size: 12px;
}

.line-add {
    color: #16a34a;
}

/* + */
.line-del {
    color: #dc2626;
}

/* - */
.line-chg {
    color: #b45309;
}

/* ? */
.line-ctx {
    color: #94a3b8;
}

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
</style>