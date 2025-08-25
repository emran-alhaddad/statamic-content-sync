<template>
    <select :value="value" @input="$emit('input', $event.target.value)" class="cs-select">
        <option value="collections">Collections</option>
        <option value="taxonomies">Taxonomies</option>
        <option value="navigation">Navigation</option>
        <option value="globals">Globals</option>
        <option value="assets">Assets</option>
    </select>
</template>

<script>
export default { props: { value: { type: String, default: 'collections' } } };
</script>