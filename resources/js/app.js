import '../css/app.css';
import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import AppLayout from './layouts/AppLayout.vue';

createInertiaApp({
    title: (title) => (title ? `${title} · hris` : 'hris'),
    resolve: (name) => {
        const pages = import.meta.glob('./pages/**/*.vue', { eager: true });
        const page = pages[`./pages/${name}.vue`];
        // Every page sits in the app shell unless it names its own layout (the login page uses none).
        page.default.layout = page.default.layout === undefined ? AppLayout : page.default.layout;
        return page;
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) }).use(plugin).mount(el);
    },
    progress: { color: '#1d6b4a', showSpinner: false },
});
