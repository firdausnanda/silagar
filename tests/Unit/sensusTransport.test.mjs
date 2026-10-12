import assert from 'node:assert/strict';
import test from 'node:test';
import { sendSensusEntry } from '../../resources/js/Offline/sensusTransport.js';

test('synced bidang edit is sent as PATCH to the existing record without a photo', async () => {
    let request;
    const client = {
        patch: async (url, data) => {
            request = { url, data };
            return { data: { data: { id: 7, nama: data.nama } } };
        },
        post: () => { throw new Error('An edit must not create a second bidang.'); },
    };
    const entry = {
        operation: 'update', server_id: 7, nama: 'Siti Rahma',
        no_hp: null, luas_garapan: '1.25', lama_menggarap: '9', foto: null,
    };

    const result = await sendSensusEntry(entry, client, (name, id) => `/${name}/${id}`);

    assert.equal(request.url, '/sensus.update/7');
    assert.deepEqual(request.data, {
        nama: 'Siti Rahma', no_hp: null, luas_garapan: '1.25', lama_menggarap: '9',
    });
    assert.deepEqual(result, { id: 7, nama: 'Siti Rahma' });
});

test('edited photo and GPS are sent to the existing bidang as multipart PATCH', async () => {
    let request;
    const client = {
        patch: () => { throw new Error('A photo edit must use multipart.'); },
        post: async (url, data) => {
            request = { url, data };
            return { data: { data: { id: 7, latitude: -6.9 } } };
        },
    };
    const entry = {
        operation: 'update', server_id: 7, nama: 'Siti Rahma', no_hp: null,
        luas_garapan: '1.25', lama_menggarap: '9',
        latitude: -6.9, longitude: 107.6, gps_accuracy_m: 3,
        captured_at: '2026-10-12T09:15:00Z', location_updated: true,
        foto: new File(['foto baru'], 'baru.jpg', { type: 'image/jpeg' }),
    };

    const result = await sendSensusEntry(entry, client, (name, id) => `/${name}/${id}`);

    assert.equal(request.url, '/sensus.update/7');
    assert.equal(request.data.get('_method'), 'PATCH');
    assert.equal(request.data.get('no_hp'), '');
    assert.equal(request.data.get('latitude'), '-6.9');
    assert.equal(request.data.get('longitude'), '107.6');
    assert.equal(request.data.get('captured_at'), '2026-10-12T09:15:00Z');
    assert.equal(await request.data.get('foto').text(), 'foto baru');
    assert.deepEqual(result, { id: 7, latitude: -6.9 });
});

test('GPS-only edit uses PATCH without sending the existing photo', async () => {
    let request;
    const client = {
        patch: async (url, data) => {
            request = { url, data };
            return { data: { data: { id: 7 } } };
        },
        post: () => { throw new Error('A GPS-only edit needs no upload.'); },
    };

    await sendSensusEntry({
        operation: 'update', server_id: 7, nama: 'Siti', no_hp: null,
        luas_garapan: '1', lama_menggarap: '2',
        latitude: -6.9, longitude: 107.6, gps_accuracy_m: 3,
        captured_at: '2026-10-12T09:15:00Z', location_updated: true,
        foto: null,
    }, client, (name, id) => `/${name}/${id}`);

    assert.equal(request.url, '/sensus.update/7');
    assert.equal(request.data.latitude, -6.9);
    assert.equal(request.data.longitude, 107.6);
    assert.equal('foto' in request.data, false);
});

test('new bidang still sends the stored photo and UUID as POST', async () => {
    let request;
    const client = {
        patch: () => { throw new Error('A new bidang must be created.'); },
        post: async (url, data) => {
            request = { url, data };
            return { data: { data: { id: 8 } } };
        },
    };
    const entry = {
        operation: 'create', client_uuid: 'abc', nama: 'Budi',
        no_hp: null, luas_garapan: '1', lama_menggarap: '2',
        latitude: -7, longitude: 112, gps_accuracy_m: 5,
        captured_at: '2026-10-08T09:15:00Z',
        foto: new File(['gambar'], 'lahan.jpg', { type: 'image/jpeg' }),
    };
    let photoReads = 0;
    const readPhoto = entry.foto.arrayBuffer.bind(entry.foto);
    entry.foto.arrayBuffer = async () => {
        photoReads += 1;
        return readPhoto();
    };

    const result = await sendSensusEntry(entry, client, (name) => `/${name}`);

    assert.equal(request.url, '/sensus.store');
    assert.equal(request.data.get('client_uuid'), 'abc');
    const uploadedPhoto = request.data.get('foto');
    assert.equal(uploadedPhoto.name, 'lahan.jpg');
    assert.equal(await uploadedPhoto.text(), 'gambar');
    assert.equal(photoReads, 1);
    assert.deepEqual(result, { id: 8 });
});
