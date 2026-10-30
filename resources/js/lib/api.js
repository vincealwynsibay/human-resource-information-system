import axios from 'axios';

// Same-origin calls to the Laravel API. Sanctum authenticates them with the session cookie; the XSRF-TOKEN cookie
// it sets is sent back as a header by axios on every write.
export const http = axios.create({ baseURL: '/api', withCredentials: true, withXSRFToken: true, headers: { Accept: 'application/json' } });

let csrfReady = null;
export const ensureCsrf = () => (csrfReady ??= axios.get('/sanctum/csrf-cookie', { withCredentials: true }));

/** The message to show for a failed request: the API's own words, the first validation error, or a fallback. */
export function errorMessage(e) {
    const d = e?.response?.data;
    if (d?.errors && typeof d.errors === 'object' && !Array.isArray(d.errors)) {
        const first = Object.values(d.errors)[0];
        if (Array.isArray(first) && first[0]) return first[0];
    }
    if (d?.message) return Array.isArray(d.message) ? d.message[0] : d.message;
    if (d?.error) return d.error;
    if (e?.response?.status === 419) return 'Your session expired. Sign in again.';
    return e?.message ?? 'Something went wrong.';
}

export async function api(method, url, data, config = {}) {
    if (method !== 'get') await ensureCsrf();
    const res = await http.request({ method, url, data, ...config });
    return res.data;
}

/** Download a file the API answers with Content-Disposition, under the name it gives. */
export async function download(url) {
    const res = await http.get(url, { responseType: 'blob' });
    const name = /filename=([^;]+)/.exec(res.headers['content-disposition'] ?? '')?.[1] ?? 'download';
    const link = document.createElement('a');
    link.href = URL.createObjectURL(res.data);
    link.download = name.replace(/"/g, '');
    link.click();
    URL.revokeObjectURL(link.href);
}

/** A failed blob download carries its JSON error as a blob: read it back into a message. */
export async function blobErrorMessage(e) {
    const blob = e?.response?.data;
    if (blob instanceof Blob) {
        try {
            return errorMessage({ response: { ...e.response, data: JSON.parse(await blob.text()) } });
        } catch {
            /* not JSON */
        }
    }
    return errorMessage(e);
}
