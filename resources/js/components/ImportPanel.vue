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
<script setup>
import { ref } from 'vue';
import { previewImport, commitImport } from '../api';
import DiffViewer from './DiffViewer.vue';

const file = ref(null);
const busy = ref(false);
const diffs = ref(null);
const decisions = ref({});
const committing = ref(false);

function onFile(e) { file.value = e.target.files[0]; diffs.value = null; decisions.value = {}; }
function badge(status) { return status === 'create' ? 'bg-green-100 text-green-800' : status === 'update' ? 'bg-amber-100 text-amber-800' : 'bg-gray-100 text-gray-800'; }

async function preview() {
    if (!file.value) return;
    busy.value = true;
    try {
        const res = await previewImport(file.value);
        diffs.value = res;
        decisions.value = Object.fromEntries(res.diffs.map(d => [d.key, 'incoming']));
    } finally { busy.value = false; }
}

async function commit() {
    committing.value = true;
    try {
        const payload = { type: diffs.value.type, decisions: diffs.value.diffs.map(d => ({ key: d.key, action: decisions.value[d.key], incoming: d.incoming })) };
        const res = await commitImport(payload);
        Statamic.$toast.success(`Updated ${res.results.updated}, Created ${res.results.created}, Skipped ${res.results.skipped}`);
    } finally { committing.value = false; }
}
</script>