import assert from 'node:assert/strict';
import test from 'node:test';
import { captureCameraFrame } from '../../resources/js/Components/cameraFrame.js';

test('captured frame is scaled and encoded as JPEG', async () => {
    let drawn;
    let requestedType;
    const blob = new Blob(['camera image'], { type: 'image/jpeg' });
    const canvas = {
        getContext: () => ({
            drawImage: (...argumentsList) => { drawn = argumentsList; },
        }),
        toBlob: (callback, type) => {
            requestedType = type;
            callback(blob);
        },
    };
    const video = { videoWidth: 2400, videoHeight: 1200 };

    const result = await captureCameraFrame(video, canvas);

    assert.equal(result, blob);
    assert.equal(canvas.width, 1600);
    assert.equal(canvas.height, 800);
    assert.deepEqual(drawn, [video, 0, 0, 1600, 800]);
    assert.equal(requestedType, 'image/jpeg');
});

test('capture rejects a frame before the camera has dimensions', async () => {
    await assert.rejects(
        captureCameraFrame({ videoWidth: 0, videoHeight: 0 }, {}),
        /Kamera belum siap/,
    );
});

test('capture rejects when the browser cannot encode the photo', async () => {
    const canvas = {
        getContext: () => ({ drawImage: () => {} }),
        toBlob: (callback) => callback(null),
    };

    await assert.rejects(
        captureCameraFrame({ videoWidth: 640, videoHeight: 480 }, canvas),
        /Foto gagal dibuat/,
    );
});
