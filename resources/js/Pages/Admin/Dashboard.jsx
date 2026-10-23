import { ChevronDownIcon } from '@heroicons/react/24/outline';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import SearchableSelect from '../../Components/SearchableSelect';
import SensusExportModal from '../../Components/SensusExportModal';
import AdminLayout from '../../Layouts/AdminLayout';

const numberFormat = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 });
const dateFormat = new Intl.DateTimeFormat('id-ID', {
    timeZone: 'Asia/Jakarta', day: 'numeric', month: 'short', year: 'numeric',
    hour: '2-digit', minute: '2-digit',
});
const filterInputClass = 'min-h-11 w-full rounded-lg border-stone-500 bg-white text-base text-stone-900 focus:border-forest focus:ring-forest';
const filterLabelClass = 'mb-1 block text-sm font-semibold text-stone-700';

export default function Dashboard({ records, summary, recorders, kabupatenKotaOptions, filters }) {
    const { errors } = usePage().props;
    const [search, setSearch] = useState(filters.search ?? '');
    const [userId, setUserId] = useState(String(filters.user_id ?? ''));
    const [advancedFilters, setAdvancedFilters] = useState({
        kabupaten_kota: filters.kabupaten_kota ?? '',
        date_from: filters.date_from ?? '',
        date_to: filters.date_to ?? '',
        luas_min: filters.luas_min ?? '',
        luas_max: filters.luas_max ?? '',
        foto: filters.foto ?? '',
        koordinat: filters.koordinat ?? '',
        sort: filters.sort ?? 'terbaru',
    });
    const [showAdvanced, setShowAdvanced] = useState(false);
    const [exportOpen, setExportOpen] = useState(false);
    const filterErrors = ['search', 'user_id', 'kabupaten_kota', 'date_from', 'date_to', 'luas_min', 'luas_max', 'foto', 'koordinat', 'sort']
        .map((field) => errors?.[field]).filter(Boolean);
    const advancedFilterCount = Object.entries(advancedFilters)
        .filter(([key, value]) => value !== '' && !(key === 'sort' && value === 'terbaru')).length;

    const updateAdvancedFilter = (field, value) => {
        setAdvancedFilters((current) => ({ ...current, [field]: value }));
    };

    const applyFilters = (event) => {
        event.preventDefault();
        router.get(route('admin.dashboard'), {
            ...(search.trim() ? { search: search.trim() } : {}),
            ...(userId ? { user_id: userId } : {}),
            ...Object.fromEntries(Object.entries(advancedFilters).filter(([key, value]) => value !== '' && !(key === 'sort' && value === 'terbaru'))),
        }, { preserveState: true, replace: true });
    };

    const resetFilters = () => {
        router.get(route('admin.dashboard'), {}, { replace: true });
    };

    return (
        <AdminLayout title="Monitoring sensus">
            <Head title="Monitoring sensus" />
            <section aria-label="Ringkasan seluruh petugas" className="grid gap-4 sm:grid-cols-3">
                {[
                    ['Jumlah Lahan Garapan', numberFormat.format(summary.total)],
                    ['Luas tercatat', `${numberFormat.format(summary.luas)} Ha`],
                    ['Petugas aktif', numberFormat.format(summary.active_users)],
                ].map(([label, value]) => (
                    <div key={label} className="rounded-xl border border-stone-200 bg-white p-5 shadow-sm">
                        <p className="text-sm text-stone-600">{label}</p>
                        <p className="mt-2 text-2xl font-bold text-forest">{value}</p>
                    </div>
                ))}
            </section>

            <section className="rounded-xl border border-stone-200 bg-white p-5 shadow-sm" aria-labelledby="admin-records-title">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 id="admin-records-title" className="text-lg font-bold text-forest">Daftar sensus</h2>
                        <p className="mt-1 text-sm text-stone-600">{numberFormat.format(records.total)} bidang sesuai filter. Entri yang masih luring di perangkat petugas belum terlihat.</p>
                    </div>
                    <button type="button" onClick={() => setExportOpen(true)} className="inline-flex min-h-11 items-center justify-center rounded-lg border border-forest bg-white px-4 text-sm font-semibold text-forest transition-colors hover:bg-emerald-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-forest">Ekspor Excel</button>
                </div>
                <form onSubmit={applyFilters} className="mt-5 rounded-xl border border-stone-200 bg-stone-50 p-4 sm:p-5">
                    <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(220px,280px)]">
                        <div>
                            <label htmlFor="admin-search" className={filterLabelClass}>Cari data sensus</label>
                            <input id="admin-search" type="search" maxLength="100" value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Penggarap, KTH, wilayah, penunjuk, atau nomor HP" className={filterInputClass} />
                        </div>
                        <div>
                            <label htmlFor="admin-recorder" className={filterLabelClass}>Petugas pencatat</label>
                            <SearchableSelect id="admin-recorder" value={userId} onChange={setUserId} options={recorders.map((user) => ({ value: String(user.id), label: user.name }))} placeholder="Semua petugas" compact backgroundColor="#ffffff" />
                        </div>
                    </div>

                    {showAdvanced && <div id="admin-advanced-filters" className="mt-4 space-y-5 border-t border-stone-200 pt-5">
                        <fieldset>
                            <legend className="mb-3 text-sm font-bold text-forest">Wilayah dan tanggal pencatatan</legend>
                            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                                <div>
                                    <label htmlFor="admin-kabupaten-kota" className={filterLabelClass}>Kabupaten/Kota</label>
                                    <select id="admin-kabupaten-kota" value={advancedFilters.kabupaten_kota} onChange={(event) => updateAdvancedFilter('kabupaten_kota', event.target.value)} className={filterInputClass}>
                                        <option value="">Semua wilayah</option>
                                        {kabupatenKotaOptions.map((name) => <option key={name} value={name}>{name}</option>)}
                                    </select>
                                </div>
                                <div>
                                    <label htmlFor="admin-date-from" className={filterLabelClass}>Dari tanggal</label>
                                    <input id="admin-date-from" type="date" value={advancedFilters.date_from} max={advancedFilters.date_to || undefined} onChange={(event) => updateAdvancedFilter('date_from', event.target.value)} className={filterInputClass} />
                                </div>
                                <div>
                                    <label htmlFor="admin-date-to" className={filterLabelClass}>Sampai tanggal</label>
                                    <input id="admin-date-to" type="date" value={advancedFilters.date_to} min={advancedFilters.date_from || undefined} onChange={(event) => updateAdvancedFilter('date_to', event.target.value)} className={filterInputClass} />
                                </div>
                            </div>
                        </fieldset>

                        <fieldset>
                            <legend className="mb-3 text-sm font-bold text-forest">Luas dan kelengkapan data</legend>
                            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                                <div>
                                    <label htmlFor="admin-luas-min" className={filterLabelClass}>Luas minimal (Ha)</label>
                                    <input id="admin-luas-min" type="number" min="0" max="999999.99" step="0.01" value={advancedFilters.luas_min} onChange={(event) => updateAdvancedFilter('luas_min', event.target.value)} className={filterInputClass} />
                                </div>
                                <div>
                                    <label htmlFor="admin-luas-max" className={filterLabelClass}>Luas maksimal (Ha)</label>
                                    <input id="admin-luas-max" type="number" min="0" max="999999.99" step="0.01" value={advancedFilters.luas_max} onChange={(event) => updateAdvancedFilter('luas_max', event.target.value)} className={filterInputClass} />
                                </div>
                                <div>
                                    <label htmlFor="admin-foto" className={filterLabelClass}>Foto</label>
                                    <select id="admin-foto" value={advancedFilters.foto} onChange={(event) => updateAdvancedFilter('foto', event.target.value)} className={filterInputClass}>
                                        <option value="">Semua</option>
                                        <option value="ada">Ada foto</option>
                                        <option value="tidak_ada">Tanpa foto</option>
                                    </select>
                                </div>
                                <div>
                                    <label htmlFor="admin-koordinat" className={filterLabelClass}>Koordinat</label>
                                    <select id="admin-koordinat" value={advancedFilters.koordinat} onChange={(event) => updateAdvancedFilter('koordinat', event.target.value)} className={filterInputClass}>
                                        <option value="">Semua</option>
                                        <option value="ada">Ada koordinat</option>
                                        <option value="tidak_ada">Tanpa koordinat</option>
                                    </select>
                                </div>
                            </div>
                        </fieldset>

                        <div className="border-t border-stone-200 pt-5 sm:max-w-xs">
                            <label htmlFor="admin-sort" className={filterLabelClass}>Urutan hasil</label>
                            <select id="admin-sort" value={advancedFilters.sort} onChange={(event) => updateAdvancedFilter('sort', event.target.value)} className={filterInputClass}>
                                <option value="terbaru">Entri terbaru</option>
                                <option value="terlama">Entri terlama</option>
                                <option value="luas_terbesar">Luas terbesar</option>
                                <option value="luas_terkecil">Luas terkecil</option>
                            </select>
                        </div>
                    </div>}

                    {filterErrors.length > 0 && <div role="alert" className="mt-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800">{filterErrors.map((message, index) => <p key={index}>{message}</p>)}</div>}
                    <div className="mt-5 flex flex-col gap-3 border-t border-stone-200 pt-4 sm:flex-row sm:justify-end">
                        <button type="button" aria-expanded={showAdvanced} aria-controls={showAdvanced ? 'admin-advanced-filters' : undefined} onClick={() => setShowAdvanced(!showAdvanced)} className="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-forest bg-white px-4 text-sm font-semibold text-forest transition-colors hover:bg-emerald-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-forest">
                            <span>{showAdvanced ? 'Tutup filter lanjutan' : 'Filter lanjutan'}{advancedFilterCount > 0 ? ` (${advancedFilterCount})` : ''}</span>
                            <ChevronDownIcon aria-hidden="true" className={`h-5 w-5 shrink-0 transition-transform ${showAdvanced ? 'rotate-180' : ''}`} />
                        </button>
                        <button type="button" onClick={resetFilters} className="min-h-11 rounded-lg border border-stone-500 bg-white px-5 text-sm font-semibold text-stone-800 transition-colors hover:bg-stone-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-forest">Reset semua</button>
                        <button type="submit" className="min-h-11 rounded-lg bg-forest px-6 text-sm font-semibold text-white transition-colors hover:bg-emerald-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-forest">Tampilkan hasil</button>
                    </div>
                </form>

                <div className="mt-5 overflow-x-auto">
                    <table className="min-w-[1080px] w-full border-collapse text-left text-sm">
                        <thead><tr className="border-b border-stone-200 text-stone-600"><th className="py-3 pr-4 font-semibold">Bidang</th><th className="py-3 pr-4 font-semibold">Kabupaten/Kota</th><th className="py-3 pr-4 font-semibold">Nama Penunjuk</th><th className="py-3 pr-4 font-semibold">Petugas</th><th className="py-3 pr-4 font-semibold">Garapan</th><th className="py-3 pr-4 font-semibold">Koordinat</th><th className="py-3 font-semibold">Dicatat</th></tr></thead>
                        <tbody>
                            {records.data.map((record) => (
                                <tr key={record.id} className="border-b border-stone-100 align-top">
                                    <td className="py-3 pr-4"><strong className="text-forest">{record.nama}</strong>{record.nama_kth && <span className="mt-1 block break-words text-xs text-stone-600">KTH: {record.nama_kth}</span>}<span className="mt-1 block text-xs text-stone-500">#{record.id}{record.no_hp ? ` · ${record.no_hp}` : ''}</span>{record.foto_url ? <a href={record.foto_url} target="_blank" rel="noopener noreferrer" className="mt-1 inline-block font-semibold text-forest underline decoration-emerald-300 underline-offset-2 focus-visible:outline focus-visible:outline-2 focus-visible:outline-forest">Lihat foto</a> : <span className="mt-1 block text-xs font-medium text-amber-800">Foto belum tersedia</span>}</td>
                                    <td className="py-3 pr-4">{record.kabupaten_kota || '—'}</td>
                                    <td className="py-3 pr-4">{record.nama_penunjuk || '—'}</td>
                                    <td className="py-3 pr-4">{record.creator_name}</td>
                                    <td className="py-3 pr-4">
                                        <span className="block whitespace-nowrap font-semibold text-forest">{numberFormat.format(record.luas_garapan)} Ha</span>
                                        <span className="mt-1 block whitespace-nowrap text-xs text-stone-600">{record.lama_menggarap} tahun menggarap</span>
                                    </td>
                                    <td className="py-3 pr-4 font-mono text-xs">
                                        {record.latitude === null || record.longitude === null ? 'Belum tersedia' : (
                                            <a
                                                href={`https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(`${record.latitude},${record.longitude}`)}`}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                aria-label={`Buka koordinat ${record.latitude.toFixed(6)}, ${record.longitude.toFixed(6)} di Google Maps`}
                                                className="inline-block whitespace-nowrap font-semibold text-forest underline decoration-emerald-300 underline-offset-2 hover:text-emerald-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-forest"
                                            >
                                                {record.latitude.toFixed(6)}, {record.longitude.toFixed(6)}
                                            </a>
                                        )}
                                    </td>
                                    <td className="py-3">{record.captured_at ? `${dateFormat.format(new Date(record.captured_at))} WIB` : 'Belum tersedia'}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    {records.data.length === 0 && <p className="py-8 text-center text-sm text-stone-600">Belum ada sensus yang cocok.</p>}
                </div>
                <div className="mt-5 flex items-center justify-between gap-3 text-sm">
                    <span className="text-stone-600">Halaman {records.current_page} dari {records.last_page}</span>
                    <div className="flex gap-2">
                        {records.prev_page_url && <Link href={records.prev_page_url} preserveState className="rounded-lg border border-stone-300 px-4 py-2 font-semibold text-forest focus-visible:outline focus-visible:outline-2 focus-visible:outline-forest">Sebelumnya</Link>}
                        {records.next_page_url && <Link href={records.next_page_url} preserveState className="rounded-lg border border-stone-300 px-4 py-2 font-semibold text-forest focus-visible:outline focus-visible:outline-2 focus-visible:outline-forest">Berikutnya</Link>}
                    </div>
                </div>
            </section>
            <SensusExportModal show={exportOpen} onClose={() => setExportOpen(false)} admin />
        </AdminLayout>
    );
}
