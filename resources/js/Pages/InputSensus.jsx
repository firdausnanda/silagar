import { Head, Link, router, usePage } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';
import SensusPhoto from '../Components/SensusPhoto';
import { captureCameraFrame } from '../Components/cameraFrame';
import { getGpsErrorMessage, requestFreshGpsPosition } from '../Components/gpsLocation';
import { clearDraft, getDraft, getEntry, saveDraft, saveEntry } from '../Offline/sensusStore';
import { syncForOwner } from '../Offline/sensusSync';

const emptyDraft = {
    step: 1,
    client_uuid: null,
    foto: null,
    latitude: null,
    longitude: null,
    gps_accuracy_m: null,
    captured_at: null,
    nama: '',
    no_hp: '',
    luas_garapan: '',
    lama_menggarap: '',
};

export default function InputSensus() {
    const ownerId = usePage().props.auth.user.id;
    const [draft, setDraft] = useState(null);
    const [gpsStatus, setGpsStatus] = useState('idle');
    const [gpsError, setGpsError] = useState('');
    const [formError, setFormError] = useState('');
    const [storageError, setStorageError] = useState('');
    const [processing, setProcessing] = useState(false);
    const [cameraOpen, setCameraOpen] = useState(false);
    const [cameraReady, setCameraReady] = useState(false);
    const [cameraError, setCameraError] = useState('');
    const draftRef = useRef(null);
    const pendingSave = useRef(Promise.resolve());
    const videoRef = useRef(null);
    const cameraStream = useRef(null);
    const gpsRequestId = useRef(0);

    const closeCamera = useCallback(() => {
        setCameraReady(false);
        setCameraOpen(false);
    }, []);

    useEffect(() => {
        let active = true;
        const load = async () => {
            try {
                const editUuid = new URLSearchParams(window.location.search).get('edit');
                let saved = editUuid ? await getEntry(editUuid) : await getDraft(ownerId);
                if (saved && saved.owner_id !== Number(ownerId)) {
                    saved = null;
                }
                if (saved && !editUuid && saved.client_uuid && await getEntry(saved.client_uuid)) {
                    saved = null;
                    await clearDraft(ownerId);
                }
                if (active) {
                    const initial = { ...emptyDraft, ...saved, step: editUuid ? 2 : saved?.step ?? 1 };
                    draftRef.current = initial;
                    setDraft(initial);
                    setGpsStatus(initial.latitude !== null && initial.longitude !== null ? 'ready' : 'idle');
                }
            } catch (error) {
                if (active) {
                    setStorageError('Data di perangkat tidak dapat dibuka. Periksa izin dan ruang penyimpanan browser.');
                    setDraft({ ...emptyDraft });
                    draftRef.current = { ...emptyDraft };
                }
            }
        };
        load();
        return () => { active = false; };
    }, [ownerId]);

    useEffect(() => () => {
        gpsRequestId.current += 1;
    }, []);

    const persist = useCallback((changes) => {
        const next = { ...draftRef.current, ...changes };
        draftRef.current = next;
        setDraft(next);
        pendingSave.current = pendingSave.current.catch(() => {}).then(() => saveDraft(ownerId, next));
        pendingSave.current.then(() => setStorageError('')).catch(() => {
            setStorageError('Perubahan belum tersimpan di perangkat. Periksa ruang penyimpanan browser.');
        });
        return next;
    }, [ownerId]);

    useEffect(() => {
        if (!cameraOpen) {
            return;
        }

        let active = true;
        setCameraReady(false);

        const openCamera = async () => {
            if (!navigator.mediaDevices?.getUserMedia) {
                setCameraError(window.isSecureContext
                    ? 'Kamera tidak tersedia di browser atau perangkat ini.'
                    : 'Kamera memerlukan alamat HTTPS atau localhost.');
                closeCamera();
                return;
            }

            try {
                const stream = await navigator.mediaDevices.getUserMedia({
                    audio: false,
                    video: { facingMode: { ideal: 'environment' } },
                });
                if (!active) {
                    stream.getTracks().forEach((track) => track.stop());
                    return;
                }

                cameraStream.current = stream;
                videoRef.current.srcObject = stream;
                await videoRef.current.play();
                if (active) {
                    setCameraReady(true);
                }
            } catch (error) {
                if (active) {
                    setCameraError(error.name === 'NotAllowedError'
                        ? 'Izin kamera ditolak. Aktifkan izin kamera pada browser lalu coba lagi.'
                        : error.name === 'NotFoundError'
                            ? 'Kamera tidak ditemukan pada perangkat ini.'
                            : 'Kamera tidak dapat dibuka. Tutup aplikasi lain yang memakai kamera lalu coba lagi.');
                    closeCamera();
                }
            }
        };

        openCamera();

        return () => {
            active = false;
            cameraStream.current?.getTracks().forEach((track) => track.stop());
            cameraStream.current = null;
            if (videoRef.current) {
                videoRef.current.srcObject = null;
            }
        };
    }, [cameraOpen, closeCamera]);

    const takeGps = useCallback(async () => {
        const requestId = ++gpsRequestId.current;
        setGpsError('');
        if (!navigator.geolocation) {
            setGpsStatus('error');
            setGpsError(window.isSecureContext
                ? 'Browser atau perangkat ini tidak menyediakan akses lokasi.'
                : 'Akses lokasi memerlukan alamat HTTPS atau localhost.');
            return;
        }

        setGpsStatus('loading');
        try {
            const position = await requestFreshGpsPosition(navigator.geolocation);
            if (requestId !== gpsRequestId.current) {
                return;
            }
            persist({
                latitude: position.coords.latitude,
                longitude: position.coords.longitude,
                gps_accuracy_m: position.coords.accuracy,
                captured_at: new Date(position.timestamp).toISOString(),
            });
            setGpsStatus('ready');
        } catch (error) {
            if (requestId !== gpsRequestId.current) {
                return;
            }
            setGpsStatus('error');
            setGpsError(getGpsErrorMessage(error, !navigator.onLine));
        }
    }, [persist]);

    const takePhoto = async () => {
        try {
            const blob = await captureCameraFrame(videoRef.current);
            if (blob.size > 10 * 1024 * 1024) {
                throw new Error('Foto terlalu besar. Coba jepret ulang.');
            }
            const file = new File([blob], `foto-lahan-${Date.now()}.jpg`, { type: 'image/jpeg' });
            setCameraError('');
            setFormError('');
            persist({ foto: file, latitude: null, longitude: null, gps_accuracy_m: null, captured_at: null });
            closeCamera();
            takeGps();
        } catch (error) {
            setCameraError(error.message);
        }
    };

    const goToStepTwo = async () => {
        if (cameraOpen) {
            return;
        }
        if (!draft.foto || draft.latitude === null || draft.longitude === null) {
            setFormError('Foto dan titik GPS wajib tersedia sebelum melanjutkan.');
            return;
        }
        try {
            persist({ step: 2 });
            await pendingSave.current;
            setFormError('');
        } catch (error) {
            draftRef.current = { ...draftRef.current, step: 1 };
            setDraft(draftRef.current);
            setStorageError('Data belum dapat disimpan di perangkat. Jangan lanjutkan sebelum ruang penyimpanan tersedia.');
        }
    };

    const save = async (event) => {
        event.preventDefault();
        const nama = draft.nama.trim();
        const luas = Number(draft.luas_garapan);
        const lama = Number(draft.lama_menggarap);
        if (!nama || nama.length > 100 || !Number.isFinite(luas) || luas <= 0 || luas > 999999.99
            || !/^\d+(\.\d{1,2})?$/.test(String(draft.luas_garapan))
            || !Number.isInteger(lama) || lama < 0 || lama > 150
            || draft.no_hp.length > 20) {
            setFormError('Periksa nama, luas dalam hektar, lama garap, dan nomor kontak.');
            return;
        }
        if (!draft.foto || draft.latitude === null || draft.longitude === null) {
            setFormError('Foto dan titik GPS belum lengkap. Kembali ke langkah pertama.');
            return;
        }

        setProcessing(true);
        setFormError('');
        try {
            const clientUuid = draft.client_uuid ?? crypto.randomUUID();
            const ready = persist({
                client_uuid: clientUuid,
                nama,
                no_hp: draft.no_hp.trim(),
                luas_garapan: String(luas),
                lama_menggarap: String(lama),
            });
            await pendingSave.current;
            await saveEntry(ownerId, ready);
            try {
                await clearDraft(ownerId);
            } catch (error) {
                setStorageError('Entri tersimpan, tetapi draf lama belum dibersihkan.');
            }
            syncForOwner(ownerId).catch(() => {});
            if (navigator.onLine) {
                router.visit(route('dashboard'));
            } else {
                window.location.assign(route('dashboard'));
            }
        } catch (error) {
            setStorageError('Sensus belum tersimpan. Periksa ruang penyimpanan perangkat dan coba lagi.');
            setProcessing(false);
        }
    };

    if (!draft) {
        return <div className="flex min-h-screen items-center justify-center bg-offwhite text-forest">Membuka draf sensus...</div>;
    }

    return (
        <div className="min-h-screen bg-stone-200 text-stone-900">
            <Head title="Catat Bidang Lahan" />
            <div className="mx-auto flex min-h-screen w-full max-w-md flex-col bg-offwhite shadow-xl">
                <header className="sticky top-0 z-20 bg-forest px-5 py-4 text-white">
                    <div className="flex items-center gap-3">
                        {draft.step === 1 ? (
                            <Link href={route('dashboard')} className="rounded-lg p-2 focus-visible:outline focus-visible:outline-2 focus-visible:outline-white" aria-label="Kembali ke dashboard">
                                <BackIcon />
                            </Link>
                        ) : (
                            <button type="button" onClick={() => persist({ step: 1 })} className="rounded-lg p-2 focus-visible:outline focus-visible:outline-2 focus-visible:outline-white" aria-label="Kembali ke langkah pertama">
                                <BackIcon />
                            </button>
                        )}
                        <div>
                            <p className="text-xs font-semibold text-emerald-100">Langkah {draft.step} dari 2</p>
                            <h1 className="text-lg font-bold">{draft.step === 1 ? 'Foto dan titik lahan' : 'Data penggarap'}</h1>
                        </div>
                    </div>
                </header>

                <main className="flex-1 space-y-4 p-4 pb-28">
                    <div className="rounded-xl bg-white p-4 shadow-sm">
                        <div className="mb-3 flex items-center justify-between text-sm font-semibold">
                            <span>Pencatatan satu bidang</span><span>{draft.step}/2</span>
                        </div>
                        <div className="h-2 overflow-hidden rounded-full bg-stone-200">
                            <div className={`h-full bg-forest ${draft.step === 1 ? 'w-1/2' : 'w-full'}`} />
                        </div>
                        <p className="mt-3 text-sm text-stone-700">Data disimpan di perangkat lebih dulu dan dikirim otomatis saat aplikasi aktif serta koneksi tersedia.</p>
                    </div>

                    {storageError && <p role="alert" className="rounded-lg border border-red-300 bg-red-50 p-3 text-sm font-medium text-red-800">{storageError}</p>}
                    {formError && <p role="alert" className="rounded-lg border border-red-300 bg-red-50 p-3 text-sm font-medium text-red-800">{formError}</p>}

                    {draft.step === 1 ? (
                        <>
                            <section className="space-y-3 rounded-xl bg-white p-4 shadow-sm" aria-labelledby="foto-title">
                                <div>
                                    <h2 id="foto-title" className="font-bold text-forest">Foto bukti lahan</h2>
                                    <p className="text-sm text-stone-600">Jepret foto lahan langsung dari kamera perangkat.</p>
                                </div>
                                {cameraOpen ? (
                                    <div className="overflow-hidden rounded-xl bg-stone-950">
                                        <video ref={videoRef} autoPlay muted playsInline aria-label="Pratinjau kamera lahan" className="aspect-[4/3] w-full object-cover" />
                                        <p role="status" className="px-3 py-2 text-center text-sm text-white">{cameraReady ? 'Kamera siap' : 'Membuka kamera...'}</p>
                                        <div className="flex gap-2 bg-white p-3">
                                            <button type="button" onClick={closeCamera} className="min-h-12 rounded-lg border border-forest px-4 font-semibold text-forest focus-visible:outline focus-visible:outline-2 focus-visible:outline-forest">Batal</button>
                                            <button type="button" onClick={takePhoto} disabled={!cameraReady} className="min-h-12 flex-1 rounded-lg bg-forest px-4 font-semibold text-white disabled:opacity-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-forest">Jepret foto</button>
                                        </div>
                                    </div>
                                ) : (
                                    <button type="button" onClick={() => { setCameraError(''); setCameraReady(false); setCameraOpen(true); }} className="block w-full overflow-hidden rounded-xl border-2 border-dashed border-forest/50 bg-emerald-50 text-forest focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-forest">
                                        {draft.foto ? (
                                            <SensusPhoto file={draft.foto} alt="Pratinjau foto lahan" className="h-52 w-full" />
                                        ) : (
                                            <span className="flex h-44 flex-col items-center justify-center gap-2">
                                                <CameraIcon />
                                                <span className="font-semibold">Ambil foto lahan</span>
                                            </span>
                                        )}
                                        <span className="block bg-white px-3 py-3 text-center text-sm font-semibold">{draft.foto ? 'Ambil ulang foto' : 'Buka kamera'}</span>
                                    </button>
                                )}
                                {cameraError && <p role="alert" className="text-sm font-medium text-red-800">{cameraError}</p>}
                                <p className="text-xs text-stone-600">Foto disimpan sebagai JPG, maksimal 10 MB.</p>
                            </section>

                            <section className="space-y-3 rounded-xl bg-white p-4 shadow-sm" aria-labelledby="gps-title">
                                <div>
                                    <h2 id="gps-title" className="font-bold text-forest">Titik GPS</h2>
                                    <p className="text-sm text-stone-600">Titik diambil dari lokasi perangkat setelah foto dipilih.</p>
                                </div>
                                <div className="rounded-lg bg-emerald-50 p-3 text-sm text-stone-800" aria-live="polite">
                                    {gpsStatus === 'loading' ? 'Mencari titik lokasi baru (hingga 60 detik)...' : gpsStatus === 'ready' ? 'Titik lokasi tersedia' : 'Titik lokasi belum tersedia'}
                                </div>
                                {gpsError && <p role="alert" className="text-sm font-medium text-red-800">{gpsError}</p>}
                                {gpsStatus === 'ready' && (
                                    <div className="grid grid-cols-2 gap-2 text-sm">
                                        <div className="rounded-lg bg-stone-50 p-3"><span className="block text-stone-600">Lintang</span><strong className="break-all">{Number(draft.latitude).toFixed(6)}</strong></div>
                                        <div className="rounded-lg bg-stone-50 p-3"><span className="block text-stone-600">Bujur</span><strong className="break-all">{Number(draft.longitude).toFixed(6)}</strong></div>
                                        <p className="col-span-2 text-stone-700">Akurasi perangkat: ±{Math.round(Number(draft.gps_accuracy_m))} meter</p>
                                    </div>
                                )}
                                <button type="button" onClick={takeGps} disabled={!draft.foto || gpsStatus === 'loading'} className="min-h-12 w-full rounded-lg border border-forest px-4 font-semibold text-forest disabled:cursor-not-allowed disabled:opacity-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-forest">
                                    {gpsStatus === 'ready' ? 'Ambil ulang titik GPS' : 'Coba ambil titik GPS'}
                                </button>
                            </section>
                        </>
                    ) : (
                        <form id="sensus-form" onSubmit={save} className="space-y-4">
                            <section className="rounded-xl bg-white p-4 shadow-sm">
                                <h2 className="font-bold text-forest">Ringkasan lokasi</h2>
                                <div className="mt-3 flex items-center gap-3">
                                    <SensusPhoto file={draft.foto} alt="Foto lahan yang akan disimpan" className="h-16 w-16 shrink-0 rounded-lg" />
                                    <div className="min-w-0 text-sm text-stone-700">
                                        <p className="break-all font-semibold">{Number(draft.latitude).toFixed(6)}, {Number(draft.longitude).toFixed(6)}</p>
                                        <p>Akurasi ±{Math.round(Number(draft.gps_accuracy_m))} meter</p>
                                    </div>
                                </div>
                                <button type="button" onClick={() => persist({ step: 1 })} className="mt-3 text-sm font-semibold text-forest underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-forest">Ubah foto atau GPS</button>
                            </section>

                            <section className="space-y-4 rounded-xl bg-white p-4 shadow-sm" aria-labelledby="identitas-title">
                                <div>
                                    <h2 id="identitas-title" className="font-bold text-forest">Identitas penggarap</h2>
                                    <p className="text-sm text-stone-600">Satu formulir untuk satu bidang lahan.</p>
                                </div>
                                <Field label="Nama lengkap penggarap" id="nama" required>
                                    <input id="nama" type="text" maxLength="100" required value={draft.nama} onChange={(event) => persist({ nama: event.target.value })} className="field-input" placeholder="Nama penggarap" />
                                </Field>
                                <Field label="Nomor HP / WhatsApp (opsional)" id="no_hp">
                                    <input id="no_hp" type="tel" maxLength="20" value={draft.no_hp} onChange={(event) => persist({ no_hp: event.target.value })} className="field-input" placeholder="08..." />
                                </Field>
                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <Field label="Luas garapan (hektar)" id="luas" required>
                                        <input id="luas" type="number" inputMode="decimal" min="0.01" max="999999.99" step="0.01" required value={draft.luas_garapan} onChange={(event) => persist({ luas_garapan: event.target.value })} className="field-input" placeholder="0.75" />
                                    </Field>
                                    <Field label="Lama menggarap (tahun)" id="lama" required>
                                        <input id="lama" type="number" inputMode="numeric" min="0" max="150" step="1" required value={draft.lama_menggarap} onChange={(event) => persist({ lama_menggarap: event.target.value })} className="field-input" placeholder="8" />
                                    </Field>
                                </div>
                            </section>
                        </form>
                    )}
                </main>

                <div className="sticky bottom-0 z-10 border-t border-stone-200 bg-white p-4 shadow-lg">
                    {draft.step === 1 ? (
                        <button type="button" onClick={goToStepTwo} disabled={cameraOpen || !draft.foto || gpsStatus !== 'ready'} className="min-h-12 w-full rounded-xl bg-forest px-4 font-bold text-white disabled:cursor-not-allowed disabled:bg-stone-300 disabled:text-stone-600 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-forest">
                            Lanjut ke data penggarap
                        </button>
                    ) : (
                        <div className="flex gap-3">
                            <button type="button" onClick={() => persist({ step: 1 })} className="min-h-12 rounded-xl border border-forest px-4 font-semibold text-forest focus-visible:outline focus-visible:outline-2 focus-visible:outline-forest">Kembali</button>
                            <button type="submit" form="sensus-form" disabled={processing} className="min-h-12 flex-1 rounded-xl bg-forest px-4 font-bold text-white disabled:opacity-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-forest">
                                {processing ? 'Menyimpan...' : 'Simpan sensus di perangkat'}
                            </button>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}

function Field({ label, id, required = false, children }) {
    return <div className="space-y-1.5"><label htmlFor={id} className="block text-sm font-semibold text-stone-800">{label}{required && <span className="text-red-700"> *</span>}</label>{children}</div>;
}

function BackIcon() {
    return <svg className="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden="true"><path d="m15 18-6-6 6-6" /></svg>;
}

function CameraIcon() {
    return <svg className="h-9 w-9" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" aria-hidden="true"><path d="M3 7h4l2-2h6l2 2h4v12H3z" /><circle cx="12" cy="13" r="3" /></svg>;
}
