import '../css/app.css'
import { createApp, h } from 'vue'
import { createInertiaApp } from '@inertiajs/vue3'
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers'
import { ZiggyVue } from '../../vendor/tightenco/ziggy'
import { installGlobalErrorReporting, vueErrorHandler } from './Utils/errorReporter'

installGlobalErrorReporting()

createInertiaApp({
    title: (title) => `${title} — GameShop`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => h(App, props) })

        app.config.errorHandler = vueErrorHandler

        app.use(plugin).use(ZiggyVue).mount(el)
    },
    progress: {
        color: '#c9a84c',
    },
})