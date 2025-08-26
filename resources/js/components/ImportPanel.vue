<template>
    <div class="card p-6 space-y-5">
        <div class="flex items-start justify-between flex-wrap gap-4">
            <div>
                <div class="font-bold text-lg">Import</div>
                <div class="text-gray-600">Upload an exported JSON. We verify integrity, show only affected items, and
                    compute a final merge per item.</div>
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

        <div v-if="diffs && diffs.diffs && diffs.diffs.length">
            <div class="mb-2 text-gray-600">Changed items: <b>{{ diffs.diffs.length }}</b></div>

            <div v-for="item in diffs.diffs" :key="item.key" class="border rounded p-3 mb-4">
                <div class="flex items-center justify-between mb-2">
                    <div class="font-semibold">
                        {{ item.key }}
                        <span class="badge" :class="badge(item.status)">{{ item.status }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <label class="sr-only">Decision</label>
                        <v-select :options="['incoming', 'current', 'both']" :clearable="false"
                            v-model="decisions[item.key]" style="width: 170px" />
                    </div>
                </div>

                <!-- side-by-side current vs incoming, final merge below -->
                <div class="flex flex-wrap gap-3">
                    <div class="flex-1 min-w-[300px]">
                        <div class="text-gray-700 font-semibold mb-1">Current</div>
                        <pre class="code-block" v-html="renderCurrent(item)"></pre>
                    </div>
                    <div class="flex-1 min-w-[300px]">
                        <div class="text-gray-700 font-semibold mb-1">Incoming</div>
                        <pre class="code-block" v-html="renderIncoming(item)"></pre>
                    </div>
                </div>

                <div class="mt-3">
                    <div class="text-gray-700 font-semibold mb-1">Final merge</div>
                    <pre class="code-block">{{ pretty(finalMerge(item)) }}</pre>
                </div>
            </div>

            <div class="pt-2">
                <button class="btn-primary" :disabled="committing" @click="commit">{{ committing ? 'Committing…' :
                    'Complete Import' }}</button>
            </div>
        </div>
    </div>
</template>

<script>
import { previewImport, commitImport } from '../api';

export default {
    data() {
        return { loading: false, error: '', diffs: null, decisions: {}, committing: false };
    },
    methods: {
        onFile(e) {
            const f = e.target.files[0];
            if (!f) return;
            this.error = ''; this.diffs = null;
            this.loading = true;
            previewImport(f)
                .then(res => {
                    this.diffs = res;
                    // default all to incoming
                    this.decisions = Object.fromEntries(res.diffs.map(d => [d.key, 'incoming']));
                })
                .catch(err => { this.error = err.message || 'Failed to analyze file.'; })
                .finally(() => { this.loading = false; });
        },
        badge(status) { return status === 'create' ? 'badge-green' : status === 'update' ? 'badge-amber' : 'badge-gray'; },
        pretty(o) { return JSON.stringify(o, null, 2); },

        // diff renderers with -, ?, +
        renderCurrent(item) {
            const lines = [];
            for (const [path, detail] of Object.entries(item.diff)) {
                if (detail.status === 'removed') lines.push(`<span class="line-del">- ${path}: ${this.escape(detail.current)}</span>`);
                else if (detail.status === 'changed') lines.push(`<span class="line-chg">? ${path}: ${this.escape(detail.current)}</span>`);
                else if (detail.status === 'added') lines.push(`<span class="line-ctx">  ${path}: (unchanged in current)</span>`);
            }
            return lines.join('\n') || this.escape(this.pretty(item.current));
        },
        renderIncoming(item) {
            const lines = [];
            for (const [path, detail] of Object.entries(item.diff)) {
                if (detail.status === 'added') lines.push(`<span class="line-add">+ ${path}: ${this.escape(detail.incoming)}</span>`);
                else if (detail.status === 'changed') lines.push(`<span class="line-chg">? ${path}: ${this.escape(detail.incoming)}</span>`);
                else if (detail.status === 'removed') lines.push(`<span class="line-ctx">  ${path}: (unchanged in incoming)</span>`);
            }
            return lines.join('\n') || this.escape(this.pretty(item.incoming));
        },
        escape(v) {
            return String(typeof v === 'string' ? v : JSON.stringify(v)).replace(/[&<>]/g, s => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;' }[s]));
        },
        finalMerge(item) {
            const decision = this.decisions[item.key] || 'incoming';
            if (decision === 'incoming') return item.incoming;
            if (decision === 'current') return item.current;
            // both: deep merge (incoming wins on conflict)
            const merge = (a, b) => {
                if (Array.isArray(a) && Array.isArray(b)) return b; // keep incoming array
                if (a && typeof a === 'object' && b && typeof b === 'object') {
                    const out = { ...a };
                    Object.keys(b).forEach(k => out[k] = merge(a[k], b[k]));
                    return out;
                }
                return b !== undefined ? b : a;
            };
            return merge(item.current, item.incoming);
        },
        async commit() {
            this.committing = true;
            try {
                const payload = {
                    type: this.diffs.type,
                    decisions: this.diffs.diffs.map(d => ({
                        key: d.key,
                        action: this.decisions[d.key] || 'incoming',
                        incoming: d.incoming
                    }))
                };
                const res = await commitImport(payload);
                Statamic.$toast.success(`Updated ${res.results.updated}, Created ${res.results.created}, Skipped ${res.results.skipped}`);
            } catch (e) {
                Statamic.$toast.error(e.message || 'Commit failed.');
            } finally {
                this.committing = false;
            }
        }
    }
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