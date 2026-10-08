const DATABASE_NAME = 'sensus-lahan-v1';
const MAX_CACHED_SERVER_RECORDS = 200;
let databasePromise;

function recentServerRecords(records) {
    const unique = new Map(records.map((record) => [record.client_uuid ?? `server-${record.id}`, record]));
    return [...unique.values()]
        .sort((first, second) => Number(second.id) - Number(first.id))
        .slice(0, MAX_CACHED_SERVER_RECORDS);
}

function openDatabase() {
    if (!databasePromise) {
        databasePromise = new Promise((resolve, reject) => {
            const request = indexedDB.open(DATABASE_NAME, 1);

            request.onupgradeneeded = () => {
                const database = request.result;
                database.createObjectStore('drafts', { keyPath: 'owner_id' });
                const entries = database.createObjectStore('entries', { keyPath: 'client_uuid' });
                entries.createIndex('owner_id', 'owner_id');
                database.createObjectStore('server_records', { keyPath: 'owner_id' });
            };
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
            request.onblocked = () => reject(new Error('Penyimpanan perangkat sedang digunakan oleh tab lain.'));
        }).catch((error) => {
            databasePromise = null;
            throw error;
        });
    }

    return databasePromise;
}

function notifyChange() {
    window.dispatchEvent(new Event('sensus-changed'));
}

async function read(storeName, key) {
    const database = await openDatabase();
    return new Promise((resolve, reject) => {
        const request = database.transaction(storeName).objectStore(storeName).get(key);
        request.onsuccess = () => resolve(request.result ?? null);
        request.onerror = () => reject(request.error);
    });
}

async function write(storeName, method, value) {
    const database = await openDatabase();
    await new Promise((resolve, reject) => {
        const transaction = database.transaction(storeName, 'readwrite');
        transaction.objectStore(storeName)[method](value);
        transaction.oncomplete = resolve;
        transaction.onerror = () => reject(transaction.error);
        transaction.onabort = () => reject(transaction.error);
    });
    notifyChange();
}

export async function getDraft(ownerId) {
    return read('drafts', Number(ownerId));
}

export async function saveDraft(ownerId, draft) {
    return write('drafts', 'put', { ...draft, owner_id: Number(ownerId) });
}

export async function clearDraft(ownerId) {
    return write('drafts', 'delete', Number(ownerId));
}

export async function getEntry(clientUuid) {
    return read('entries', clientUuid);
}

export async function saveEntry(ownerId, entry) {
    return write('entries', 'put', {
        ...entry,
        owner_id: Number(ownerId),
        status: 'pending',
        retryable: true,
        error: null,
    });
}

export async function listEntries(ownerId) {
    const database = await openDatabase();
    return new Promise((resolve, reject) => {
        const request = database.transaction('entries')
            .objectStore('entries')
            .index('owner_id')
            .getAll(Number(ownerId));
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}

export async function updateEntry(clientUuid, changes) {
    const database = await openDatabase();
    await new Promise((resolve, reject) => {
        const transaction = database.transaction('entries', 'readwrite');
        const store = transaction.objectStore('entries');
        const request = store.get(clientUuid);

        request.onsuccess = () => {
            if (!request.result) {
                transaction.abort();
                return;
            }

            store.put({ ...request.result, ...changes });
        };
        transaction.oncomplete = resolve;
        transaction.onerror = () => reject(transaction.error);
        transaction.onabort = () => reject(transaction.error ?? new Error('Data sensus tidak ditemukan.'));
    });
    notifyChange();
}

export async function getCachedServerRecords(ownerId) {
    return recentServerRecords((await read('server_records', Number(ownerId)))?.records ?? []);
}

export async function cacheServerRecords(ownerId, records) {
    const database = await openDatabase();
    await new Promise((resolve, reject) => {
        const transaction = database.transaction('server_records', 'readwrite');
        const store = transaction.objectStore('server_records');
        const request = store.get(Number(ownerId));

        request.onsuccess = () => {
            store.put({
                owner_id: Number(ownerId),
                records: recentServerRecords([...(request.result?.records ?? []), ...records]),
            });
        };
        transaction.oncomplete = resolve;
        transaction.onerror = () => reject(transaction.error);
        transaction.onabort = () => reject(transaction.error);
    });
    notifyChange();
}
