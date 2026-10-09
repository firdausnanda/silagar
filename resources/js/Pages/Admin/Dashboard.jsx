import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AdminLayout from '../../Layouts/AdminLayout';

const numberFormat = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 });
const dateFormat = new Intl.DateTimeFormat('id-ID', {
    timeZone: 'Asia/Jakarta', day: 'numeric', month: 'short', year: 'numeric',
    hour: '2-digit', minute: '2-digit',
});

export default function Dashboard({ records, summary, recorders, filters }) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [userId, setUserId] = useState(String(filters.user_id ?? ''));

    const applyFilters = (event) => {
        event.preventDefault();
        router.get(route('admin.dashboard'), {
            ...(search.trim() ? { search: search.trim() } : {}),
            ...(userId ? { user_id: userId } : {}),
        }, { preserveState: true, replace: true });
    };

    return (
        <AdminLayout title="Monitoring sensus">
            <Head title="Monitoring sensus" />
            <section aria-label="Ringkasan seluruh petugas" className="grid gap-4 sm:grid-cols-3">
                {[
                    ['Bidang di server', numberFormat.format(summary.total)],
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
                </div>
                <form onSubmit={applyFilters} className="mt-5 grid gap-3 sm:grid-cols-[minmax(0,1fr)_220px_auto] sm:items-end">
                    <div>
                        <label htmlFor="admin-search" className="mb-1 block text-sm font-semibold text-stone-700">Cari nama atau nomor HP</label>
                        <input id="admin-search" type="search" maxLength="100" value={search} onChange={(event) => setSearch(event.target.value)} className="min-h-11 w-full rounded-lg border-stone-300 focus:border-forest focus:ring-forest" />
                    </div>
                    <div>
                        <label htmlFor="admin-recorder" className="mb-1 block text-sm font-semibold text-stone-700">Petugas</label>
                        <select id="admin-recorder" value={userId} onChange={(event) => setUserId(event.target.value)} className="min-h-11 w-full rounded-lg border-stone-300 bg-white focus:border-forest focus:ring-forest">
                            <option value="">Semua petugas</option>
                            {recorders.map((user) => <option key={user.id} value={user.id}>{user.name}</option>)}
                        </select>
                    </div>
                    <button type="submit" className="min-h-11 rounded-lg bg-forest px-5 font-semibold text-white transition-colors hover:bg-emerald-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-forest">Terapkan</button>
                </form>

                <div className="mt-5 overflow-x-auto">
                    <table className="min-w-[760px] w-full border-collapse text-left text-sm">
                        <thead><tr className="border-b border-stone-200 text-stone-600"><th className="py-3 pr-4 font-semibold">Bidang</th><th className="py-3 pr-4 font-semibold">Petugas</th><th className="py-3 pr-4 font-semibold">Luas</th><th className="py-3 pr-4 font-semibold">Koordinat</th><th className="py-3 font-semibold">Dicatat</th></tr></thead>
                        <tbody>
                            {records.data.map((record) => (
                                <tr key={record.id} className="border-b border-stone-100 align-top">
                                    <td className="py-3 pr-4"><strong className="text-forest">{record.nama}</strong><span className="mt-1 block text-xs text-stone-500">#{record.id}{record.no_hp ? ` · ${record.no_hp}` : ''}</span><a href={record.foto_url} target="_blank" rel="noopener noreferrer" className="mt-1 inline-block font-semibold text-forest underline decoration-emerald-300 underline-offset-2 focus-visible:outline focus-visible:outline-2 focus-visible:outline-forest">Lihat foto</a></td>
                                    <td className="py-3 pr-4">{record.creator_name}</td>
                                    <td className="py-3 pr-4">{numberFormat.format(record.luas_garapan)} Ha</td>
                                    <td className="py-3 pr-4 font-mono text-xs">{record.latitude.toFixed(6)}, {record.longitude.toFixed(6)}</td>
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
        </AdminLayout>
    );
}
