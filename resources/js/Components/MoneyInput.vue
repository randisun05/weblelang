<script setup>
import { computed } from 'vue';
import { num, parseMoney } from '@/lib/format';

const model = defineModel({ type: [Number, String, null], default: null });
defineProps({ placeholder: { type: String, default: '0' } });

const display = computed({
    get: () => (model.value === null || model.value === '' ? '' : num(model.value)),
    set: (v) => (model.value = v === '' ? null : parseMoney(v)),
});
</script>

<template>
    <div class="relative">
        <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-sm text-stone-500">Rp</span>
        <input v-model="display" type="text" inputmode="numeric" class="input pl-10" :placeholder="placeholder" />
    </div>
</template>
