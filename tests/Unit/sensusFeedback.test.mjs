import test from 'node:test';
import assert from 'node:assert/strict';
import { consumeSavedSensusNotice, rememberSavedSensusNotice } from '../../resources/js/Offline/sensusFeedback.js';

function memoryStorage() {
    const values = new Map();

    return {
        getItem: (key) => values.get(key) ?? null,
        setItem: (key, value) => values.set(key, value),
        removeItem: (key) => values.delete(key),
    };
}

test('saved sensus notice is shown once after its local entry exists', () => {
    const storage = memoryStorage();
    const localEntries = [{ owner_id: 7, client_uuid: 'saved-uuid', status: 'pending' }];

    assert.equal(rememberSavedSensusNotice(7, 'saved-uuid', () => storage), true);
    assert.equal(consumeSavedSensusNotice(7, localEntries, () => storage), true);
    assert.equal(consumeSavedSensusNotice(7, localEntries, () => storage), false);
});

test('saved sensus notice stays private to its owner', () => {
    const storage = memoryStorage();
    const localEntries = [{ owner_id: 7, client_uuid: 'saved-uuid' }];

    rememberSavedSensusNotice(7, 'saved-uuid', () => storage);

    assert.equal(consumeSavedSensusNotice(8, localEntries, () => storage), false);
    assert.equal(consumeSavedSensusNotice(7, localEntries, () => storage), true);
});

test('saved sensus notice is discarded when the local entry is missing', () => {
    const storage = memoryStorage();

    rememberSavedSensusNotice(7, 'missing-uuid', () => storage);

    assert.equal(consumeSavedSensusNotice(7, [], () => storage), false);
    assert.equal(consumeSavedSensusNotice(7, [{ owner_id: 7, client_uuid: 'missing-uuid' }], () => storage), false);
});

test('unavailable session storage does not prevent saving sensus', () => {
    const unavailable = () => { throw new Error('Storage denied'); };

    assert.equal(rememberSavedSensusNotice(7, 'saved-uuid', unavailable), false);
    assert.equal(consumeSavedSensusNotice(7, [], unavailable), false);
});
