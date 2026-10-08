import assert from 'node:assert/strict';
import test from 'node:test';
import { getGpsErrorMessage, requestFreshGpsPosition } from '../../resources/js/Components/gpsLocation.js';

test('GPS request asks for a fresh high accuracy position and allows a cold start', async () => {
    const position = { coords: { latitude: -6.2, longitude: 106.8, accuracy: 12 }, timestamp: Date.now() };
    let options;
    const geolocation = {
        getCurrentPosition: (success, error, requestedOptions) => {
            options = requestedOptions;
            success(position);
        },
    };

    assert.equal(await requestFreshGpsPosition(geolocation), position);
    assert.deepEqual(options, { enableHighAccuracy: true, timeout: 60000, maximumAge: 0 });
});

test('offline position unavailable explains the need for a device GPS', () => {
    const message = getGpsErrorMessage({ code: 2 }, true);

    assert.match(message, /luring/);
    assert.match(message, /GPS/);
    assert.match(message, /laptop/i);
});

test('permission denial is distinguished from a timed out position', () => {
    assert.match(getGpsErrorMessage({ code: 1 }, false), /Izin lokasi ditolak/);
    assert.match(getGpsErrorMessage({ code: 3 }, true), /60 detik/);
});
