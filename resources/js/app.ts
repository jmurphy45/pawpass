import './bootstrap';

if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {});
    });
}
import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import type { DefineComponent } from 'vue';

import AppAlert from '@/Components/AppAlert.vue';
import AppBadge from '@/Components/AppBadge.vue';
import AppButton from '@/Components/AppButton.vue';
import AppCard from '@/Components/AppCard.vue';
import AppDropdown from '@/Components/AppDropdown.vue';
import AppEmptyState from '@/Components/AppEmptyState.vue';
import AppInput from '@/Components/AppInput.vue';
import AppModal from '@/Components/AppModal.vue';
import AppPageHeader from '@/Components/AppPageHeader.vue';
import AppSelect from '@/Components/AppSelect.vue';
import AppStatCard from '@/Components/AppStatCard.vue';

createInertiaApp({
    title: (title) => title ? `${title} | PawPass` : 'PawPass',
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob<DefineComponent>('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .component('AppAlert', AppAlert)
            .component('AppBadge', AppBadge)
            .component('AppButton', AppButton)
            .component('AppCard', AppCard)
            .component('AppDropdown', AppDropdown)
            .component('AppEmptyState', AppEmptyState)
            .component('AppInput', AppInput)
            .component('AppModal', AppModal)
            .component('AppPageHeader', AppPageHeader)
            .component('AppSelect', AppSelect)
            .component('AppStatCard', AppStatCard)
            .mount(el);
    },
    progress: {
        color: '#4f46e5',
    },
});
