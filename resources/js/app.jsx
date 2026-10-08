import '../css/app.css';
import './bootstrap';

import { createInertiaApp, router } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import { clearOfflineAccess, registerOfflineAccess, registerPwa } from './Offline/registerOffline';

const appName = import.meta.env.VITE_APP_NAME || 'SILAGAR';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.jsx`,
            import.meta.glob('./Pages/**/*.jsx'),
        ),
    setup({ el, App, props }) {
        const root = createRoot(el);

        root.render(<App {...props} />);
        registerPwa().catch(() => {});

        const prepareForOwner = (page) => {
            const ownerId = page?.props?.auth?.user?.id;
            if (ownerId) {
                registerOfflineAccess(ownerId, page.version);
            } else {
                clearOfflineAccess();
            }
        };
        prepareForOwner(props.initialPage);
        router.on('navigate', (event) => prepareForOwner(event.detail.page));
    },
    progress: {
        color: '#4B5563',
    },
});
