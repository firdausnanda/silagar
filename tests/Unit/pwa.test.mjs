import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { fileURLToPath } from 'node:url';
import { runInNewContext } from 'node:vm';

const publicPath = fileURLToPath(new URL('../../public/', import.meta.url));

function loadWorker(fetchImplementation = async () => { throw new Error('offline'); }) {
    const listeners = new Map();
    const offlinePage = new Response('Tidak ada koneksi', { headers: { 'Content-Type': 'text/html' } });
    const cache = {
        match: async () => undefined,
        put: async () => {},
    };
    const context = {
        URL,
        Request,
        Response,
        Set,
        caches: {
            open: async () => cache,
            match: async (key) => key === '/offline.html' ? offlinePage : undefined,
        },
        fetch: fetchImplementation,
        self: {
            location: { origin: 'https://sensus.test' },
            addEventListener: (type, listener) => listeners.set(type, listener),
        },
    };
    runInNewContext(readFileSync(`${publicPath}service-worker.js`, 'utf8'), context);

    return listeners;
}

function dispatchFetch(listener, path, mode = 'navigate') {
    let responsePromise;
    listener({
        request: {
            url: `https://sensus.test${path}`,
            method: 'GET',
            mode,
            headers: new Headers(),
        },
        respondWith: (response) => { responsePromise = response; },
    });
    return responsePromise;
}

test('manifest provides installable identity and real PNG icons', () => {
    const manifest = JSON.parse(readFileSync(`${publicPath}manifest.webmanifest`, 'utf8'));

    assert.equal(manifest.name, 'SIPINTAR HUT - Sistem Informasi Pendataan dan Inventarisasi Hutan Sosial');
    assert.equal(manifest.short_name, 'SIPINTAR HUT');
    assert.equal(manifest.display, 'standalone');
    assert.equal(manifest.scope, '/');
    assert.ok(manifest.start_url.startsWith('/'));

    for (const size of [192, 512]) {
        const icon = manifest.icons.find((item) => item.sizes === `${size}x${size}` && item.type === 'image/png');
        assert.ok(icon, `Ikon ${size}x${size} harus ada`);
        const image = readFileSync(`${publicPath}${icon.src.replace(/^\//, '')}`);
        assert.equal(image.subarray(0, 8).toString('hex'), '89504e470d0a1a0a');
        assert.equal(image.readUInt32BE(16), size);
        assert.equal(image.readUInt32BE(20), size);
    }
});

test('service worker lets the browser fetch the latest Vite manifest', () => {
    const listener = loadWorker().get('fetch');

    assert.equal(dispatchFetch(listener, '/build/manifest.json', 'same-origin'), undefined);
});

test('uncached navigation shows an offline page when the network is unavailable', async () => {
    const listener = loadWorker().get('fetch');
    const response = await dispatchFetch(listener, '/login');

    assert.equal(response.status, 200);
    assert.match(await response.text(), /Tidak ada koneksi/);
});

test('uncached dashboard navigation also shows the offline page', async () => {
    const listener = loadWorker().get('fetch');
    const response = await dispatchFetch(listener, '/dashboard');

    assert.equal(response.status, 200);
    assert.match(await response.text(), /Tidak ada koneksi/);
});
