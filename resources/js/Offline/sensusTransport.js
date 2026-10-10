export async function sendSensusEntry(entry, client, resolveRoute) {
    if (entry.operation === 'update') {
        const response = await client.patch(resolveRoute('sensus.update', entry.server_id), {
            nama: entry.nama,
            no_hp: entry.no_hp,
            luas_garapan: entry.luas_garapan,
            lama_menggarap: entry.lama_menggarap,
        }, {
            headers: { Accept: 'application/json' },
            withXSRFToken: true,
        });
        return response.data.data;
    }

    const form = new FormData();
    for (const field of [
        'client_uuid', 'nama', 'no_hp', 'luas_garapan', 'lama_menggarap',
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
