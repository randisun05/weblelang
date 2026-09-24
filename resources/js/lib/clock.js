import { onBeforeUnmount, onMounted, ref } from 'vue';

// Selisih jam server - jam perangkat (ms). Countdown selalu mengacu ke waktu server,
// jadi mengubah jam di HP/laptop tidak memengaruhi apa pun.
const offset = ref(0);
const now = ref(Date.now());
let timer = null;
let users = 0;

export function syncServerTime(serverIso) {
    if (serverIso) offset.value = new Date(serverIso).getTime() - Date.now();
}

export function useClock() {
    onMounted(() => {
        users++;
        if (!timer) timer = setInterval(() => (now.value = Date.now()), 1000);
    });
    onBeforeUnmount(() => {
        users--;
        if (users === 0 && timer) {
            clearInterval(timer);
            timer = null;
        }
    });

    return { serverNow: () => now.value + offset.value, now };
}
