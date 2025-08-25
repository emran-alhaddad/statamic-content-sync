<template>
    <div>
        <label class="font-medium block mb-1">Select {{ label }}</label>
        <div class="flex gap-2">
            <select class="input w-full" multiple size="6" v-model="selected">
                <option v-for="opt in options" :key="opt" :value="opt">{{ opt }}</option>
            </select>
            <div class="w-40">
                <button class="btn w-full mb-2" @click="selectAll">Select All</button>
                <button class="btn w-full" @click="clearAll">Clear</button>
            </div>
        </div>
    </div>
</template>
<script setup>
import { ref, watch, onMounted, computed } from 'vue';
import { fetchOptions } from '../api';

const props = defineProps({ type: { type: String, required: true }, modelValue: { type: Array, default: () => [] } });
const emit = defineEmits(['update:modelValue']);

const options = ref([]);
const selected = ref([]);

const labelMap = { collections: 'Collections', taxonomies: 'Taxonomies', navigation: 'Navigation', globals: 'Globals', assets: 'Asset Containers' };
const label = computed(() => labelMap[props.type] || 'Items');

watch(() => props.type, async (t) => { await load(t); selected.value = []; emit('update:modelValue', []); }, { immediate: true });
watch(selected, v => emit('update:modelValue', v));

async function load(type) { options.value = await fetchOptions(type); }
function selectAll() { selected.value = [...options.value]; }
function clearAll() { selected.value = []; }
</script>