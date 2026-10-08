import test from 'node:test';
import assert from 'node:assert/strict';
import { filterExportRecords } from '../../resources/js/Components/sensusExportData.js';

test('export list excludes pending records and filters capture dates in Jakarta', () => {
    const records = [
        { id: 1, nama: 'Siti', status: 'synced', captured_at: '2026-10-08T16:30:00Z' },
        { id: 2, nama: 'Budi', status: 'synced', captured_at: '2026-10-08T17:30:00Z' },
        { id: 3, nama: 'Pending', status: 'pending', captured_at: '2026-10-08T17:30:00Z' },
    ];

    assert.deepEqual(filterExportRecords(records, { search: '', startDate: '2026-10-09', endDate: '2026-10-09' }).map((record) => record.id), [2]);
});

test('export search matches name phone and coordinates', () => {
    const records = [
        { id: 1, nama: 'Siti Aminah', no_hp: '08123', latitude: -7.2, longitude: 112.7, status: 'synced' },
        { id: 2, nama: 'Budi', no_hp: '08999', latitude: -6.1, longitude: 110.2, status: 'synced' },
    ];

    assert.deepEqual(filterExportRecords(records, { search: '  AMINAH ' }).map((record) => record.id), [1]);
    assert.deepEqual(filterExportRecords(records, { search: '08999' }).map((record) => record.id), [2]);
    assert.deepEqual(filterExportRecords(records, { search: '112.7' }).map((record) => record.id), [1]);
});

test('recorder filter offers my records, one other recorder, and all recorders', () => {
    const records = [
        { id: 1, nama: 'A', created_by: 10, status: 'synced' },
        { id: 2, nama: 'B', created_by: 20, status: 'synced' },
        { id: 3, nama: 'C', created_by: 30, status: 'synced' },
    ];

    assert.deepEqual(filterExportRecords(records, { recorderScope: 'mine', currentUserId: 10 }).map((record) => record.id), [1]);
    assert.deepEqual(filterExportRecords(records, { recorderScope: 'user', selectedUserId: 20 }).map((record) => record.id), [2]);
    assert.deepEqual(filterExportRecords(records, { recorderScope: 'all' }).map((record) => record.id), [1, 2, 3]);
});
