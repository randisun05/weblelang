<script setup>
import { usePage } from '@inertiajs/vue3';
import { watch } from 'vue';
import Swal from 'sweetalert2';

// Popup global sukses/error/info/warning (pola yang sama dengan web-aspro).
const page = usePage();
const Toast = Swal.mixin({ toast: true, position: 'top-end', showConfirmButton: false, timer: 4000, timerProgressBar: true });

watch(
    () => page.props.session,
    (session) => {
        if (!session) return;
        for (const icon of ['success', 'error', 'warning', 'info']) {
            if (session[icon]) Toast.fire({ icon, title: session[icon] });
        }
    },
    { immediate: true, deep: true },
);
</script>

<template><span class="hidden" /></template>
