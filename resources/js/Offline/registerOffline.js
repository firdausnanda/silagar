let registrationPromise;

export function registerPwa() {
    if (!import.meta.env.PROD || !('serviceWorker' in navigator)) {
        return Promise.resolve(null);
    }

    if (!registrationPromise) {
        registrationPromise = navigator.serviceWorker.register('/service-worker.js', { updateViaCache: 'none' })
            .catch((error) => {
                registrationPromise = null;
                throw error;
            });
    }

    return registrationPromise;
}

function sendWorkerMessage(message) {
    return new Promise((resolve) => {
        const channel = new MessageChannel();
        channel.port1.onmessage = resolve;
        navigator.serviceWorker.controller.postMessage(message, [channel.port2]);
    });
}

async function waitForController() {
    if (navigator.serviceWorker.controller) {
        return;
    }

    await new Promise((resolve) => {
        const timeout = window.setTimeout(resolve, 5000);
        navigator.serviceWorker.addEventListener('controllerchange', () => {
            window.clearTimeout(timeout);
            resolve();
        }, { once: true });
    });
}

export async function clearOfflineAccess() {
    if (!import.meta.env.PROD || !('serviceWorker' in navigator)) {
        return;
    }

    const previousOwner = window.localStorage.getItem('sensus-cache-owner');
    window.localStorage.removeItem('sensus-offline-ready-owner');
    if (!previousOwner) {
        return;
    }

    try {
        await registerPwa();
        await navigator.serviceWorker.ready;
        await waitForController();
        if (!navigator.serviceWorker.controller) {
            throw new Error('Service worker belum mengendalikan halaman.');
        }
        await sendWorkerMessage({ type: 'PURGE_PRIVATE' });
        window.localStorage.removeItem('sensus-cache-owner');
    } catch (error) {
        window.dispatchEvent(new CustomEvent('sensus-offline-error', { detail: error.message }));
    }
}

export async function registerOfflineAccess(ownerId, version) {
    if (!import.meta.env.PROD || !('serviceWorker' in navigator)) {
        return;
    }

    try {
        await registerPwa();
        await navigator.serviceWorker.ready;
        await waitForController();

        if (!navigator.serviceWorker.controller) {
            throw new Error('Service worker belum mengendalikan halaman.');
        }

        const previousOwner = window.localStorage.getItem('sensus-cache-owner');
        if (previousOwner && previousOwner !== String(ownerId)) {
            await sendWorkerMessage({ type: 'PURGE_PRIVATE' });
            window.localStorage.removeItem('sensus-offline-ready-owner');
        }
        window.localStorage.setItem('sensus-cache-owner', String(ownerId));

        if (!navigator.onLine) {
            return;
        }

        const manifestResponse = await fetch('/build/manifest.json', { credentials: 'same-origin', cache: 'no-store' });
        if (!manifestResponse.ok) {
            throw new Error('Daftar aset aplikasi belum tersedia.');
        }
        const manifest = await manifestResponse.json();
        const requiredEntries = ['resources/js/app.jsx', 'resources/js/Pages/Dashboard.jsx', 'resources/js/Pages/InputSensus.jsx'];
        if (!requiredEntries.every((key) => manifest[key])) {
            throw new Error('Aset dashboard atau formulir belum ada di build.');
        }
        const assetUrls = new Set();
        const visited = new Set();
        const collectAssets = (key) => {
            if (visited.has(key) || !manifest[key]) {
                return;
            }
            visited.add(key);
            const entry = manifest[key];
            if (entry.file) {
                assetUrls.add(`/build/${entry.file}`);
            }
            for (const css of entry.css ?? []) {
                assetUrls.add(`/build/${css}`);
            }
            for (const dependency of entry.imports ?? []) {
                collectAssets(dependency);
            }
        };
        requiredEntries.forEach(collectAssets);
        const assetResponses = await Promise.all([...assetUrls].map((url) => fetch(url, { credentials: 'same-origin' })));
        if (!assetResponses.every((response) => response.ok)) {
            throw new Error('Sebagian aset aplikasi belum siap untuk akses luring.');
        }

        const pagePaths = ['/dashboard', '/input-sensus'];
        const responses = await Promise.all(pagePaths.flatMap((path) => [
            fetch(path, { credentials: 'same-origin' }),
            fetch(path, {
                credentials: 'same-origin',
                headers: {
                    'X-Inertia': 'true',
                    'X-Inertia-Version': version,
                    'X-Requested-With': 'XMLHttpRequest',
                },
            }),
        ]));

        if (!responses.every((response, index) => response.ok
            && !new URL(response.url).pathname.includes('/login')
            && response.headers.get('Content-Type')?.includes(index % 2 === 0 ? 'text/html' : 'application/json'))) {
            throw new Error('Halaman sensus belum tersedia untuk akses luring.');
        }

        window.localStorage.setItem('sensus-offline-ready-owner', String(ownerId));
        window.dispatchEvent(new Event('sensus-offline-ready'));
    } catch (error) {
        window.dispatchEvent(new CustomEvent('sensus-offline-error', { detail: error.message }));
    }
}
