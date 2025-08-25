<template>
    <div class="space-y-6">
        <div class="cs-card p-4 flex items-center justify-between">
            <div class="cs-title">Content Sync</div>
            <div class="cs-tabbar">
                <button class="cs-tab" :class="{ active: tab === 'export' }" @click="tab = 'export'">Export</button>
                <button class="cs-tab" :class="{ active: tab === 'import' }" @click="tab = 'import'">Import</button>
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
    data() { return { tab: 'export' }; }
};
</script>