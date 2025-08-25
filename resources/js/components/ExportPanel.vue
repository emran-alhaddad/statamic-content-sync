<template>
    <div class="card p-4 space-y-4">
        <TypePicker v-model="type" />
        <MultiSelect v-if="type" :type="type" v-model="handles" />

        <div class="flex gap-3 items-end">
            <div class="w-1/3">
                <label class="font-medium">Sites (optional, comma separated)</label>
                <input v-model="sitesRaw" class="input" placeholder="english,arabic" />
            </div>
            <div class="w-1/3">
                <label class="font-medium">Since (optional ISO8601)</label>
                <input v-model="since" class="input" placeholder="2025-01-01T00:00:00+03:00" />
            </div>
            <div class="w-1/3">
                <label class="font-medium">Out filename</label>
                <input v-model="out" class="input" placeholder="my-export.json" />
            </div>
        </div>

        <div>
            <button class="btn btn-primary" @click="runExport" :disabled="busy">{{ busy ? 'Exporting…' : 'Export'
            }}</button>
        </div>

        <div v-if="result" class="mt-4 text-sm">
            <p>Exported <b>{{ result.count }}</b> items → <code>{{ result.path }}</code></p>
        </div>
    </div>
</template>
<script setup>
import { ref } from 'vue';
import TypePicker from './TypePicker.vue';
import MultiSelect from './MultiSelect.vue';
import { exportPayload } from '../api';

const type = ref('collections');
const handles = ref([]);
const sitesRaw = ref('');
const since = ref('');
const out = ref('export.json');
const busy = ref(false);
const result = ref(null);

async function runExport() {
    busy.value = true;
    try {
        const payload = {
            type: type.value,
            handles: handles.value,
            sites: sitesRaw.value ? sitesRaw.value.split(',').map(s => s.trim()).filter(Boolean) : [],
            since: since.value || null,
            out: out.value,
        };
        result.value = await exportPayload(payload);
    } finally {
        busy.value = false;
    }
}
</script>