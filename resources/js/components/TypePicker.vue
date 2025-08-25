<template>
    <div>
        <label class="font-medium block mb-1">Type</label>
        <select v-model="model" class="input">
            <option value="collections">Collections</option>
            <option value="taxonomies">Taxonomies</option>
            <option value="navigation">Navigation</option>
            <option value="globals">Globals</option>
            <option value="assets">Assets</option>
        </select>
    </div>
</template>
<script setup>
import { computed } from 'vue';
const props = defineProps({ modelValue: String });
const emit = defineEmits(['update:modelValue']);
const model = computed({ get: () => props.modelValue, set: v => emit('update:modelValue', v) });
</script>