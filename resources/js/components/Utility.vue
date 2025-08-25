<template>
    <div class="space-y-6">
        <div class="card p-4">
            <div class="flex gap-4">
                <button class="btn" :class="{ 'btn-primary': tab === 'export' }" @click="tab = 'export'">Export</button>
                <button class="btn" :class="{ 'btn-primary': tab === 'import' }" @click="tab = 'import'">Import</button>
            </div>
        </div>
        <ExportPanel v-if="tab === 'export'" />
        <ImportPanel v-else />
    </div>
</template>

<script>
import ExportPanel from './ExportPanel.vue';
import ImportPanel from './ImportPanel.vue';

export default {
    components: { ExportPanel, ImportPanel },
    data() {
        return { tab: 'export' };
    }
};
</script>