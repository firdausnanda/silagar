import axios from 'axios';
import { useCallback, useEffect, useState } from 'react';
import { syncQueuedEntries } from './sensusData';
import {
    cacheServerRecords,
    getCachedServerRecords,
    listEntries,
    updateEntry,
} from './sensusStore';
import { sendSensusEntry } from './sensusTransport';

const activeSyncs = new Map();

async function uploadEntry(entry) {
    try {
        return await sendSensusEntry(entry, axios, route);
    } catch (error) {
        const status = error.response?.status;
        const message = status === 401 || status === 419
            ? 'Sesi berakhir. Masuk kembali untuk mengirim data.'
            : status === 422
                ? Object.values(error.response.data.errors ?? {}).flat()[0] ?? 'Data ditolak server. Periksa kembali isian.'
                : status === 404
                    ? 'Bidang tidak ditemukan pada akun ini. Periksa data sebelum mengirim ulang.'
                : status === 409
                    ? 'ID sensus bentrok. Periksa data ini sebelum mengirim ulang.'
                    : 'Pengiriman belum berhasil. Data tetap tersimpan di perangkat.';
        const uploadError = new Error(message);
        uploadError.retryable = ![401, 404, 409, 419, 422].includes(status);
        throw uploadError;
    }
}

export function syncForOwner(ownerId, options = {}) {
    if (!navigator.onLine) {
        return Promise.resolve();
    }

    const key = Number(ownerId);
    if (activeSyncs.has(key)) {
        return activeSyncs.get(key);
    }

    window.dispatchEvent(new Event('sensus-sync-start'));
    let uploaded = 0;
    const task = syncQueuedEntries({
        list: () => listEntries(key),
        update: updateEntry,
    }, uploadEntry, options).then((count) => {
        uploaded = count;
        return count;
    }).finally(() => {
        activeSyncs.delete(key);
        window.dispatchEvent(new CustomEvent('sensus-sync-end', { detail: { uploaded } }));
    });
    activeSyncs.set(key, task);
    return task;
}

export function useSensusRecords(ownerId, serverRecords = []) {
    const [localEntries, setLocalEntries] = useState([]);
    const [cachedRecords, setCachedRecords] = useState(serverRecords);
    const [storageError, setStorageError] = useState(null);
    const [syncing, setSyncing] = useState(false);

    const refresh = useCallback(async () => {
        try {
            const [entries, records] = await Promise.all([
                listEntries(ownerId),
                getCachedServerRecords(ownerId),
            ]);
            setLocalEntries(entries);
            setCachedRecords(records);
            setStorageError(null);
        } catch (error) {
            setStorageError('Penyimpanan perangkat tidak dapat dibaca. Periksa izin dan ruang penyimpanan browser.');
        }
    }, [ownerId]);

    const attemptSync = useCallback(async (force = false) => {
        if (!navigator.onLine) {
            return;
        }
        try {
            await syncForOwner(ownerId, { force });
            await refresh();
        } catch (error) {
            setStorageError('Antrean tidak dapat diakses. Data yang sudah tersimpan belum dihapus.');
        }
    }, [ownerId, refresh]);

    useEffect(() => {
        const initialize = async () => {
            await refresh();
            if (navigator.onLine) {
                try {
                    await cacheServerRecords(ownerId, serverRecords);
                    await refresh();
                } catch (error) {
                    setStorageError('Data server belum dapat disimpan untuk akses luring.');
                }
                attemptSync(true);
            }
        };
        initialize();

        const onOnline = () => attemptSync();
        const onVisible = () => {
            if (document.visibilityState === 'visible') {
                attemptSync();
            }
        };
        const onStart = () => setSyncing(true);
        const onEnd = () => setSyncing(false);
        const timer = window.setInterval(() => attemptSync(), 60000);
        window.addEventListener('online', onOnline);
        window.addEventListener('sensus-changed', refresh);
        window.addEventListener('sensus-sync-start', onStart);
        window.addEventListener('sensus-sync-end', onEnd);
        document.addEventListener('visibilitychange', onVisible);

        return () => {
            window.clearInterval(timer);
            window.removeEventListener('online', onOnline);
            window.removeEventListener('sensus-changed', refresh);
            window.removeEventListener('sensus-sync-start', onStart);
            window.removeEventListener('sensus-sync-end', onEnd);
            document.removeEventListener('visibilitychange', onVisible);
        };
    }, [ownerId, refresh, attemptSync]);

    return { localEntries, cachedRecords, storageError, syncing, attemptSync, refresh };
}
