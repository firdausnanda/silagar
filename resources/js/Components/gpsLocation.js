export function requestFreshGpsPosition(geolocation) {
    return new Promise((resolve, reject) => {
        geolocation.getCurrentPosition(resolve, reject, {
            enableHighAccuracy: true,
            timeout: 60000,
            maximumAge: 0,
        });
    });
}

export function getGpsErrorMessage(error, isOffline) {
    if (error.code === 1) {
        return 'Izin lokasi ditolak. Aktifkan izin lokasi pada browser dan sistem, lalu coba lagi.';
    }
    if (error.code === 2) {
        return isOffline
            ? 'Perangkat tidak dapat menentukan titik baru saat luring. Laptop tanpa penerima GPS biasanya memerlukan jaringan; gunakan ponsel dengan GPS atau penerima GPS eksternal.'
            : 'Perangkat belum dapat menentukan lokasi. Periksa layanan lokasi dan coba di area terbuka.';
    }
    if (error.code === 3) {
        return 'Titik lokasi belum didapat setelah 60 detik. Coba di area terbuka dan pastikan GPS perangkat aktif.';
    }

    return 'Titik lokasi belum didapat. Periksa layanan lokasi perangkat lalu coba lagi.';
}
