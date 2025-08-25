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

<script>
export default {
    props: { diff: { type: Object, required: true } },
    computed: {
        current() {
            const obj = {};
            Object.entries(this.diff).forEach(([k, v]) => (obj[k] = v.current));
            return obj;
        },
        incoming() {
            const obj = {};
            Object.entries(this.diff).forEach(([k, v]) => (obj[k] = v.incoming));
            return obj;
        }
    },
    methods: {
        pretty(obj) {
            return JSON.stringify(obj, null, 2);
        }
    }
};
</script>