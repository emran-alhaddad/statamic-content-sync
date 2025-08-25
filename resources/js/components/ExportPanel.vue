<template>
    <div class="cs-card p-6 space-y-5">
        <div class="flex items-start justify-between flex-wrap gap-4">
            <div>
                <div class="cs-title">Export</div>
                <div class="cs-subtle">Choose a type, filter handles & sites, then export to JSON.</div>
            </div>
            <button class="cs-btn ghost" @click="selectAllHandles" v-if="handles.length === 0 && allOptions.length">
                Select All Handles
            </button>
        </div>

        <div class="cs-grid-2">
            <div>
                <label class="cs-label">Type</label>
                <TypePicker v-model="type" />
            </div>

            <div>
                <label class="cs-label">Since (optional ISO8601)</label>
                <input v-model="since" class="cs-input" placeholder="2025-01-01T00:00:00+03:00" />
            </div>
        </div>

        <div class="cs-grid-2">
            <div>
                <label class="cs-label">Select {{ prettyType }}</label>
                <div class="flex gap-2">
                    <div class="flex-1">
                        <input v-model="search" class="cs-input" placeholder="Search…" />
                        <select class="cs-select mt-2" multiple :size="8" v-model="handles">
                            <option v-for="opt in filteredOptions" :key="opt" :value="opt">{{ opt }}</option>
                        </select>
                    </div>
                    <div class="w-36 space-y-2">
                        <button class="cs-btn ghost w-full" @click.prevent="selectAllHandles">Select All</button>
                        <button class="cs-btn ghost w-full" @click.prevent="handles = []">Clear</button>
                    </div>
                </div>
            </div>

            <div class="cs-grid-2">
                <div>
                    <label class="cs-label">Sites (optional, comma separated)</label>
                    <input v-model="sitesRaw" class="cs-input" placeholder="english,arabic" />
                </div>
                <div>
                    <label class="cs-label">Out filename</label>
                    <input v-model="out" class="cs-input" placeholder="my-export.json" />
                </div>
                <div class="col-span-2">
                    <button class="cs-btn primary" @click="runExport" :disabled="busy">
                        {{ busy ? 'Exporting…' : 'Export' }}
                    </button>
                    <span v-if="result" class="ml-3 cs-subtle">Exported <b>{{ result.count }}</b> →
                        <code>{{ result.path }}</code></span>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import TypePicker from './TypePicker.vue';
import MultiSelect from './MultiSelect.vue'; // (we’ll reuse its API)
import { exportPayload } from '../api';
import { fetchOptions } from '../api';

export default {
    components: { TypePicker, MultiSelect },
    data() {
        return {
            type: 'collections',
            handles: [],
            allOptions: [],
            search: '',
            sitesRaw: '',
            since: '',
            out: 'export.json',
            busy: false,
            result: null
        };
    },
    computed: {
        prettyType() {
            return { collections: 'Collections', taxonomies: 'Taxonomies', navigation: 'Navigation', globals: 'Globals', assets: 'Asset Containers' }[this.type];
        },
        filteredOptions() {
            const q = (this.search || '').toLowerCase();
            return this.allOptions.filter(o => o.toLowerCase().includes(q));
        }
    },
    watch: {
        async type(t) {
            this.allOptions = await fetchOptions(t);
            this.handles = [];
            this.search = '';
        }
    },
    created() { this.type = 'collections'; }, // trigger watcher
    methods: {
        selectAllHandles() { this.handles = [...this.filteredOptions]; },
        async runExport() {
            this.busy = true;
            try {
                const payload = {
                    type: this.type,
                    handles: this.handles,
                    sites: this.sitesRaw ? this.sitesRaw.split(',').map(s => s.trim()).filter(Boolean) : [],
                    since: this.since || null,
                    out: this.out
                };
                this.result = await exportPayload(payload);
            } finally { this.busy = false; }
        }
    }
};
</script>