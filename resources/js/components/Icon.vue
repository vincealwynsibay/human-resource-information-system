<script setup>
// A small set of line icons (24px grid, drawn for this app).
defineProps({ name: { type: String, required: true }, size: { type: Number, default: 18 } });
const paths = {
    home: 'M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6h-6v6H4a1 1 0 0 1-1-1z',
    runs: 'M4 5h16M4 12h16M4 19h10',
    people: 'M16 19v-1a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v1M9 10a3 3 0 1 0 0-6 3 3 0 0 0 0 6m13 9v-1a4 4 0 0 0-3-3.9M16 4.1a3 3 0 0 1 0 5.8',
    loan: 'M3 7h18v10H3zM7 12h.01M17 12h.01M12 14.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5',
    calendar: 'M4 6h16v14H4zM4 10h16M9 3v4m6-4v4',
    slip: 'M6 3h9l3 3v15H6zM9 9h6m-6 4h6m-6 4h4',
    plus: 'M12 5v14M5 12h14',
    check: 'm5 12 4.5 4.5L19 7',
    x: 'M6 6l12 12M18 6 6 18',
    download: 'M12 4v11m0 0-4-4m4 4 4-4M5 20h14',
    upload: 'M12 20V9m0 0-4 4m4-4 4 4M5 4h14',
    refresh: 'M20 11a8 8 0 1 0-2.3 5.7M20 4v7h-7',
    chevron: 'm9 6 6 6-6 6',
    back: 'm15 6-6 6 6 6',
    sun: 'M12 4V2m0 20v-2m8-8h2M2 12h2m13.7-5.7 1.4-1.4M4.9 19.1l1.4-1.4m0-11.4L4.9 4.9m14.2 14.2-1.4-1.4M12 16a4 4 0 1 0 0-8 4 4 0 0 0 0 8',
    moon: 'M20 14.5A8 8 0 0 1 9.5 4 8 8 0 1 0 20 14.5',
    logout: 'M15 4h4a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1h-4M10 16l-4-4 4-4M6 12h10',
    menu: 'M4 6h16M4 12h16M4 18h16',
    alert: 'M12 9v4m0 4h.01M10.3 3.9 2.4 18a2 2 0 0 0 1.7 3h15.8a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0',
    trash: 'M4 7h16M10 11v6m4-6v6M6 7l1 13h10l1-13M9 7V4h6v3',
    file: 'M6 3h8l4 4v14H6zM14 3v4h4',
    clock: 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18M12 7v5l3 2',
    login: 'M10 17l5-5-5-5M15 12H3M14 4h5a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1h-5',
};
</script>

<template>
    <svg :width="size" :height="size" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path :d="paths[name]" />
    </svg>
</template>
