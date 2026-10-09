import assert from 'node:assert/strict';
import test from 'node:test';
import { isValidClientUuid, repairQueuedCreateEntry, validOrNewClientUuid } from '../../resources/js/Offline/clientUuid.js';

test('keeps an existing valid UUID when a draft is saved again', () => {
    const existing = '29a24968-3e37-4444-a6b4-7a7b2f3ce016';
    const cryptoProvider = { randomUUID: () => { throw new Error('Should not replace a valid UUID.'); } };

    assert.equal(validOrNewClientUuid(existing, cryptoProvider), existing);
});

test('replaces an empty or malformed UUID before a draft is queued', () => {
    const replacement = 'a1bb6027-36d5-4731-af89-b00ce819de02';

    assert.equal(validOrNewClientUuid('', { randomUUID: () => replacement }), replacement);
    assert.equal(validOrNewClientUuid('legacy-id', { randomUUID: () => replacement }), replacement);
    assert.equal(isValidClientUuid(replacement), true);
});

test('creates a version 4 UUID when randomUUID is unavailable', () => {
    const cryptoProvider = {
        getRandomValues(bytes) {
            bytes.fill(0);
            return bytes;
        },
    };

    assert.equal(validOrNewClientUuid(null, cryptoProvider), '00000000-0000-4000-8000-000000000000');
});

test('does not queue data when the browser has no secure random generator', () => {
    assert.throws(() => validOrNewClientUuid('', {}), /Browser tidak dapat membuat ID sensus/);
});

test('repairs a failed queued entry while retaining its photo and form data', () => {
    const photo = new Blob(['foto'], { type: 'image/jpeg' });
    const entry = {
        owner_id: 5, client_uuid: '', nama: 'Siti', foto: photo,
        status: 'failed', retryable: false, error: 'The client uuid field is required.',
    };

    const replacement = repairQueuedCreateEntry(entry, {
        randomUUID: () => 'a1bb6027-36d5-4731-af89-b00ce819de02',
    });

    assert.deepEqual(replacement, {
        ...entry,
        client_uuid: 'a1bb6027-36d5-4731-af89-b00ce819de02',
        status: 'pending', retryable: true, error: null,
    });
    assert.equal(replacement.foto, photo);
    assert.equal(entry.client_uuid, '');
});

test('leaves synced entries and pending edits untouched', () => {
    const invalidId = 'server-7';
    const cryptoProvider = { randomUUID: () => { throw new Error('No UUID needed.'); } };

    assert.equal(repairQueuedCreateEntry({ client_uuid: invalidId, status: 'synced' }, cryptoProvider), null);
    assert.equal(repairQueuedCreateEntry({ client_uuid: invalidId, operation: 'update' }, cryptoProvider), null);
});
