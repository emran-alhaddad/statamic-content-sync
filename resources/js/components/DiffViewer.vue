<template>
    <div class="mt-4 grid gap-3" style="grid-template-columns: 1fr 1fr;">
        <div>
            <div class="font-semibold mb-1 text-slate-800">Current</div>
            <pre class="cs-pre">{{ pretty(current) }}</pre>
        </div>
        <div>
            <div class="font-semibold mb-1 text-slate-800">Incoming</div>
            <pre class="cs-pre">{{ pretty(incoming) }}</pre>
        </div>
    </div>
</template>

<script>
export default {
    props: { diff: { type: Object, required: true } },
    computed: {
        current() { const o = {}; Object.entries(this.diff).forEach(([k, v]) => o[k] = v.current); return o; },
        incoming() { const o = {}; Object.entries(this.diff).forEach(([k, v]) => o[k] = v.incoming); return o; }
    },
    methods: { pretty(obj) { return JSON.stringify(obj, null, 2); } }
};
</script>