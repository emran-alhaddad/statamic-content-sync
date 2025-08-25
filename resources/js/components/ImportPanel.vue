<template>
    <div class="cs-card p-6 space-y-5">
        <div class="flex items-start justify-between flex-wrap gap-4">
            <div>
                <div class="cs-title">Import</div>
                <div class="cs-subtle">Upload an export JSON, review the diff, choose what to keep, then apply.</div>
            </div>
            <div>
                <input type="file" @change="onFile" accept="application/json,.json" />
                <button class="cs-btn ghost ml-2" :disabled="!file || busy" @click="preview">{{ busy ? 'Analyzing…' :
                    'Preview' }}</button>
            </div>
        </div>

        <div v-if="diffs" class="space-y-4">
            <div class="cs-subtle">Preview ({{ diffs.diffs.length }} items)</div>

            <div v-for="item in diffs.diffs" :key="item.key" class="p-4 rounded-xl border border-gray-200 bg-white">
                <div class="flex items-center justify-between">
                    <div class="font-semibold text-slate-900">
                        {{ item.key }}
                        <span class="cs-badge ml-2" :class="badgeClass(item.status)">{{ item.status }}</span>
                    </div>
                    <select v-model="decisions[item.key]" class="cs-select w-44">
                        <option value="incoming">Accept incoming</option>
                        <option value="current">Keep current</option>
                    </select>
                </div>
                <DiffViewer :diff="item.diff" />
            </div>

            <div class="pt-2">
                <button class="cs-btn primary" :disabled="committing" @click="commit">
                    {{ committing ? 'Committing…' : 'Complete Import' }}
                </button>
            </div>
        </div>
    </div>
</template>

<script>
import { previewImport, commitImport } from '../api';
import DiffViewer from './DiffViewer.vue';

export default {
    components: { DiffViewer },
    data() {
        return { file: null, busy: false, diffs: null, decisions: {}, committing: false };
    },
    methods: {
        onFile(e) { this.file = e.target.files[0]; this.diffs = null; this.decisions = {}; },
        badgeClass(s) { return s === 'create' ? 'green' : s === 'update' ? 'amber' : 'gray'; },
        async preview() {
            if (!this.file) return;
            this.busy = true;
            try {
                const res = await previewImport(this.file);
                this.diffs = res;
                this.decisions = Object.fromEntries(res.diffs.map(d => [d.key, 'incoming']));
            } finally { this.busy = false; }
        },
        async commit() {
            this.committing = true;
            try {
                const payload = { type: this.diffs.type, decisions: this.diffs.diffs.map(d => ({ key: d.key, action: this.decisions[d.key], incoming: d.incoming })) };
                const res = await commitImport(payload);
                Statamic.$toast.success(`Updated ${res.results.updated}, Created ${res.results.created}, Skipped ${res.results.skipped}`);
            } finally { this.committing = false; }
        }
    }
};
</script>