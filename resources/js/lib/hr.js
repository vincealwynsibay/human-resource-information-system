import { reactive } from 'vue';
import { api } from './api';

/** The rows of an HR API list, which answers either a bare array or an object holding one. */
export function listOf(res, key) {
    if (Array.isArray(res)) return res;
    if (key && Array.isArray(res?.[key])) return res[key];
    return Object.values(res ?? {}).find(Array.isArray) ?? [];
}

/** Departments, job titles, employment statuses and leave types, loaded once per page, to show names for ids. */
export function useLookups() {
    const lookups = reactive({ departments: [], jobTitles: [], statuses: [], leaveTypes: [] });
    const sources = { departments: '/department', jobTitles: '/jobtitle', statuses: '/employmentstatus', leaveTypes: '/leavetype' };
    async function loadLookups(which = Object.keys(sources)) {
        await Promise.all(which.map((k) => api('get', sources[k]).then((r) => (lookups[k] = listOf(r))).catch(() => {})));
    }
    const nameOf = (kind, id) => (id == null ? '—' : lookups[kind].find((x) => String(x.id) === String(id))?.name ?? `#${id}`);
    return { lookups, loadLookups, nameOf };
}

export const REQUEST_STATUS = { PENDING: 'bg-warn-soft text-warn', APPROVED: 'bg-accent-soft text-accent', REJECTED: 'bg-danger-soft text-danger' };
