<script setup>
defineProps({ no: [String, Number], title: String });
</script>

<template>
    <section :id="`pasal-${no}`" class="scroll-mt-24">
        <h2 class="mt-10 text-lg font-bold text-ink">{{ no }}. {{ title }}</h2>
        <div class="mt-3 space-y-3 text-[15px] leading-relaxed text-stone-700 [&_li]:ml-5 [&_ol]:list-decimal [&_ol]:space-y-1.5 [&_ul]:list-disc [&_ul]:space-y-1.5">
            <slot />
        </div>
    </section>
</template>
