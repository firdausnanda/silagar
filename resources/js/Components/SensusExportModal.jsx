import axios from 'axios';
import { useEffect, useMemo, useState } from 'react';
import BusyIndicator from './BusyIndicator';
import Modal from './Modal';
import { filterExportRecords } from './sensusExportData';

const exportColumns = [
    ['id', 'ID bidang'],
    ['nama', 'Nama penggarap'],
    ['no_hp', 'Nomor HP'],
    ['luas_garapan', 'Luas garapan (Ha)'],
    ['lama_menggarap', 'Lama menggarap (tahun)'],
    ['latitude', 'Lintang'],
    ['longitude', 'Bujur'],
    ['utm_x', 'UTM X (m)'],
    ['utm_y', 'UTM Y (m)'],
    ['utm_epsg', 'EPSG UTM'],
    ['gps_accuracy_m', 'Akurasi GPS (m)'],
    ['captured_at', 'Waktu pencatatan (WIB)'],
    ['updated_at', 'Waktu pembaruan (WIB)'],
    ['creator_name', 'Petugas pencatat'],
];

const defaultColumns = [
    'nama', 'no_hp', 'luas_garapan', 'lama_menggarap',
    'latitude', 'longitude', 'captured_at',
];
const maxExportRecords = 1000;

const dateFormat = new Intl.DateTimeFormat('id-ID', {
    timeZone: 'Asia/Jakarta',
    day: 'numeric', month: 'short', year: 'numeric',
});

export default function SensusExportModal({ show, onClose, pendingCount, online }) {
    const [catalog, setCatalog] = useState(null);
    const [loading, setLoading] = useState(false);
    const [search, setSearch] = useState('');
    const [startDate, setStartDate] = useState('');
    const [endDate, setEndDate] = useState('');
    const [selectedIds, setSelectedIds] = useState(new Set());
    const [selectedColumns, setSelectedColumns] = useState(new Set(defaultColumns));
    const [downloading, setDownloading] = useState(false);
    const [error, setError] = useState('');
    const [status, setStatus] = useState('');

    useEffect(() => {
        if (!show || !online) {
            return;
        }

        let cancelled = false;
        setLoading(true);
        setCatalog(null);
        setError('');
        axios.get(route('sensus.export.choices'), { headers: { Accept: 'application/json' } })
            .then(({ data }) => {
                if (!cancelled) {
                    setCatalog(data);
                    const availableIds = new Set(data.records.map((record) => Number(record.id)));
                    setSelectedIds((current) => new Set([...current].filter((id) => availableIds.has(id))));
                }
            })
            .catch(() => {
                if (!cancelled) {
                    setError('Daftar bidang belum berhasil dimuat. Tutup lalu buka kembali panel ekspor.');
                }
            })
            .finally(() => {
                if (!cancelled) {
                    setLoading(false);
                }
            });

        return () => { cancelled = true; };
    }, [show, online]);

    const availableRecords = catalog?.records ?? [];
    const visibleRecords = useMemo(() => filterExportRecords(availableRecords, {
        search, startDate, endDate,
    }), [availableRecords, search, startDate, endDate]);
    const availableIds = new Set(availableRecords.map((record) => Number(record.id)));
    const chosenIds = [...selectedIds].filter((id) => availableIds.has(id));
    const columns = exportColumns.map(([key]) => key).filter((key) => selectedColumns.has(key));
    const invalidDateRange = Boolean(startDate && endDate && startDate > endDate);
    const overRecordLimit = chosenIds.length > maxExportRecords;

    const toggleId = (id) => {
        setSelectedIds((current) => {
            const next = new Set(current);
            if (next.has(id)) {
                next.delete(id);
            } else {
                next.add(id);
            }
            return next;
        });
    };

    const selectVisible = () => {
        setSelectedIds((current) => new Set([...current, ...visibleRecords.map((record) => Number(record.id))]));
    };

    const toggleColumn = (key) => {
        setSelectedColumns((current) => {
            const next = new Set(current);
            if (next.has(key)) {
                next.delete(key);
            } else {
                next.add(key);
            }
            return next;
        });
    };

    const download = async () => {
        if (!online || loading || !catalog || downloading || invalidDateRange || overRecordLimit || chosenIds.length === 0 || columns.length === 0) {
            return;
        }

        setDownloading(true);
        setError('');
        setStatus('');

        try {
            const response = await axios.post(route('sensus.export'), {
                ids: chosenIds,
                columns,
                scope: 'mine',
            }, {
                responseType: 'blob',
                headers: { Accept: 'application/json' },
                withXSRFToken: true,
            });
            const filename = response.headers['content-disposition']?.match(/filename="?([^";]+)"?/i)?.[1]
                ?? 'sipintar-hut.xlsx';
            const url = URL.createObjectURL(response.data);
            const link = document.createElement('a');
            link.href = url;
            link.download = filename;
            document.body.appendChild(link);
            link.click();
            link.remove();
            window.setTimeout(() => URL.revokeObjectURL(url), 1000);
            setStatus('File Excel sedang diunduh.');
        } catch (requestError) {
            let message = 'Ekspor belum berhasil. Periksa koneksi lalu coba lagi.';
            if ([401, 419].includes(requestError.response?.status)) {
                message = 'Sesi berakhir. Masuk kembali lalu coba ekspor.';
            } else if (requestError.response?.data instanceof Blob) {
                try {
                    const payload = JSON.parse(await requestError.response.data.text());
                    message = payload.errors?.ids?.[0]
                        ?? Object.values(payload.errors ?? {}).flat()[0]
                        ?? payload.message
                        ?? message;
                } catch {
                    message = 'Ekspor belum berhasil. Periksa koneksi lalu coba lagi.';
                }
            }
            setError(message);
        } finally {
            setDownloading(false);
        }
    };

    return (
        <Modal show={show} onClose={() => { if (!downloading) onClose(); }} closeable={!downloading} maxWidth="xl">
            <div className="max-h-[85vh] w-[calc(100vw-2rem)] space-y-5 overflow-y-auto p-4 text-on-surface sm:w-full sm:p-6">
                <div className="flex items-start justify-between gap-3">
                    <div>
                        <h2 className="text-xl font-bold text-forest">Ekspor data ke Excel</h2>
                        <p className="mt-1 text-sm text-stone-700">Pilih bidang dan kolom untuk file .xlsx.</p>
                    </div>
                    <button type="button" onClick={onClose} disabled={downloading} aria-label="Tutup ekspor" className="min-h-10 min-w-10 rounded-lg text-2xl text-forest focus-visible:outline focus-visible:outline-2 focus-visible:outline-forest">×</button>
                </div>

                {pendingCount > 0 && <p className="rounded-lg bg-amber-100 p-3 text-sm text-amber-950">{pendingCount} entri belum sinkron dan tidak masuk dalam ekspor.</p>}
                {!online && <p role="alert" className="rounded-lg bg-amber-100 p-3 text-sm text-amber-950">Hubungkan perangkat ke internet untuk mengunduh data server.</p>}
                {overRecordLimit && <p role="alert" className="rounded-lg bg-amber-100 p-3 text-sm text-amber-950">Maksimal 1.000 bidang per file. Kurangi pilihan untuk mengunduh.</p>}
                {error && <p role="alert" className="rounded-lg bg-red-50 p-3 text-sm text-red-800">{error}</p>}
                {status && <p role="status" className="rounded-lg bg-emerald-100 p-3 text-sm text-emerald-900">{status}</p>}

                <fieldset className="space-y-3">
                    <legend className="font-bold text-forest">Pilih bidang</legend>
                    <p className="text-sm text-stone-700">Hanya bidang milik akun Anda yang tersedia untuk ekspor.</p>
                    <label htmlFor="export-search" className="block text-sm font-semibold">Cari nama, nomor HP, atau koordinat</label>
                    <input id="export-search" type="search" value={search} onChange={(event) => setSearch(event.target.value)} className="min-h-11 w-full rounded-lg border-stone-500 focus:border-forest focus:ring-forest" />
                    <div className="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label htmlFor="export-start" className="mb-1 block text-sm font-semibold">Dari tanggal (WIB)</label>
                            <input id="export-start" type="date" value={startDate} onChange={(event) => setStartDate(event.target.value)} className="min-h-11 w-full rounded-lg border-stone-500 focus:border-forest focus:ring-forest" />
                        </div>
                        <div>
                            <label htmlFor="export-end" className="mb-1 block text-sm font-semibold">Sampai tanggal (WIB)</label>
                            <input id="export-end" type="date" value={endDate} onChange={(event) => setEndDate(event.target.value)} className="min-h-11 w-full rounded-lg border-stone-500 focus:border-forest focus:ring-forest" />
                        </div>
                    </div>
                    {invalidDateRange && <p role="alert" className="text-sm font-semibold text-red-800">Tanggal awal harus sebelum atau sama dengan tanggal akhir.</p>}
                    <div className="flex flex-wrap items-center justify-between gap-2 text-sm">
                        <span>{visibleRecords.length} bidang hasil filter · {chosenIds.length} terpilih{selectedIds.size > chosenIds.length ? ` · ${selectedIds.size - chosenIds.length} tersimpan dari filter lain` : ''}</span>
                        <div className="flex flex-wrap gap-2">
                            <button type="button" onClick={selectVisible} disabled={loading || visibleRecords.length === 0 || invalidDateRange} className="min-h-10 rounded-lg border border-forest px-3 font-semibold text-forest disabled:opacity-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-forest">Pilih semua hasil filter</button>
                            <button type="button" onClick={() => setSelectedIds(new Set())} disabled={selectedIds.size === 0} className="min-h-10 rounded-lg px-3 font-semibold text-forest disabled:opacity-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-forest">Hapus pilihan</button>
                        </div>
                    </div>
                    <div className="max-h-56 space-y-1 overflow-y-auto rounded-lg border border-stone-300 p-2" aria-label="Daftar bidang untuk ekspor">
                        {loading ? (
                            <p role="status" className="flex items-center gap-2 p-3 text-sm text-stone-700"><BusyIndicator active={loading} />Memuat bidang dari server...</p>
                        ) : visibleRecords.length === 0 ? (
                            <p className="p-3 text-sm text-stone-700">{catalog ? 'Tidak ada bidang tersinkron yang cocok.' : 'Daftar bidang belum tersedia.'}</p>
                        ) : visibleRecords.map((record) => (
                            <label key={record.id} className="flex min-h-12 cursor-pointer items-center gap-3 rounded-lg p-2 hover:bg-surface-container-low focus-within:outline focus-within:outline-2 focus-within:outline-forest">
                                <input type="checkbox" checked={selectedIds.has(Number(record.id))} onChange={() => toggleId(Number(record.id))} className="h-5 w-5 rounded border-stone-500 text-forest focus:ring-forest" />
                                <span className="min-w-0 text-sm"><strong className="block break-words text-forest">{record.nama}</strong><span className="text-stone-700">#{record.id} · {record.creator_name} · {record.captured_at ? dateFormat.format(new Date(record.captured_at)) : 'Tanggal belum tersedia'}</span></span>
                            </label>
                        ))}
                    </div>
                </fieldset>

                <fieldset>
                    <legend className="font-bold text-forest">Kolom dalam Excel</legend>
                    <div className="mt-2 grid gap-2 sm:grid-cols-2">
                        {exportColumns.map(([key, label]) => (
                            <label key={key} className="flex min-h-10 cursor-pointer items-center gap-2 rounded-lg px-2 text-sm focus-within:outline focus-within:outline-2 focus-within:outline-forest">
                                <input type="checkbox" checked={selectedColumns.has(key)} onChange={() => toggleColumn(key)} className="h-5 w-5 rounded border-stone-500 text-forest focus:ring-forest" />
                                {label}
                            </label>
                        ))}
                    </div>
                </fieldset>

                <div className="flex flex-col-reverse gap-2 border-t border-stone-200 pt-4 sm:flex-row sm:justify-end">
                    <button type="button" onClick={onClose} disabled={downloading} className="min-h-12 rounded-lg border border-forest px-4 font-semibold text-forest disabled:opacity-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-forest">Tutup</button>
                    <button type="button" onClick={download} disabled={!online || loading || !catalog || downloading || invalidDateRange || overRecordLimit || chosenIds.length === 0 || columns.length === 0} aria-busy={downloading} className="flex min-h-12 items-center justify-center gap-2 rounded-lg bg-forest px-4 font-bold text-white disabled:opacity-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-forest">
                        <BusyIndicator active={downloading} />
                        {downloading ? 'Menyiapkan Excel...' : `Unduh ${chosenIds.length} bidang`}
                    </button>
                </div>
            </div>
        </Modal>
    );
}
