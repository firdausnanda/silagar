import test from 'node:test';
import assert from 'node:assert/strict';
import { countEnteredRecords, hasActiveSensusSync, mergeSensusRecords, selectDashboardEntries, syncQueuedEntries } from '../../resources/js/Offline/sensusData.js';

test('dashboard detects an upload already sending before its sync listener mounts', () => {
    assert.equal(hasActiveSensusSync(false, [{ status: 'sending' }]), true);
    assert.equal(hasActiveSensusSync(true, [{ status: 'pending' }]), true);
    assert.equal(hasActiveSensusSync(false, [{ status: 'pending' }, { status: 'synced' }]), false);
});

test('merging local and server records counts one bidang for the same UUID', () => {
    const serverRecords = [{ client_uuid: 'a', nama: 'Siti', luas_garapan: 0.75, captured_at: '2026-10-08T02:00:00Z' }];
    const localEntries = [
        { client_uuid: 'a', nama: 'Siti', luas_garapan: 0.75, status: 'pending' },
        { client_uuid: 'b', nama: 'Budi', luas_garapan: 1.25, status: 'failed', captured_at: '2026-10-08T03:00:00Z' },
    ];

    const result = mergeSensusRecords(serverRecords, localEntries);

    assert.equal(result.records.length, 2);
    assert.equal(result.records.find((record) => record.client_uuid === 'a').status, 'synced');
    assert.equal(result.summary.total, 2);
    assert.equal(result.summary.luas, 2);
    assert.equal(result.summary.pending, 1);
});

test('failed uploads stay queued with the photo and can be retried', async () => {
    const entry = { client_uuid: 'a', foto: new Blob(['foto']), status: 'pending' };
    const states = [];
    const store = {
        list: async () => [entry],
        update: async (uuid, changes) => {
            Object.assign(entry, changes);
            states.push(entry.status);
        },
    };

    await syncQueuedEntries(store, async () => {
        throw new Error('Koneksi terputus');
    });

    assert.deepEqual(states, ['sending', 'failed']);
    assert.equal(entry.foto.size, 4);
    assert.equal(entry.error, 'Koneksi terputus');

    const uploaded = await syncQueuedEntries(store, async () => ({ id: 7, client_uuid: 'a' }));

    assert.equal(uploaded, 1);
    assert.equal(entry.status, 'synced');
    assert.equal(entry.foto, null);
    assert.equal(entry.server_record.id, 7);
});

test('pending edit replaces one server bidang in the dashboard summary', () => {
    const serverRecords = [{
        id: 7, client_uuid: 'a', nama: 'Siti', no_hp: null,
        luas_garapan: 0.75, lama_menggarap: 8, captured_at: '2026-10-08T02:00:00Z',
    }];
    const localEntries = [{
        client_uuid: 'a', server_id: 7, operation: 'update', status: 'pending',
        nama: 'Siti Rahma', no_hp: '08123', luas_garapan: 1.25,
        lama_menggarap: 9, captured_at: '2026-10-08T02:00:00Z',
    }];

    const result = mergeSensusRecords(serverRecords, localEntries);

    assert.equal(result.records.length, 1);
    assert.equal(result.records[0].nama, 'Siti Rahma');
    assert.equal(result.records[0].status, 'pending');
    assert.equal(result.summary.total, 1);
    assert.equal(result.summary.luas, 1.25);
    assert.equal(result.summary.pending, 1);
});

test('a newer server edit wins over an older synced local snapshot', () => {
    const serverRecords = [{
        id: 7, client_uuid: 'a', nama: 'Siti Terbaru', luas_garapan: 2,
        updated_at: '2026-10-08T03:00:00Z',
    }];
    const localEntries = [{
        client_uuid: 'a', status: 'synced',
        server_record: {
            id: 7, client_uuid: 'a', nama: 'Siti Lama', luas_garapan: 1,
            updated_at: '2026-10-08T02:00:00Z',
        },
    }];

    const result = mergeSensusRecords(serverRecords, localEntries);

    assert.equal(result.records[0].nama, 'Siti Terbaru');
    assert.equal(result.summary.luas, 2);
});

test('a just-synced edit remains visible while dashboard props are stale', () => {
    const serverRecords = [{
        id: 7, client_uuid: 'a', nama: 'Siti Lama', luas_garapan: 1,
        updated_at: '2026-10-08T02:00:00Z',
    }];
    const localEntries = [{
        client_uuid: 'a', status: 'synced',
        server_record: {
            id: 7, client_uuid: 'a', nama: 'Siti Baru', luas_garapan: 2,
            updated_at: '2026-10-08T03:00:00Z',
        },
    }];

    const result = mergeSensusRecords(serverRecords, localEntries);

    assert.equal(result.records[0].nama, 'Siti Baru');
    assert.equal(result.summary.luas, 2);
});

test('loaded pages stay in newest id order even when captured times differ', () => {
    const serverRecords = [
        { id: 3, client_uuid: 'newer', captured_at: '2025-01-01T00:00:00Z' },
        { id: 2, client_uuid: 'older', captured_at: '2026-01-01T00:00:00Z' },
    ];

    const result = mergeSensusRecords(serverRecords, []);

    assert.deepEqual(result.records.map((record) => record.id), [3, 2]);
});

test('dashboard retains pending entries without restoring every old synced snapshot', () => {
    const entries = [
        { client_uuid: 'loaded', status: 'synced', server_record: { id: 20 } },
        { client_uuid: 'old', status: 'synced', server_record: { id: 5 } },
        { client_uuid: 'new', status: 'synced', server_record: { id: 31 } },
        { client_uuid: 'pending', status: 'pending' },
    ];

    const selected = selectDashboardEntries(entries, [{ client_uuid: 'loaded' }], 30);

    assert.deepEqual(selected.map((entry) => entry.client_uuid), ['loaded', 'new', 'pending']);
});

test('total entered fields includes unsynced new entries without counting edits twice', () => {
    const entries = [
        { client_uuid: 'baru', status: 'pending' },
        { client_uuid: 'edit', status: 'failed', operation: 'update', server_id: 8 },
        { client_uuid: 'lama', status: 'synced', server_record: { id: 7 } },
        { client_uuid: 'sudah-ada', status: 'pending' },
    ];

    assert.equal(countEnteredRecords(10, entries, [{ client_uuid: 'sudah-ada' }]), 11);
});
