<script setup>
// Placeholder rows while a table loads, shaped like the real ones so the page does not jump.
defineProps({ rows: { type: Number, default: 5 }, cols: { type: Number, default: 4 } });
</script>

<template>
    <tr v-for="r in rows" :key="r" aria-hidden="true">
        <td v-for="c in cols" :key="c" class="td">
            <div class="skeleton h-4" :style="{ width: c === 1 ? '70%' : `${45 + ((r * 17 + c * 11) % 40)}%` }" />
        </td>
    </tr>
</template>
