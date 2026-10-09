import axios from 'axios';
import { Head, Link, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';
import BusyIndicator from '../Components/BusyIndicator';
import Modal from '../Components/Modal';
import SensusExportModal from '../Components/SensusExportModal';
import SensusPhoto from '../Components/SensusPhoto';
import { countEnteredRecords, hasActiveSensusSync, mergeSensusRecords, selectDashboardEntries } from '../Offline/sensusData';
import { consumeSavedSensusNotice } from '../Offline/sensusFeedback';
import { cacheServerRecords, listEntries, reconcileCompleteServerRecords, saveEntry } from '../Offline/sensusStore';
import { syncForOwner, useSensusRecords } from '../Offline/sensusSync';

const numberFormat = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 });
const dateFormat = new Intl.DateTimeFormat('id-ID', {
    timeZone: 'Asia/Jakarta',
    day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit',
});

function formatDate(value) {
    return value ? dateFormat.format(new Date(value)) + ' WIB' : 'Waktu belum tersedia';
}

function statusLabel(status) {
    return {
        pending: 'Menunggu kirim',
        sending: 'Mengirim',
        failed: 'Perlu perhatian',
        synced: 'Tersinkron',
    }[status] ?? 'Menunggu kirim';
}

export default function Dashboard({ records: initialRecords = [], next_cursor: initialNextCursor = null, summary: initialSummary = { total: 0, luas: 0 } }) {
    const ownerId = usePage().props.auth.user.id;
    const userName = usePage().props.auth.user.name;
    const isImpersonating = usePage().props.auth.is_impersonating;
    const [filter, setFilter] = useState('semua');
    const [search, setSearch] = useState('');
    const [selectedUuid, setSelectedUuid] = useState(null);
    const [editRecord, setEditRecord] = useState(null);
    const [editData, setEditData] = useState({ nama: '', no_hp: '', luas_garapan: '', lama_menggarap: '' });
    const [editError, setEditError] = useState('');
    const [editSaving, setEditSaving] = useState(false);
    const [exportOpen, setExportOpen] = useState(false);
    const [online, setOnline] = useState(navigator.onLine);
    const [offlineReady, setOfflineReady] = useState(
        window.localStorage.getItem('sensus-offline-ready-owner') === String(ownerId),
    );
    const [offlineError, setOfflineError] = useState('');
    const [serverRecords, setServerRecords] = useState(initialRecords);
    const [serverSummary, setServerSummary] = useState(initialSummary);
    const [nextCursor, setNextCursor] = useState(initialNextCursor);
    const [activeSearch, setActiveSearch] = useState('');
    const [searchLoading, setSearchLoading] = useState(false);
    const [searchRetry, setSearchRetry] = useState(0);
    const [loadingMore, setLoadingMore] = useState(false);
    const [retryingUuid, setRetryingUuid] = useState(null);
    const [pageError, setPageError] = useState('');
    const [saveNotice, setSaveNotice] = useState('');
    const [offlineVisibleCount, setOfflineVisibleCount] = useState(30);
    const dialogRef = useRef(null);
    const requestGeneration = useRef(0);
    const baseRecords = useRef(initialRecords);
    const baseNextCursor = useRef(initialNextCursor);
    const baseSummary = useRef(initialSummary);
    const currentSearch = useRef(search);
    currentSearch.current = search;
    const { localEntries, cachedRecords, storageError, syncing, attemptSync, refresh } = useSensusRecords(ownerId);

    useEffect(() => {
        let active = true;

        listEntries(ownerId).then((entries) => {
            if (active && consumeSavedSensusNotice(ownerId, entries)) {
                setSaveNotice((current) => current || 'Sensus tersimpan di perangkat. Status pengiriman terlihat di daftar bidang.');
            }
        }).catch(() => {});

        return () => { active = false; };
    }, [ownerId]);

    useEffect(() => {
        baseRecords.current = initialRecords;
        baseNextCursor.current = initialNextCursor;
        setServerRecords(initialRecords);
        setNextCursor(initialNextCursor);
    }, [initialRecords, initialNextCursor]);

    useEffect(() => {
        baseSummary.current = initialSummary;
        setServerSummary(initialSummary);
    }, [initialSummary]);

    useEffect(() => {
        if (!online) {
            return;
        }

        const controller = new AbortController();
        axios.get(route('dashboard.records'), {
            signal: controller.signal,
            headers: { Accept: 'application/json' },
        }).then(({ data }) => {
            baseRecords.current = data.records;
            baseNextCursor.current = data.next_cursor;
            baseSummary.current = data.summary;
            if (!currentSearch.current.trim()) {
                setServerRecords(data.records);
                setNextCursor(data.next_cursor);
                setServerSummary(data.summary);
            }
            const saveRecords = data.summary.total <= data.records.length
                ? reconcileCompleteServerRecords(ownerId, data.records)
                : cacheServerRecords(ownerId, data.records);
            saveRecords.then(refresh).catch(() => {
                setOfflineError('Daftar bidang terbaru belum tersimpan untuk akses luring.');
            }).finally(() => attemptSync(true));
        }).catch((error) => {
            if (error.code !== 'ERR_CANCELED') {
                setPageError('Daftar bidang dari server belum dapat diverifikasi. Coba muat ulang saat koneksi stabil.');
            }
        });

        return () => controller.abort();
    }, [online, ownerId, refresh, attemptSync]);

    useEffect(() => {
        const updateSummary = (event) => {
            if (!event.detail?.uploaded || !navigator.onLine) {
                return;
            }
            const term = currentSearch.current.trim();
            const generation = requestGeneration.current;
            axios.get(route('dashboard.records'), {
                params: term ? { search: term } : {},
                headers: { Accept: 'application/json' },
            }).then(({ data }) => {
                if (currentSearch.current.trim() !== term || requestGeneration.current !== generation) {
                    return;
                }
                setServerRecords(data.records);
                setNextCursor(data.next_cursor);
                setServerSummary(data.summary);
                setActiveSearch(term);
                if (!term) {
                    baseRecords.current = data.records;
                    baseNextCursor.current = data.next_cursor;
                    baseSummary.current = data.summary;
                    const saveRecords = data.summary.total <= data.records.length
                        ? reconcileCompleteServerRecords(ownerId, data.records)
                        : cacheServerRecords(ownerId, data.records);
                    saveRecords.then(refresh).catch(() => {
                        setOfflineError('Bidang terbaru belum tersimpan untuk akses luring.');
                    });
                }
            })
                .catch(() => {});
        };
        window.addEventListener('sensus-sync-end', updateSummary);
        return () => window.removeEventListener('sensus-sync-end', updateSummary);
    }, [ownerId, refresh]);

    useEffect(() => {
        const generation = ++requestGeneration.current;
        const term = search.trim();

        if (!online) {
            setSearchLoading(false);
            setPageError('');
            return;
        }

        if (!term) {
            setServerRecords(baseRecords.current);
            setNextCursor(baseNextCursor.current);
            setServerSummary(baseSummary.current);
            setActiveSearch('');
            setSearchLoading(false);
            setPageError('');
            return;
        }

        const controller = new AbortController();
        setSearchLoading(true);
        setPageError('');
        const timer = window.setTimeout(() => {
            axios.get(route('dashboard.records'), {
                params: { search: term },
                signal: controller.signal,
                headers: { Accept: 'application/json' },
            }).then(({ data }) => {
                if (requestGeneration.current === generation) {
                    setServerRecords(data.records);
                    setNextCursor(data.next_cursor);
                    setServerSummary(data.summary);
                    setActiveSearch(term);
                }
            }).catch((error) => {
                if (error.code !== 'ERR_CANCELED' && requestGeneration.current === generation) {
                    setPageError('Pencarian server belum berhasil. Coba lagi saat koneksi stabil.');
                }
            }).finally(() => {
                if (requestGeneration.current === generation) {
                    setSearchLoading(false);
                }
            });
        }, 300);

        return () => {
            window.clearTimeout(timer);
            controller.abort();
        };
    }, [search, searchRetry, online, initialRecords, initialNextCursor]);

    useEffect(() => {
        const onOnline = () => setOnline(true);
        const onOffline = () => setOnline(false);
        const onReady = () => { setOfflineReady(true); setOfflineError(''); };
        const onOfflineError = (event) => setOfflineError(event.detail);
        window.addEventListener('online', onOnline);
        window.addEventListener('offline', onOffline);
        window.addEventListener('sensus-offline-ready', onReady);
        window.addEventListener('sensus-offline-error', onOfflineError);
        return () => {
            window.removeEventListener('online', onOnline);
            window.removeEventListener('offline', onOffline);
            window.removeEventListener('sensus-offline-ready', onReady);
            window.removeEventListener('sensus-offline-error', onOfflineError);
        };
    }, []);

    const offlineRecords = useMemo(() => {
        const byUuid = new Map([...cachedRecords, ...serverRecords]
            .map((record) => [record.client_uuid ?? `server-${record.id}`, record]));
        return [...byUuid.values()].sort((first, second) => Number(second.id) - Number(first.id));
    }, [cachedRecords, serverRecords]);

    const { records, summary: loadedSummary } = useMemo(() => {
        const visibleServerRecords = online
            ? serverRecords
            : search.trim() ? offlineRecords : offlineRecords.slice(0, offlineVisibleCount);
        const newestInitialId = Math.max(0, ...baseRecords.current.map((record) => Number(record.id)));
        const visibleEntries = selectDashboardEntries(localEntries, visibleServerRecords, newestInitialId, online);

        return mergeSensusRecords(visibleServerRecords, visibleEntries);
    }, [online, serverRecords, offlineRecords, offlineVisibleCount, localEntries, search, initialRecords]);
    const summary = {
        ...serverSummary,
        total: countEnteredRecords(serverSummary.total, localEntries, offlineRecords),
        pending: loadedSummary.pending,
    };
    const isSending = hasActiveSensusSync(syncing, localEntries);

    const loadMore = async () => {
        if (!online) {
            setOfflineVisibleCount((count) => count + 30);
            return;
        }
        if (!nextCursor || loadingMore || searchLoading || activeSearch !== search.trim()) {
            return;
        }

        const generation = requestGeneration.current;
        setLoadingMore(true);
        setPageError('');

        try {
            const { data } = await axios.get(route('dashboard.records'), {
                params: { cursor: nextCursor, search: activeSearch },
                headers: { Accept: 'application/json' },
            });
            if (requestGeneration.current !== generation) {
                return;
            }
            setServerRecords((current) => {
                const knownIds = new Set(current.map((record) => record.id));
                return [...current, ...data.records.filter((record) => !knownIds.has(record.id))];
            });
            setNextCursor(data.next_cursor);
            setServerSummary(data.summary);
            if (!activeSearch) {
                cacheServerRecords(ownerId, data.records).catch(() => {
                    setOfflineError('Bidang tambahan belum tersimpan untuk akses luring.');
                });
            }
        } catch (error) {
            if (requestGeneration.current === generation) {
                setPageError('Data berikutnya belum dapat dimuat. Coba lagi.');
            }
        } finally {
            setLoadingMore(false);
        }
    };

    const filteredRecords = records.filter((record) => {
        const matchesFilter = filter === 'semua' || (filter === 'pending' ? record.status !== 'synced' : record.status === 'synced');
        const query = search.trim().toLowerCase();
        const matchesSearch = !query || [record.nama, record.no_hp, record.latitude, record.longitude]
            .some((value) => String(value ?? '').toLowerCase().includes(query));
        return matchesFilter && matchesSearch;
    });
    const selected = records.find((record) => (record.client_uuid ?? `server-${record.id}`) === selectedUuid);
    const hasMoreRecords = filter !== 'pending' && (online
        ? nextCursor !== null
        : !search.trim() && offlineRecords.length > offlineVisibleCount);

    useEffect(() => {
        if (selected && !dialogRef.current?.open) {
            dialogRef.current?.showModal();
        } else if (!selected && dialogRef.current?.open) {
            dialogRef.current.close();
        }
    }, [selected]);

    const retry = async (clientUuid) => {
        if (retryingUuid !== null || isSending) {
            return;
        }

        setRetryingUuid(clientUuid);
        try {
            await syncForOwner(ownerId, { onlyUuid: clientUuid, force: true });
            await refresh();
        } catch (error) {
            await refresh();
        } finally {
            setRetryingUuid(null);
        }
    };

    const openEdit = (record) => {
        setEditRecord(record);
        setEditData({
            nama: record.nama ?? '',
            no_hp: record.no_hp ?? '',
            luas_garapan: String(record.luas_garapan ?? ''),
            lama_menggarap: String(record.lama_menggarap ?? ''),
        });
        setEditError('');
    };

    const saveEdit = async (event) => {
        event.preventDefault();
        const nama = editData.nama.trim();
        const noHp = editData.no_hp.trim();
        const luas = Number(editData.luas_garapan);
        const lama = Number(editData.lama_menggarap);
        if (!nama || nama.length > 100 || noHp.length > 20
            || !/^\d+(\.\d{1,2})?$/.test(editData.luas_garapan)
            || !Number.isFinite(luas) || luas <= 0 || luas > 999999.99
            || !Number.isInteger(lama) || lama < 0 || lama > 150) {
            setEditError('Periksa nama, nomor HP, luas garapan, dan lama menggarap.');
            return;
        }

        setEditSaving(true);
        setEditError('');
        try {
            const serverId = editRecord.server_id ?? editRecord.id ?? null;
            await saveEntry(ownerId, {
                ...editRecord,
                client_uuid: editRecord.client_uuid ?? `server-${serverId}`,
                operation: serverId === null ? 'create' : 'update',
                server_id: serverId,
                nama,
                no_hp: noHp || null,
                luas_garapan: String(luas),
                lama_menggarap: String(lama),
            });
            setEditRecord(null);
            setSaveNotice('Perubahan tersimpan di perangkat. Status pengiriman terlihat di daftar bidang.');
            syncForOwner(ownerId).catch(() => {});
        } catch (error) {
            setEditError('Perubahan belum tersimpan di perangkat. Periksa ruang penyimpanan dan coba lagi.');
        } finally {
            setEditSaving(false);
        }
    };

    return (
        <div className="min-h-screen bg-surface text-on-surface">
            <Head title="Sensus Lahan" />
            <header className="bg-primary-container px-4 py-5 text-white">
                <div className="mx-auto flex max-w-3xl items-center justify-between gap-4">
                    <div>
                        <p className="text-sm text-emerald-100">SIPINTAR HUT · Sensus Lahan</p>
                        <h1 className="text-xl font-bold">Data bidang saya</h1>
                        <p className="mt-1 text-sm text-emerald-100">Petugas: {userName}</p>
                    </div>
                    {online && !isImpersonating && <Link href={route('profile.edit')} className="rounded-lg border border-emerald-200 px-3 py-2 text-sm font-semibold text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-white">Profil</Link>}
                </div>
            </header>

            <main className="mx-auto max-w-3xl space-y-5 px-4 py-5 pb-12">
                {saveNotice && (
                    <div role="status" className="flex items-start justify-between gap-3 rounded-xl border border-emerald-300 bg-emerald-50 p-4 text-sm font-medium text-forest">
                        <p>{saveNotice}</p>
                        <button type="button" onClick={() => setSaveNotice('')} aria-label="Tutup pemberitahuan simpan" className="min-h-8 min-w-8 rounded-lg text-xl leading-none focus-visible:outline focus-visible:outline-2 focus-visible:outline-forest">×</button>
                    </div>
                )}
                <div className="flex flex-wrap items-center gap-2 text-sm">
                    <span className={`rounded-lg px-3 py-1.5 font-semibold ${online ? 'bg-emerald-100 text-emerald-900' : 'bg-amber-100 text-amber-950'}`}>
                        {online ? 'Koneksi perangkat aktif' : 'Luring'}
                    </span>
                    {import.meta.env.PROD && (
                        <span className="rounded-lg bg-white px-3 py-1.5 text-stone-700">
                            {offlineReady ? 'Aplikasi siap dibuka luring' : 'Menyiapkan akses luring'}
                        </span>
                    )}
                </div>

                {(storageError || offlineError) && (
                    <p role="alert" className="rounded-lg border border-red-300 bg-red-50 p-3 text-sm font-medium text-red-800">
                        {storageError || `Akses luring belum siap: ${offlineError}`}
                    </p>
                )}

                <section className="rounded-xl bg-white p-4 shadow-sm" aria-label="Status sinkronisasi">
                    <div className="flex items-start justify-between gap-4">
                        <div>
                            <h2 className="font-bold text-forest">{summary.pending} entri menunggu sinkronisasi</h2>
                            <p role="status" className="mt-1 flex items-center gap-2 text-sm text-stone-700">
                                <BusyIndicator active={isSending} />
                                {isSending ? 'Mengirim data ke server...' : online
                                    ? summary.pending === 0 ? 'Tidak ada entri yang menunggu pengiriman.' : 'Pengiriman berjalan otomatis selama aplikasi aktif.'
                                    : 'Data tetap berada di perangkat sampai koneksi kembali.'}
                            </p>
                        </div>
                        {summary.pending > 0 && online && (
                            <button type="button" onClick={() => attemptSync(true)} disabled={isSending} className="min-h-11 shrink-0 rounded-lg bg-secondary px-3 text-sm font-bold text-white disabled:opacity-60 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-secondary">{isSending ? 'Mengirim...' : 'Coba kirim'}</button>
                        )}
                    </div>
                </section>

                <section className="grid grid-cols-2 gap-3" aria-label="Ringkasan sensus">
                    <div className="rounded-xl bg-white p-4 shadow-sm">
                        <p className="text-sm text-stone-700">Total bidang saya</p>
                        <p className="mt-2 text-3xl font-bold text-forest">{summary.total}</p>
                    </div>
                    <div className="rounded-xl bg-white p-4 shadow-sm">
                        <p className="text-sm text-stone-700">Luas di server</p>
                        <p className="mt-2 text-3xl font-bold text-forest">{numberFormat.format(summary.luas)} <span className="text-base font-medium">Ha</span></p>
                    </div>
                </section>

                <Link href={route('input-sensus')} className="flex min-h-14 items-center justify-between rounded-xl bg-forest px-5 font-bold text-white shadow-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-forest">
                    <span>Catat bidang baru</span><span aria-hidden="true" className="text-2xl">+</span>
                </Link>

                <section className="flex flex-col gap-3 rounded-xl border border-stone-300 bg-white p-4 sm:flex-row sm:items-center sm:justify-between" aria-label="Ekspor data">
                    <div>
                        <h2 className="font-bold text-forest">Butuh data di Excel?</h2>
                        <p className="mt-1 text-sm text-stone-700">Pilih bidang dan kolom yang ingin diunduh.</p>
                        {!online && <p className="mt-1 text-sm font-semibold text-amber-950">Ekspor tersedia saat koneksi aktif.</p>}
                    </div>
                    <button type="button" onClick={() => setExportOpen(true)} disabled={!online} className="min-h-12 shrink-0 rounded-lg border border-forest px-4 font-bold text-forest disabled:opacity-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-forest">Ekspor Excel</button>
                </section>

                <section className="space-y-3" aria-labelledby="daftar-title">
                    <div className="flex flex-wrap items-baseline justify-between gap-2">
                        <h2 id="daftar-title" className="text-lg font-bold text-forest">Daftar bidang</h2>
                        <span className="text-sm text-stone-700">
                            {filter === 'pending' ? `${filteredRecords.length} belum sinkron` : `${filteredRecords.length} dimuat`}
                            {' · '}Total bidang Anda: {numberFormat.format(summary.total)}
                        </span>
                    </div>
                    <label htmlFor="cari" className="sr-only">Cari nama, nomor HP, atau koordinat</label>
                    <input id="cari" type="search" maxLength="100" value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Cari nama, nomor HP, atau koordinat" className="min-h-12 w-full rounded-lg border-stone-300 bg-white text-base focus:border-forest focus:ring-forest" />
                    {searchLoading && <p role="status" className="flex items-center gap-2 text-sm text-stone-700"><BusyIndicator active={searchLoading} />Mencari di server...</p>}
                    {!online && search.trim() && <p className="text-sm text-stone-700">Pencarian luring hanya mencakup bidang yang tersimpan di perangkat ini.</p>}
                    {pageError && (
                        <div role="alert" className="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-800">
                            <p>{pageError}</p>
                            {online && search.trim() && activeSearch !== search.trim() && <button type="button" onClick={() => setSearchRetry((count) => count + 1)} className="min-h-10 rounded-lg border border-red-500 px-3 font-semibold focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-700">Ulangi pencarian</button>}
                        </div>
                    )}
                    <div className="flex flex-wrap gap-2" aria-label="Filter status">
                        {[
                            ['semua', 'Semua'],
                            ['pending', `Belum sinkron (${summary.pending})`],
                            ['synced', `Tersinkron (${serverSummary.total})`],
                        ].map(([value, label]) => (
                            <button key={value} type="button" onClick={() => setFilter(value)} aria-pressed={filter === value} className={`min-h-10 rounded-lg px-3 text-sm font-semibold focus-visible:outline focus-visible:outline-2 focus-visible:outline-forest ${filter === value ? 'bg-forest text-white' : 'bg-white text-forest'}`}>{label}</button>
                        ))}
                    </div>

                    {!searchLoading && filteredRecords.length === 0 ? (
                        <div className="rounded-xl border border-dashed border-stone-300 bg-white p-8 text-center">
                            <p className="font-semibold text-forest">{records.length === 0 ? 'Belum ada bidang dicatat' : 'Tidak ada bidang yang cocok'}</p>
                            <p className="mt-1 text-sm text-stone-700">{records.length === 0 ? 'Gunakan tombol Catat bidang baru untuk memulai.' : 'Coba kata lain atau pilih filter Semua.'}</p>
                        </div>
                    ) : filteredRecords.map((record) => (
                        <article key={record.client_uuid ?? `server-${record.id}`} className="rounded-xl bg-white p-4 shadow-sm">
                            <div className="flex gap-3">
                                <SensusPhoto file={record.foto} src={online ? record.foto_url : null} alt={`Foto bidang ${record.nama}`} className="h-20 w-20 shrink-0 rounded-lg" />
                                <div className="min-w-0 flex-1">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <h3 className="min-w-0 break-words font-bold text-forest">{record.nama}</h3>
                                        <span className={`rounded-md px-2 py-0.5 text-xs font-semibold ${record.status === 'synced' ? 'bg-emerald-100 text-emerald-900' : record.status === 'failed' ? 'bg-red-100 text-red-900' : 'bg-amber-100 text-amber-950'}`}>{statusLabel(record.status)}</span>
                                    </div>
                                    <p className="mt-1 text-sm text-stone-700">{numberFormat.format(Number(record.luas_garapan))} Ha · {record.lama_menggarap} tahun</p>
                                    <p className="mt-1 text-xs text-stone-700">{formatDate(record.captured_at)}</p>
                                </div>
                            </div>
                            {record.error && <p role="status" className="mt-3 rounded-lg bg-red-50 p-2 text-sm text-red-800">{record.error}</p>}
                            <div className="mt-3 flex flex-wrap items-center gap-2 border-t border-stone-100 pt-3">
                                <button type="button" onClick={() => setSelectedUuid(record.client_uuid ?? `server-${record.id}`)} className="min-h-10 rounded-lg bg-forest px-3 text-sm font-semibold text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-forest">Rincian</button>
                                <button type="button" onClick={() => openEdit(record)} disabled={record.status === 'sending'} className="min-h-10 rounded-lg border border-forest px-3 text-sm font-semibold text-forest disabled:opacity-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-forest">Edit data</button>
                                {record.status === 'failed' && record.retryable !== false && online && <button type="button" onClick={() => retry(record.client_uuid)} disabled={isSending || retryingUuid !== null} className="flex min-h-10 items-center gap-2 rounded-lg border border-secondary px-3 text-sm font-semibold text-secondary disabled:opacity-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-secondary"><BusyIndicator active={retryingUuid === record.client_uuid} />{retryingUuid === record.client_uuid ? 'Mengirim...' : 'Kirim ulang'}</button>}
                                {record.status === 'failed' && record.retryable === false && record.operation !== 'update' && <a href={`${route('input-sensus')}?edit=${encodeURIComponent(record.client_uuid)}`} className="flex min-h-10 items-center rounded-lg border border-secondary px-3 text-sm font-semibold text-secondary focus-visible:outline focus-visible:outline-2 focus-visible:outline-secondary">Perbaiki</a>}
                            </div>
                        </article>
                    ))}
                    {hasMoreRecords && (
                        <button type="button" onClick={loadMore} disabled={loadingMore || searchLoading || (online && activeSearch !== search.trim())} className="flex min-h-12 w-full items-center justify-center gap-2 rounded-lg border border-forest bg-white px-4 font-bold text-forest disabled:opacity-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-forest">
                            <BusyIndicator active={loadingMore} />
                            {loadingMore ? 'Memuat...' : 'Muat lagi'}
                        </button>
                    )}
                </section>
            </main>

            <SensusExportModal show={exportOpen} onClose={() => setExportOpen(false)} pendingCount={summary.pending} online={online} />

            <Modal show={editRecord !== null} onClose={() => { if (!editSaving) setEditRecord(null); }} maxWidth="md">
                <form onSubmit={saveEdit} className="space-y-4 p-5 sm:p-6">
                    <div>
                        <h2 className="text-lg font-bold text-forest">Edit data bidang</h2>
                        <p className="mt-1 text-sm text-stone-700">Perubahan disimpan di perangkat lalu dikirim saat koneksi tersedia. Foto dan titik GPS tetap.</p>
                    </div>
                    {editError && <p role="alert" className="rounded-lg bg-red-50 p-3 text-sm font-medium text-red-800">{editError}</p>}
                    <div>
                        <label htmlFor="edit-nama" className="mb-1 block text-sm font-semibold">Nama penggarap</label>
                        <input id="edit-nama" className="field-input" maxLength="100" required value={editData.nama} onChange={(event) => setEditData({ ...editData, nama: event.target.value })} />
                    </div>
                    <div>
                        <label htmlFor="edit-no-hp" className="mb-1 block text-sm font-semibold">Nomor HP / WhatsApp (opsional)</label>
                        <input id="edit-no-hp" type="tel" className="field-input" maxLength="20" value={editData.no_hp} onChange={(event) => setEditData({ ...editData, no_hp: event.target.value })} />
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label htmlFor="edit-luas" className="mb-1 block text-sm font-semibold">Luas garapan (Ha)</label>
                            <input id="edit-luas" type="number" inputMode="decimal" min="0.01" max="999999.99" step="0.01" className="field-input" required value={editData.luas_garapan} onChange={(event) => setEditData({ ...editData, luas_garapan: event.target.value })} />
                        </div>
                        <div>
                            <label htmlFor="edit-lama" className="mb-1 block text-sm font-semibold">Lama menggarap (tahun)</label>
                            <input id="edit-lama" type="number" inputMode="numeric" min="0" max="150" step="1" className="field-input" required value={editData.lama_menggarap} onChange={(event) => setEditData({ ...editData, lama_menggarap: event.target.value })} />
                        </div>
                    </div>
                    <div className="flex flex-col-reverse gap-2 pt-2 sm:flex-row sm:justify-end">
                        <button type="button" onClick={() => setEditRecord(null)} disabled={editSaving} className="min-h-12 rounded-lg border border-forest px-4 font-semibold text-forest disabled:opacity-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-forest">Batal</button>
                        <button type="submit" disabled={editSaving} className="flex min-h-12 items-center justify-center gap-2 rounded-lg bg-forest px-4 font-semibold text-white disabled:opacity-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-forest"><BusyIndicator active={editSaving} />{editSaving ? 'Menyimpan...' : 'Simpan perubahan'}</button>
                    </div>
                </form>
            </Modal>

            <dialog ref={dialogRef} onClose={() => setSelectedUuid(null)} aria-labelledby="rincian-title" className="max-h-[85vh] w-[min(92vw,420px)] overflow-y-auto rounded-xl p-0 text-stone-900 backdrop:bg-black/60">
                {selected && (
                    <div>
                        <SensusPhoto file={selected.foto} src={online ? selected.foto_url : null} alt={`Foto bidang ${selected.nama}`} className="h-52 w-full" />
                        <div className="space-y-3 p-5">
                            <div className="flex items-start justify-between gap-3">
                                <div><p className="text-sm text-stone-700">{statusLabel(selected.status)}</p><h2 id="rincian-title" className="text-xl font-bold text-forest">{selected.nama}</h2></div>
                                <button type="button" onClick={() => dialogRef.current?.close()} aria-label="Tutup rincian" className="rounded-lg px-2 text-2xl text-forest focus-visible:outline focus-visible:outline-2 focus-visible:outline-forest">×</button>
                            </div>
                            <dl className="grid grid-cols-2 gap-3 text-sm">
                                <div><dt className="text-stone-600">Luas</dt><dd className="font-semibold">{numberFormat.format(Number(selected.luas_garapan))} Ha</dd></div>
                                <div><dt className="text-stone-600">Lama garap</dt><dd className="font-semibold">{selected.lama_menggarap} tahun</dd></div>
                                <div><dt className="text-stone-600">Lintang</dt><dd className="break-all font-mono">{selected.latitude}</dd></div>
                                <div><dt className="text-stone-600">Bujur</dt><dd className="break-all font-mono">{selected.longitude}</dd></div>
                                {selected.utm_x !== null && selected.utm_x !== undefined && (
                                    <>
                                        <div><dt className="text-stone-600">UTM X</dt><dd className="break-all font-mono">{selected.utm_x} m</dd></div>
                                        <div><dt className="text-stone-600">UTM Y</dt><dd className="break-all font-mono">{selected.utm_y} m</dd></div>
                                        <div className="col-span-2"><dt className="text-stone-600">Sistem koordinat</dt><dd className="font-semibold">WGS84 / UTM EPSG:{selected.utm_epsg}</dd></div>
                                    </>
                                )}
                                <div className="col-span-2"><dt className="text-stone-600">Akurasi GPS</dt><dd className="font-semibold">{selected.gps_accuracy_m === null ? 'Tidak tersedia' : `±${Math.round(Number(selected.gps_accuracy_m))} meter`}</dd></div>
                                <div className="col-span-2"><dt className="text-stone-600">Dicatat</dt><dd className="font-semibold">{formatDate(selected.captured_at)}</dd></div>
                                {selected.no_hp && <div className="col-span-2"><dt className="text-stone-600">Nomor HP</dt><dd className="font-semibold">{selected.no_hp}</dd></div>}
                            </dl>
                        </div>
                    </div>
                )}
            </dialog>
        </div>
    );
}
