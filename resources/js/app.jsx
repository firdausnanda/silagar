import '../css/app.css';
import './bootstrap';

import { createInertiaApp, router } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import { useEffect, useState } from 'react';
import { clearOfflineAccess, registerOfflineAccess, registerPwa } from './Offline/registerOffline';

const appName = import.meta.env.VITE_APP_NAME || 'SIPINTAR HUT';

function ImpersonationBanner({ initialPage }) {
    const [auth, setAuth] = useState(initialPage.props.auth);
    const [leaving, setLeaving] = useState(false);

    useEffect(() => router.on('navigate', (event) => {
        setAuth(event.detail.page.props.auth);
        setLeaving(false);
    }), []);

    if (!auth?.is_impersonating) {
        return null;
    }

    return (
        <div role="status" className="relative z-50 flex flex-wrap items-center justify-center gap-x-4 gap-y-2 bg-amber-100 px-4 py-3 text-center text-sm font-semibold text-amber-950">
            <span>Anda sedang masuk sebagai {auth.user.name}.</span>
            <button
                type="button"
                onClick={() => {
                    setLeaving(true);
                    router.delete(route('impersonation.destroy'), { onFinish: () => setLeaving(false) });
                }}
                disabled={leaving}
                className="min-h-11 rounded-lg border border-amber-900 px-3 font-bold text-amber-950 disabled:opacity-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-950"
            >
                {leaving ? 'Mengembalikan...' : 'Kembali ke admin'}
            </button>
        </div>
    );
}

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.jsx`,
            import.meta.glob('./Pages/**/*.jsx'),
        ),
    setup({ el, App, props }) {
        const root = el.__sensusReactRoot ?? createRoot(el);
        el.__sensusReactRoot = root;

        root.render(<><ImpersonationBanner initialPage={props.initialPage} /><App {...props} /></>);
        registerPwa().catch(() => {});

        const prepareForOwner = (page) => {
            const ownerId = page?.props?.auth?.user?.id;
            if (ownerId && !page?.props?.auth?.is_admin && !page?.props?.auth?.is_impersonating) {
                registerOfflineAccess(ownerId, page.version);
            } else {
                clearOfflineAccess();
            }
        };
        prepareForOwner(props.initialPage);
        const stopPreparingForOwner = router.on('navigate', (event) => prepareForOwner(event.detail.page));

        if (import.meta.hot) {
            import.meta.hot.dispose(() => {
                stopPreparingForOwner();
            });
        }
    },
    progress: {
        color: '#FACC15',
    },
});
