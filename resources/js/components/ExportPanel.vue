<template>
    <div class="card p-6 space-y-5">
        <div class="flex items-start justify-between flex-wrap gap-4">
            <div>
                <div class="font-bold text-lg">Export</div>
                <div class="text-gray-600">Select a type, handles, sites and a “since” date/time. The file downloads
                    locally and includes a tamper-proof signature.</div>
            </div>
        </div>

        <div class="flex flex-wrap gap-4">
            <div class="w-full lg:w-1/3">
                <label class="block font-medium mb-1">Type</label>
                <v-select :options="types" v-model="type" :clearable="false"></v-select>
            </div>

            <div class="w-full lg:w-1/3">
                <label class="block font-medium mb-1">Sites</label>
                <v-select :options="siteOptions" v-model="sites" :multiple="true" :reduce="o => o" />
            </div>

            <div class="w-full lg:w-1/3">
                <label class="block font-medium mb-1">Since</label>
                <input type="datetime-local" v-model="sinceLocal" class="input" />
                <small class="text-gray-500">Optional. Filters by updated_at ≥ since.</small>
            </div>
        </div>

        <div>
            <label class="block font-medium mb-1">Handles</label>
            <v-select :options="handleOptions" v-model="handles" :multiple="true" :reduce="o => o" :taggable="false"
                placeholder="Select one or more" />
            <div class="mt-2 text-gray-600 text-sm" v-if="!handleOptions.length">
                No handles available for “{{ type }}”.
            </div>
        </div>

        <div class="flex items-center gap-3">
            <button class="btn-primary" @click="runExport" :disabled="busy">{{ busy ? 'Exporting…' : 'Export & Download'
                }}</button>
            <span v-if="result" class="text-gray-600">Exported <b>{{ result.count }}</b> →
                <code>{{ result.path }}</code></span>
        </div>
    </div>
</template>

<script>
import { exportPayload, fetchOptions, fetchSites } from '../api';

export default {
    data() {
        return {
            types: ['collections', 'taxonomies', 'navigation', 'globals', 'assets'],
            type: 'collections',
            handles: [],
            handleOptions: [],
            siteOptions: [],
            sites: [],
            sinceLocal: '',
            busy: false,
            result: null,
        };
    },
    async created() {
        // load sites & collection handles immediately (no need to switch away/back)
        this.siteOptions = await fetchSites();
        this.handleOptions = await fetchOptions(this.type);
    },
    watch: {
        async type(t) {
            this.handles = [];
            this.handleOptions = await fetchOptions(t);
        }
    },
    methods: {
        toISO(dtLocal) {
            if (!dtLocal) return '';
            // add timezone offset because input gives local time without offset
            const d = new Date(dtLocal);
            return d.toISOString();
        },
        async runExport() {
            this.busy = true;
            try {
                const payload = {
                    type: this.type,
                    handles: this.handles,
                    sites: this.sites,
                    since: this.toISO(this.sinceLocal),
                    out: '' // server will generate filename
                };
                this.result = await exportPayload(payload);
                Statamic.$toast.success('Export downloaded.');
            } catch (e) {
                Statamic.$toast.error(e.message || 'Export failed.');
            } finally {
                this.busy = false;
            }
        }
    }
};
</script>