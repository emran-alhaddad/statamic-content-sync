<template>
    <div>
        <label class="font-medium block mb-1">Type</label>
        <select :value="value" @input="$emit('input', $event.target.value)" class="input">
            <option value="collections">Collections</option>
            <option value="taxonomies">Taxonomies</option>
            <option value="navigation">Navigation</option>
            <option value="globals">Globals</option>
            <option value="assets">Assets</option>
        </select>
    </div>
</template>

<script>
export default {
    props: { value: { type: String, default: 'collections' } }
};
</script>