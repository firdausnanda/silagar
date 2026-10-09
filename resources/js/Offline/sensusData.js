export function countEnteredRecords(serverTotal, localEntries, knownServerRecords) {
    const knownUuids = new Set(knownServerRecords.map((record) => record.client_uuid));
    const newEntries = new Set(localEntries
        .filter((entry) => entry.status !== 'synced'
            && entry.operation !== 'update'
            && !entry.server_id
            && !entry.id
            && !entry.server_record
            && !knownUuids.has(entry.client_uuid))
        .map((entry) => entry.client_uuid));

    return Number(serverTotal) + newEntries.size;
}

export function hasActiveSensusSync(syncing, localEntries) {
    return syncing || localEntries.some((entry) => entry.status === 'sending');
}

export function selectDashboardEntries(localEntries, visibleRecords, newestInitialId) {
    const visibleUuids = new Set(visibleRecords.map((record) => record.client_uuid));

    return localEntries.filter((entry) => entry.status !== 'synced'
        || visibleUuids.has(entry.client_uuid)
        || Number(entry.server_record?.id ?? 0) > newestInitialId);
}

export function mergeSensusRecords(serverRecords, localEntries) {
    const recordsByUuid = new Map();

    for (const record of serverRecords) {
        recordsByUuid.set(record.client_uuid ?? `server-${record.id}`, {
            ...record,
            status: 'synced',
        });
    }

    for (const entry of localEntries) {
        const serverRecord = recordsByUuid.get(entry.client_uuid);
        if (entry.status === 'synced' && entry.server_record) {
            const localUpdatedAt = Date.parse(entry.server_record.updated_at ?? 0) || 0;
            const serverUpdatedAt = Date.parse(serverRecord?.updated_at ?? 0) || 0;
            if (!serverRecord || localUpdatedAt >= serverUpdatedAt) {
                recordsByUuid.set(entry.client_uuid, { ...entry.server_record, status: 'synced' });
            }
        } else if (serverRecord && entry.operation !== 'update') {
            continue;
        } else {
            recordsByUuid.set(entry.client_uuid, { ...serverRecord, ...entry });
        }
    }

    const records = [...recordsByUuid.values()].sort((first, second) => {
        if (first.id && second.id) {
            return Number(second.id) - Number(first.id);
        }
        if (first.id || second.id) {
            return first.id ? 1 : -1;
        }
        return Date.parse(second.captured_at ?? 0) - Date.parse(first.captured_at ?? 0);
    });

    return {
        records,
        summary: {
            total: records.length,
            luas: Math.round(records.reduce((total, record) => total + Number(record.luas_garapan || 0), 0) * 100) / 100,
            pending: records.filter((record) => record.status !== 'synced').length,
        },
    };
}

export async function syncQueuedEntries(store, send, { force = false, onlyUuid = null } = {}) {
    const entries = await store.list();
    let uploaded = 0;

    for (const entry of entries) {
        if (entry.status === 'synced' || (onlyUuid && entry.client_uuid !== onlyUuid) || (entry.retryable === false && !force)) {
            continue;
        }

        await store.update(entry.client_uuid, { status: 'sending', error: null });

        try {
            const serverRecord = await send(entry);
            await store.update(entry.client_uuid, {
                status: 'synced',
                error: null,
                foto: null,
                server_record: serverRecord,
                retryable: false,
            });
            uploaded += 1;
        } catch (error) {
            await store.update(entry.client_uuid, {
                status: 'failed',
                error: error.message || 'Pengiriman gagal. Coba lagi.',
                retryable: error.retryable !== false,
            });
        }
    }

    return uploaded;
}
