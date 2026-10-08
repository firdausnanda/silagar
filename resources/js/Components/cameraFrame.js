export async function captureCameraFrame(video, canvas = document.createElement('canvas')) {
    if (!video?.videoWidth || !video?.videoHeight) {
        throw new Error('Kamera belum siap. Tunggu pratinjau muncul lalu coba lagi.');
    }

    const scale = Math.min(1, 1600 / Math.max(video.videoWidth, video.videoHeight));
    canvas.width = Math.round(video.videoWidth * scale);
    canvas.height = Math.round(video.videoHeight * scale);
    const context = canvas.getContext('2d');

    if (!context) {
        throw new Error('Foto gagal dibuat. Periksa dukungan kamera browser.');
    }

    context.drawImage(video, 0, 0, canvas.width, canvas.height);

    return new Promise((resolve, reject) => {
        canvas.toBlob((blob) => {
            if (blob) {
                resolve(blob);
            } else {
                reject(new Error('Foto gagal dibuat. Coba jepret ulang.'));
            }
        }, 'image/jpeg', 0.85);
    });
}
