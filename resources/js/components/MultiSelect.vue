<template>
    <div>
        <label class="font-medium block mb-1">Select {{ label }}</label>
        <div class="flex gap-2">
            <select class="input w-full" multiple size="6" :value="value" @change="onChange">
                <option v-for="opt in options" :key="opt" :value="opt">{{ opt }}</option>
            </select>
            <div class="w-40">
                <button class="btn w-full mb-2" @click.prevent="selectAll">Select All</button>
                <button class="btn w-full" @click.prevent="clearAll">Clear</button>
            </div>
        </div>
    </div>
</template>

<script>
import { fetchOptions } from '../api';

export default {
    props: {
        type: { type: String, required: true },
        value: { type: Array, default: () => [] }
    },
    data() {
        return { options: [] };
    },
    computed: {
        label() {
            return (
                {
                    collections: 'Collections',
                    taxonomies: 'Taxonomies',
                    navigation: 'Navigation',
                    globals: 'Globals',
                    assets: 'Asset Containers'
                }[this.type] || 'Items'
            );
        }
    },
    watch: {
        type: {
            immediate: true,
            async handler(t) {
                this.options = await fetchOptions(t);
                this.$emit('input', []); // reset selection on type change
            }
        }
    },
    methods: {
        onChange(e) {
            const selected = Array.from(e.target.selectedOptions).map(o => o.value);
            this.$emit('input', selected);
        },
        selectAll() {
            this.$emit('input', [...this.options]);
        },
        clearAll() {
            this.$emit('input', []);
        }
    }
};
</script>