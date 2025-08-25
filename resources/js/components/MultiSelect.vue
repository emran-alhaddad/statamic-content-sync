<template>
    <div>
        <label class="cs-label">Select {{ label }}</label>
        <div class="flex gap-2">
            <div class="flex-1">
                <input v-model="q" class="cs-input" placeholder="Search…" />
                <select class="cs-select mt-2" multiple :size="8" :value="value" @change="onChange">
                    <option v-for="opt in filtered" :key="opt" :value="opt">{{ opt }}</option>
                </select>
            </div>
            <div class="w-36 space-y-2">
                <button class="cs-btn ghost w-full" @click.prevent="selectAll">Select All</button>
                <button class="cs-btn ghost w-full" @click.prevent="clearAll">Clear</button>
            </div>
        </div>
    </div>
</template>

<script>
import { fetchOptions } from '../api';

export default {
    props: { type: { type: String, required: true }, value: { type: Array, default: () => [] } },
    data() { return { options: [], q: '' }; },
    computed: {
        label() {
            return ({ collections: 'Collections', taxonomies: 'Taxonomies', navigation: 'Navigation', globals: 'Globals', assets: 'Asset Containers' }[this.type]) || 'Items';
        },
        filtered() {
            const s = this.q.toLowerCase();
            return this.options.filter(o => o.toLowerCase().includes(s));
        }
    },
    watch: {
        type: {
            immediate: true,
            async handler(t) { this.options = await fetchOptions(t); this.$emit('input', []); this.q = ''; }
        }
    },
    methods: {
        onChange(e) { this.$emit('input', Array.from(e.target.selectedOptions).map(o => o.value)); },
        selectAll() { this.$emit('input', [...this.filtered]); },
        clearAll() { this.$emit('input', []); }
    }
};
</script>