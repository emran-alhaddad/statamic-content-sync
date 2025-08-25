<template>
    <div class="card p-4 space-y-4">
        <div>
            <input type="file" @change="onFile" accept="application/json,.json" />
            <button class="btn ml-3" :disabled="!file || busy" @click="preview">{{ busy ? 'Analyzing…' : 'Preview'
                }}</button>
        </div>

        <div v-if="diffs" class="space-y-4">
            <h3 class="font-bold">Preview ({{ diffs.diffs.length }} items)</h3>
            <div v-for="item in diffs.diffs" :key="item.key" class="border rounded p-3">
                <div class="flex items-center justify-between">
                    <div>
                        <b>{{ item.key }}</b>
                        <span class="ml-2 text-xs px-2 py-1 rounded" :class="badge(item.status)">{{ item.status
                            }}</span>
                    </div>
                    <select v-model="decisions[item.key]" class="input w-40">
                        <option value="incoming">Accept incoming</option>
                        <option value="current">Keep current</option>
                    </select>
                </div>
                <DiffViewer :diff="item.diff" />
            </div>

            <button class="btn btn-primary" :disabled="committing" @click="commit">{{ committing ? 'Committing…' :
                'Complete Import' }}</button>
        </div>
    </div>
</template>

<script>
import { previewImport, commitImport } from '../api';
import DiffViewer from './DiffViewer.vue';

export default {
    components: { DiffViewer },
    data() {
        return {
            file: null,
            busy: false,
            diffs: null,
            decisions: {},
            committing: false
        };
    },
    methods: {
        onFile(e) {
            this.file = e.target.files[0];
            this.diffs = null;
            this.decisions = {};
        },
        badge(status) {
            return status === 'create'
                ? 'bg-green-100 text-green-800'
                : status === 'update'
                    ? 'bg-amber-100 text-amber-800'
                    : 'bg-gray-100 text-gray-800';
        },
        async preview() {
            if (!this.file) return;
            this.busy = true;
            try {
                const res = await previewImport(this.file);
                this.diffs = res;
                this.decisions = Object.fromEntries(res.diffs.map(d => [d.key, 'incoming']));
            } finally {
                this.busy = false;
            }
        },
        async commit() {
            this.committing = true;
            try {
                const payload = {
                    type: this.diffs.type,
                    decisions: this.diffs.diffs.map(d => ({
                        key: d.key,
                        action: this.decisions[d.key],
                        incoming: d.incoming
                    }))
                };
                const res = await commitImport(payload);
                Statamic.$toast.success(
                    `Updated ${res.results.updated}, Created ${res.results.created}, Skipped ${res.results.skipped}`
                );
            } finally {
                this.committing = false;
            }
        }
    }
};
</script>