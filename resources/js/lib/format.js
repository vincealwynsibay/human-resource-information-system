const pesoFmt = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP', minimumFractionDigits: 2 });
const numberFmt = new Intl.NumberFormat('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

export const peso = (n) => (n === null || n === undefined || n === '' ? '—' : pesoFmt.format(Number(n)));
export const amount = (n) => (n === null || n === undefined || n === '' ? '—' : numberFmt.format(Number(n)));

const dateFmt = new Intl.DateTimeFormat('en-PH', { month: 'short', day: 'numeric', year: 'numeric', timeZone: 'UTC' });
const shortFmt = new Intl.DateTimeFormat('en-PH', { month: 'short', day: 'numeric', timeZone: 'UTC' });

/** A YYYY-MM-DD (or ISO) date as "Oct 31, 2026". */
export const date = (iso) => (iso ? dateFmt.format(new Date(`${String(iso).slice(0, 10)}T00:00:00Z`)) : '—');

/** A pay period as "Oct 1 – 31, 2026" or "Dec 16, 2026 – Jan 15, 2027". */
export function period(start, end) {
    const s = String(start).slice(0, 10), e = String(end).slice(0, 10);
    if (s === e) return date(s);
    if (s.slice(0, 7) === e.slice(0, 7)) return `${shortFmt.format(new Date(`${s}T00:00:00Z`))} – ${Number(e.slice(8))}, ${e.slice(0, 4)}`;
    if (s.slice(0, 4) === e.slice(0, 4)) return `${shortFmt.format(new Date(`${s}T00:00:00Z`))} – ${date(e)}`;
    return `${date(s)} – ${date(e)}`;
}

export const STATUS_LABEL = { DRAFT: 'Draft', PENDING_APPROVAL: 'Awaiting approval', APPROVED: 'Approved', PAID: 'Paid', CANCELLED: 'Cancelled' };
export const KIND_LABEL = { regular: 'Regular', thirteenth: '13th month', final_pay: 'Final pay', year_end_adjustment: 'Year-end adjustment' };
export const FREQUENCY_LABEL = { MONTHLY: 'Monthly', SEMI_MONTHLY: 'Semi-monthly', WEEKLY: 'Weekly', ANNUALLY: 'Annual' };

/** Today in Manila as YYYY-MM-DD. */
export const today = () => new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Manila' }).format(new Date());
