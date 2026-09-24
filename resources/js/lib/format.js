const rupiah = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 });
const number = new Intl.NumberFormat('id-ID');

export const money = (value) => rupiah.format(Number(value || 0)).replace(/ /g, ' ');
export const num = (value) => number.format(Number(value || 0));

export const dateTime = (value) =>
    value
        ? new Date(value).toLocaleString('id-ID', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
        : '-';

export const date = (value) =>
    value ? new Date(value).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }) : '-';

/** Mengubah input "1.500.000" menjadi 1500000. */
export const parseMoney = (value) => Number(String(value ?? '').replace(/[^0-9]/g, '')) || 0;
