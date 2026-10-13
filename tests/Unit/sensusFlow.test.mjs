import assert from 'node:assert/strict';
import test from 'node:test';
import { newSensusDraft, restoredSensusStep } from '../../resources/js/Offline/sensusFlow.js';

test('current draft resumes its saved step', () => {
    assert.equal(restoredSensusStep({ flow_version: 3, step: 2 }), 2);
    assert.equal(restoredSensusStep({ flow_version: 3, step: 3 }), 3);
    assert.equal(restoredSensusStep({ flow_version: 3, step: 9 }), 3);
});

test('old photo draft resumes at photo step', () => {
    assert.equal(restoredSensusStep({ flow_version: 2, step: 2 }), 3);
    assert.equal(restoredSensusStep({ flow_version: 2, step: 1 }), 1);
});

test('editing a saved entry starts at Nama KTH', () => {
    assert.equal(restoredSensusStep({ flow_version: 3, step: 3 }, true), 1);
});

test('next bidang keeps Nama KTH and clears the previous bidang data', () => {
    const next = newSensusDraft('KTH Rimba Jaya');

    assert.equal(next.step, 1);
    assert.equal(next.nama_kth, 'KTH Rimba Jaya');
    assert.equal(next.nama_penunjuk, '');
    assert.equal(next.kabupaten_kota, '');
    assert.equal(next.client_uuid, null);
    assert.equal(next.nama, '');
    assert.equal(next.foto, null);
    assert.equal(next.latitude, null);
    assert.equal(next.longitude, null);
});
