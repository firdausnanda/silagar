import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AdminLayout from '../../../Layouts/AdminLayout';

const dateFormat = new Intl.DateTimeFormat('id-ID', {
    timeZone: 'Asia/Jakarta', day: 'numeric', month: 'short', year: 'numeric',
    hour: '2-digit', minute: '2-digit',
});

const statuses = {
    pending: ['Menunggu antrean', 'bg-amber-100 text-amber-900'],
    running: ['Sedang diproses', 'bg-sky-100 text-sky-900'],
    success: ['Berhasil', 'bg-emerald-100 text-emerald-900'],
    failed: ['Gagal', 'bg-rose-100 text-rose-900'],
};

export default function Index({ configured, connected, usesEnvironmentConnection, email, runs, status, schedule }) {
    const [processing, setProcessing] = useState(false);
    const busy = runs.some((run) => ['pending', 'running'].includes(run.status));

    const startBackup = () => {
        router.post(route('admin.backups.store'), {}, {
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
        });
    };

    return (
        <AdminLayout title="Backup database">
            <Head title="Backup database" />

            {status && <p role="status" className="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-forest">{status}</p>}

            <section aria-labelledby="backup-title" className="rounded-xl border border-stone-200 bg-white p-5 shadow-sm sm:p-6">
                <div className="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
                    <div className="max-w-2xl">
                        <h2 id="backup-title" className="text-lg font-bold text-forest">Backup ke Google Drive</h2>
                        <p className="mt-2 text-sm leading-6 text-stone-600">Arsip terenkripsi berisi database aplikasi. Backup otomatis dijalankan {schedule.toLowerCase()}. Foto dan berkas unggahan tidak termasuk.</p>
                        <p className="mt-3 text-sm font-semibold text-stone-800">
                            {usesEnvironmentConnection ? 'Folder tujuan diatur dari .env' : connected ? `Terhubung${email ? ` ke ${email}` : ' ke Google Drive'}` : 'Google Drive belum terhubung'}
                        </p>
                        {!configured && <p className="mt-2 text-sm text-rose-800">Admin VPS perlu mengisi GOOGLE_DRIVE_CLIENT_ID, GOOGLE_DRIVE_CLIENT_SECRET, dan BACKUP_ARCHIVE_PASSWORD di .env, lalu memuat ulang konfigurasi.</p>}
                    </div>
                    <div className="flex w-full flex-col gap-2 sm:w-auto sm:min-w-48">
                        {configured && !usesEnvironmentConnection && <a href={route('admin.backups.connect')} className="inline-flex min-h-11 items-center justify-center rounded-lg border border-forest px-4 text-sm font-semibold text-forest hover:bg-emerald-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-forest">{connected ? 'Hubungkan ulang Google' : 'Hubungkan Google Drive'}</a>}
                        <button type="button" onClick={startBackup} disabled={!configured || !connected || busy || processing} className="min-h-11 rounded-lg bg-forest px-4 text-sm font-semibold text-white hover:bg-emerald-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-forest disabled:cursor-not-allowed disabled:opacity-50">
                            {processing ? 'Menambahkan ke antrean…' : busy ? 'Backup sedang berjalan' : 'Backup sekarang'}
                        </button>
                    </div>
                </div>
            </section>

            <section aria-labelledby="backup-history-title" className="rounded-xl border border-stone-200 bg-white p-5 shadow-sm sm:p-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <h2 id="backup-history-title" className="text-lg font-bold text-forest">Riwayat backup</h2>
                    <Link href={route('admin.backups.index')} preserveScroll className="text-sm font-semibold text-forest underline underline-offset-2 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-forest">Muat ulang status</Link>
                </div>
                {runs.length === 0 ? (
                    <p className="mt-5 text-sm text-stone-600">Belum ada backup. {usesEnvironmentConnection ? 'Jalankan backup pertama untuk memeriksa akses folder Google Drive.' : 'Hubungkan Google Drive, lalu jalankan backup pertama.'}</p>
                ) : (
                    <div className="mt-5 divide-y divide-stone-200">
                        {runs.map((run) => {
                            const [label, color] = statuses[run.status] ?? [run.status, 'bg-stone-100 text-stone-800'];
                            return (
                                <div key={run.id} className="flex flex-col gap-2 py-4 first:pt-0 sm:flex-row sm:items-start sm:justify-between sm:gap-4">
                                    <div className="min-w-0">
                                        <p className="break-words text-sm font-semibold text-stone-900">{run.filename ?? (run.source === 'manual' ? 'Backup manual' : 'Backup otomatis')}</p>
                                        <p className="mt-1 text-xs text-stone-600">{dateFormat.format(new Date(run.created_at))} WIB · {run.source === 'manual' ? 'Manual' : 'Otomatis'}{run.size ? ` · ${(run.size / 1048576).toFixed(2)} MB` : ''}</p>
                                        {run.drive_file_id && <a href={`https://drive.google.com/file/d/${encodeURIComponent(run.drive_file_id)}/view`} target="_blank" rel="noopener noreferrer" className="mt-2 inline-block text-sm font-semibold text-forest underline underline-offset-2 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-forest">Buka di Google Drive</a>}
                                        {run.error && <p className="mt-2 text-sm text-rose-800">{run.error}</p>}
                                    </div>
                                    <span className={`w-fit shrink-0 rounded-md px-2.5 py-1 text-xs font-semibold ${color}`}>{label}</span>
                                </div>
                            );
                        })}
                    </div>
                )}
            </section>
        </AdminLayout>
    );
}
