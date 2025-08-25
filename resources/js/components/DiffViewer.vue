<template>
    <div class="mt-3 text-xs grid grid-cols-1 md:grid-cols-2 gap-3">
        <div>
            <div class="font-semibold mb-1">Current</div>
            <pre class="bg-gray-50 p-2 rounded overflow-auto">{{ pretty(current) }}</pre>
        </div>
        <div>
            <div class="font-semibold mb-1">Incoming</div>
            <pre class="bg-gray-50 p-2 rounded overflow-auto">{{ pretty(incoming) }}</pre>
        </div>
    </div>
</template>
<script setup>
import { computed } from 'vue';
const props = defineProps({ diff: { type: Object, required: true } });
const current = computed(() => Object.fromEntries(Object.entries(props.diff).map(([k, v]) => [k, v.current])));
const incoming = computed(() => Object.fromEntries(Object.entries(props.diff).map(([k, v]) => [k, v.incoming])));
function pretty(obj) { return JSON.stringify(obj, null, 2); }
</script>