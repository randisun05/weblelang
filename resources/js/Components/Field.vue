<script setup>
defineProps({
    label: { type: String, default: '' },
    error: { type: String, default: '' },
    hint: { type: String, default: '' },
    required: { type: Boolean, default: false },
});
</script>

<template>
    <div>
        <label v-if="label" class="label">{{ label }} <span v-if="required" class="text-red-500">*</span></label>
        <slot />
        <p v-if="hint && !error" class="mt-1 text-xs text-stone-500">{{ hint }}</p>
        <p v-if="error" class="mt-1 text-xs font-medium text-red-600">{{ error }}</p>
    </div>
</template>
