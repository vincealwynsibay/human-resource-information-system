<script setup>
// Payroll cost by month as stacked bars: net pay, employee deductions and employer contributions, which together are
// what the month cost the company. Hover a month for its figures; "Table" shows the same numbers as a table.
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { amount, peso } from '../../lib/format';

const props = defineProps({ months: { type: Array, required: true }, upTo: { type: Number, default: 12 } });
const SERIES = [
    { key: 'net', label: 'Net pay', cls: 'fill-[var(--series-1)]', dot: 'bg-[var(--series-1)]' },
    { key: 'deductions', label: 'Employee deductions & tax', cls: 'fill-[var(--series-2)]', dot: 'bg-[var(--series-2)]' },
    { key: 'employer', label: 'Employer contributions', cls: 'fill-[var(--series-3)]', dot: 'bg-[var(--series-3)]' },
];
const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

const root = ref(null);
const width = ref(640);
let ro;
onMounted(() => {
    ro = new ResizeObserver(([e]) => (width.value = Math.max(320, e.contentRect.width)));
    ro.observe(root.value);
});
onBeforeUnmount(() => ro?.disconnect());

const asTable = ref(false);
const hover = ref(null);
const H = 240, PAD_L = 56, PAD_B = 26, PAD_T = 10;
const data = computed(() => props.months.slice(0, props.upTo));
const total = (m) => m.net + m.deductions + m.employer;
const niceMax = computed(() => {
    const max = Math.max(1, ...data.value.map(total));
    const step = 10 ** Math.floor(Math.log10(max));
    return Math.ceil(max / step) * step;
});
const ticks = computed(() => [0, 0.25, 0.5, 0.75, 1].map((f) => f * niceMax.value));
const plotW = computed(() => width.value - PAD_L - 8);
const slot = computed(() => plotW.value / Math.max(1, data.value.length));
const barW = computed(() => Math.min(36, slot.value * 0.56));
const y = (v) => PAD_T + (H - PAD_T - PAD_B) * (1 - v / niceMax.value);
const compact = (v) => (v >= 1e6 ? `${(v / 1e6).toFixed(v % 1e6 ? 1 : 0)}M` : v >= 1e3 ? `${Math.round(v / 1e3)}k` : String(v));

// stacked segments, a 2px surface gap between them, the top end rounded
const bars = computed(() => data.value.map((m, i) => {
    const x = PAD_L + i * slot.value + (slot.value - barW.value) / 2;
    let base = 0;
    const segs = [];
    SERIES.forEach((s) => {
        const v = m[s.key];
        if (v > 0) {
            const top = y(base + v), bottom = y(base);
            segs.push({ ...s, x, y: top, h: Math.max(0, bottom - top - (segs.length ? 2 : 0)), v });
        }
        base += v;
    });
    return { m, i, x, segs, top: y(base) };
}));
const totals = computed(() => Object.fromEntries(SERIES.map((s) => [s.key, data.value.reduce((a, m) => a + m[s.key], 0)])));
</script>

<template>
    <div ref="root" class="viz-root">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
            <ul class="flex flex-wrap gap-x-5 gap-y-1 text-xs">
                <li v-for="s in SERIES" :key="s.key" class="flex items-center gap-1.5 text-muted"><span class="size-2.5 rounded-sm" :class="s.dot" />{{ s.label }} <span class="num font-medium text-fg">{{ peso(totals[s.key]) }}</span></li>
            </ul>
            <button class="text-xs text-accent hover:underline" @click="asTable = !asTable">{{ asTable ? 'Chart' : 'Table' }}</button>
        </div>

        <table v-if="asTable" class="w-full text-sm">
            <thead><tr><th class="th">Month</th><th v-for="s in SERIES" :key="s.key" class="th text-right">{{ s.label }}</th><th class="th text-right">Cost</th></tr></thead>
            <tbody>
                <tr v-for="m in data" :key="m.month"><td class="td">{{ MONTHS[m.month - 1] }}</td><td v-for="s in SERIES" :key="s.key" class="td num text-right">{{ amount(m[s.key]) }}</td><td class="td num text-right font-medium">{{ amount(total(m)) }}</td></tr>
            </tbody>
        </table>

        <div v-else class="relative">
            <svg :width="width" :height="H" role="img" :aria-label="`Payroll cost by month, highest ${peso(Math.max(...data.map(total)))}`">
                <g v-for="t in ticks" :key="t">
                    <line :x1="PAD_L" :x2="width - 8" :y1="y(t)" :y2="y(t)" class="stroke-[var(--line)]" stroke-width="1" />
                    <text :x="PAD_L - 8" :y="y(t) + 4" text-anchor="end" class="fill-[var(--muted)] text-[11px]">{{ compact(t) }}</text>
                </g>
                <g v-for="b in bars" :key="b.i" @mouseenter="hover = b" @mouseleave="hover = null">
                    <rect :x="b.x - (slot - barW) / 2" :y="PAD_T" :width="slot" :height="H - PAD_T - PAD_B" fill="transparent" />
                    <rect v-if="hover?.i === b.i" :x="b.x - 6" :y="PAD_T" :width="barW + 12" :height="H - PAD_T - PAD_B" class="fill-[var(--surface-2)]" rx="6" />
                    <template v-for="(s, k) in b.segs" :key="s.key">
                        <path v-if="k === b.segs.length - 1" :d="`M${s.x},${s.y + s.h} v${-(s.h - 4)} q0,-4 4,-4 h${barW - 8} q4,0 4,4 v${s.h - 4} z`" :class="s.cls" />
                        <rect v-else :x="s.x" :y="s.y" :width="barW" :height="s.h" :class="s.cls" />
                    </template>
                    <text :x="b.x + barW / 2" :y="H - 8" text-anchor="middle" class="fill-[var(--muted)] text-[11px]">{{ MONTHS[b.m.month - 1] }}</text>
                </g>
            </svg>
            <div v-if="hover" class="pointer-events-none absolute z-10 w-56 rounded-lg border border-line bg-surface p-3 text-xs shadow-lg" :style="{ left: `${Math.min(hover.x + barW + 10, width - 230)}px`, top: '8px' }">
                <p class="mb-1.5 font-semibold">{{ MONTHS[hover.m.month - 1] }}<span v-if="hover.m.unpaid" class="ml-1 font-normal text-warn">· {{ hover.m.unpaid }} unpaid run(s)</span></p>
                <div v-for="s in SERIES" :key="s.key" class="flex items-center justify-between gap-3 py-0.5"><span class="flex items-center gap-1.5 text-muted"><span class="size-2 rounded-sm" :class="s.dot" />{{ s.label }}</span><span class="num">{{ amount(hover.m[s.key]) }}</span></div>
                <div class="mt-1 flex justify-between border-t border-line pt-1 font-semibold"><span>Cost to the company</span><span class="num">{{ amount(total(hover.m)) }}</span></div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.viz-root { --series-1: #2a78d6; --series-2: #eb6834; --series-3: #1baf7a; }
@media (prefers-color-scheme: dark) {
    :root:where(:not([data-theme="light"])) .viz-root { --series-1: #3987e5; --series-2: #d95926; --series-3: #199e70; }
}
:root[data-theme="dark"] .viz-root { --series-1: #3987e5; --series-2: #d95926; --series-3: #199e70; }
</style>
