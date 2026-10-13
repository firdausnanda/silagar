export async function sendSensusEntry(entry, client, resolveRoute) {
    if (entry.operation === 'update') {
        const data = {
            nama: entry.nama,
            nama_kth: entry.nama_kth ?? null,
            nama_penunjuk: entry.nama_penunjuk ?? null,
            kabupaten_kota: entry.kabupaten_kota ?? null,
            no_hp: entry.no_hp,
            luas_garapan: entry.luas_garapan,
            lama_menggarap: entry.lama_menggarap,
        };
        if (entry.location_updated) {
            for (const field of ['latitude', 'longitude', 'gps_accuracy_m', 'captured_at']) {
                data[field] = entry[field];
            }
        } else if (entry.foto && entry.captured_at) {
            data.captured_at = entry.captured_at;
        }

        const options = {
            headers: { Accept: 'application/json' },
            withXSRFToken: true,
        };
        let response;
        if (entry.foto) {
            const form = new FormData();
            form.append('_method', 'PATCH');
            for (const [field, value] of Object.entries(data)) {
                if (value !== undefined) {
                    form.append(field, value ?? '');
                }
            }
            const photo = new Blob([await entry.foto.arrayBuffer()], { type: entry.foto.type });
            form.append('foto', photo, entry.foto.name);
            response = await client.post(resolveRoute('sensus.update', entry.server_id), form, options);
        } else {
            response = await client.patch(resolveRoute('sensus.update', entry.server_id), data, options);
        }
        return response.data.data;
    }

    const form = new FormData();
    for (const field of [
        'client_uuid', 'nama', 'nama_kth', 'nama_penunjuk', 'kabupaten_kota', 'no_hp', 'luas_garapan', 'lama_menggarap',
        'latitude', 'longitude', 'gps_accuracy_m', 'captured_at',
    ]) {
        if (entry[field] !== null && entry[field] !== undefined) {
            form.append(field, entry[field]);
        }
    }
    const photo = new Blob([await entry.foto.arrayBuffer()], { type: entry.foto.type });
    form.append('foto', photo, entry.foto.name);

    const response = await client.post(resolveRoute('sensus.store'), form, {
        headers: { Accept: 'application/json' },
        withXSRFToken: true,
    });
    return response.data.data;
}
