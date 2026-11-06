import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, DefineComponent, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';

const appName = import.meta.env.VITE_APP_NAME || 'CarePlate';

function getPageData() {
    const scriptEl = document.querySelector<HTMLScriptElement>(
        'script[data-page="app"][type="application/json"]',
    );
    if (scriptEl?.textContent) {
        try {
            return JSON.parse(scriptEl.textContent);
        } catch {
            // ignore JSON parse error
        }
    }
    const appEl = document.getElementById('app');
    if (appEl?.dataset.page) {
        try {
            return JSON.parse(appEl.dataset.page);
        } catch {
            // ignore JSON parse error
        }
    }
    return undefined;
}

const pageData = getPageData();

void createInertiaApp({
    page: pageData,
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => {
        const pages = import.meta.glob<DefineComponent>([
            './pages/**/*.vue',
            './Pages/**/*.vue',
        ]);
        return pages[`./pages/${name}.vue`]
            ? resolvePageComponent(`./pages/${name}.vue`, pages)
            : resolvePageComponent(`./Pages/${name}.vue`, pages);
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: '#0b8043',
    },
});
