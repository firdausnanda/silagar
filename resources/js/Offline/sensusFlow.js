export function newSensusDraft(namaKth = '') {
    return {
        step: 1,
        flow_version: 3,
        client_uuid: null,
        foto: null,
        latitude: null,
        longitude: null,
        gps_accuracy_m: null,
        captured_at: null,
        nama: '',
        nama_kth: namaKth,
        no_hp: '',
        luas_garapan: '',
        lama_menggarap: '',
    };
}

export function restoredSensusStep(saved, isEditing = false) {
    if (!saved || isEditing) {
        return 1;
    }

    if (saved.flow_version === 3) {
        return Math.min(3, Math.max(1, Math.trunc(Number(saved.step)) || 1));
    }

    return saved.flow_version === 2 && saved.step === 2 ? 3 : 1;
}
