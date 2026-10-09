import { DialogTitle } from '@headlessui/react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import BusyIndicator from '../../../Components/BusyIndicator';
import Modal from '../../../Components/Modal';
import AdminLayout from '../../../Layouts/AdminLayout';

const actionClass = 'min-h-11 rounded-lg border border-stone-300 bg-white px-3 text-sm font-semibold text-forest transition-colors hover:bg-stone-50 disabled:cursor-not-allowed disabled:opacity-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-forest';
const inputClass = 'min-h-11 w-full rounded-lg border-stone-300 focus:border-forest focus:ring-forest';

function UserCard({ user, pendingAction, onStatusChange, onImpersonate, onDelete }) {
    const [editing, setEditing] = useState(false);
    const form = useForm({
        name: user.name,
        email: user.email,
        password: '',
        password_confirmation: '',
    });
    const isBusy = pendingAction !== null || form.processing;
    const canDelete = !user.is_active && user.sensus_count === 0;

    const openEdit = () => {
        form.setData({
            name: user.name,
            email: user.email,
            password: '',
            password_confirmation: '',
        });
        form.clearErrors();
        setEditing(true);
    };

    const save = (event) => {
        event.preventDefault();

        if (form.processing) {
            return;
        }

        form.patch(route('admin.users.update', user.id), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset('password', 'password_confirmation');
                setEditing(false);
            },
        });
    };

    return (
        <article className="border-t border-stone-200 py-5 first:border-t-0">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="min-w-0">
                    <h3 className="break-words font-bold text-forest">{user.name}</h3>
                    <p className="break-all text-sm text-stone-600">{user.email}</p>
                </div>
                <div className="flex flex-wrap items-center gap-2 text-xs font-semibold">
                    <span className="rounded-md bg-stone-100 px-2.5 py-1.5 text-stone-700">{user.sensus_count} bidang</span>
                    <span className={user.is_active ? 'rounded-md bg-emerald-100 px-2.5 py-1.5 text-emerald-900' : 'rounded-md bg-stone-200 px-2.5 py-1.5 text-stone-700'}>
                        {user.is_active ? 'Aktif' : 'Nonaktif'}
                    </span>
                </div>
            </div>

            <div className="mt-4 flex flex-wrap gap-2">
                <button type="button" onClick={editing ? () => setEditing(false) : openEdit} disabled={isBusy} className={actionClass}>
                    {editing ? 'Tutup ubah data' : 'Ubah data'}
                </button>
                <button type="button" onClick={() => onImpersonate(user)} disabled={isBusy || !user.can_impersonate} className={actionClass} aria-label={'Masuk sebagai ' + user.name}>
                    {pendingAction?.type === 'impersonate' && pendingAction.id === user.id ? 'Memproses...' : 'Masuk sebagai'}
                </button>
                <button type="button" onClick={() => onStatusChange(user)} disabled={isBusy} className={actionClass}>
                    {pendingAction?.type === 'status' && pendingAction.id === user.id ? 'Memproses...' : user.is_active ? 'Nonaktifkan' : 'Aktifkan'}
                </button>
                <button type="button" onClick={() => onDelete(user)} disabled={isBusy || !canDelete} className="min-h-11 rounded-lg border border-red-300 px-3 text-sm font-semibold text-red-800 transition-colors hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-800" aria-label={'Hapus akun ' + user.name}>
                    Hapus
                </button>
            </div>

            {editing && (
                <form onSubmit={save} className="mt-4 grid gap-4 rounded-xl bg-stone-50 p-4 sm:grid-cols-2">
                    <div>
                        <label htmlFor={'edit-name-' + user.id} className="mb-1 block text-sm font-semibold">Nama</label>
                        <input id={'edit-name-' + user.id} required maxLength="255" value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} className={inputClass} />
                        {form.errors.name && <p role="alert" className="mt-1 text-sm text-red-700">{form.errors.name}</p>}
                    </div>
                    <div>
                        <label htmlFor={'edit-email-' + user.id} className="mb-1 block text-sm font-semibold">Email</label>
                        <input id={'edit-email-' + user.id} type="email" required maxLength="255" value={form.data.email} onChange={(event) => form.setData('email', event.target.value)} className={inputClass} />
                        {form.errors.email && <p role="alert" className="mt-1 text-sm text-red-700">{form.errors.email}</p>}
                    </div>
                    <div>
                        <label htmlFor={'edit-password-' + user.id} className="mb-1 block text-sm font-semibold">Kata sandi baru</label>
                        <input id={'edit-password-' + user.id} type="password" minLength="8" autoComplete="new-password" value={form.data.password} onChange={(event) => form.setData('password', event.target.value)} className={inputClass} />
                        {form.errors.password && <p role="alert" className="mt-1 text-sm text-red-700">{form.errors.password}</p>}
                    </div>
                    <div>
                        <label htmlFor={'edit-password-confirmation-' + user.id} className="mb-1 block text-sm font-semibold">Ulangi kata sandi baru</label>
                        <input id={'edit-password-confirmation-' + user.id} type="password" minLength="8" autoComplete="new-password" value={form.data.password_confirmation} onChange={(event) => form.setData('password_confirmation', event.target.value)} className={inputClass} />
                    </div>
                    <p className="text-sm text-stone-600 sm:col-span-2">Biarkan kedua kolom kata sandi kosong jika tidak ingin menggantinya.</p>
                    <div className="flex flex-wrap gap-2 sm:col-span-2">
                        <button type="submit" disabled={form.processing} aria-busy={form.processing} className="flex min-h-11 items-center justify-center gap-2 rounded-lg bg-forest px-5 text-sm font-semibold text-white disabled:opacity-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-forest">
                            <BusyIndicator active={form.processing} />{form.processing ? 'Menyimpan...' : 'Simpan perubahan'}
                        </button>
                        <button type="button" onClick={() => setEditing(false)} disabled={form.processing} className={actionClass}>Batal</button>
                    </div>
                </form>
            )}
        </article>
    );
}

export default function Index({ users, filters, status, errors }) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [pendingAction, setPendingAction] = useState(null);
    const [deleteTarget, setDeleteTarget] = useState(null);
    const form = useForm({ name: '', email: '', password: '' });

    const create = (event) => {
        event.preventDefault();

        if (form.processing) {
            return;
        }

        form.post(route('admin.users.store'), {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    const searchUsers = (event) => {
        event.preventDefault();
        router.get(route('admin.users.index'), search.trim() ? { search: search.trim() } : {}, { preserveState: true, replace: true });
    };

    const changeStatus = (user) => {
        if (pendingAction !== null) {
            return;
        }

        setPendingAction({ type: 'status', id: user.id });
        router.patch(route('admin.users.status', user.id), { is_active: !user.is_active }, {
            preserveScroll: true,
            onFinish: () => setPendingAction(null),
        });
    };

    const impersonate = (user) => {
        if (pendingAction !== null || !user.can_impersonate) {
            return;
        }

        setPendingAction({ type: 'impersonate', id: user.id });
        router.post(route('admin.users.impersonate', user.id), {}, {
            onFinish: () => setPendingAction(null),
        });
    };

    const deleteUser = () => {
        if (pendingAction !== null || deleteTarget === null) {
            return;
        }

        setPendingAction({ type: 'delete', id: deleteTarget.id });
        router.delete(route('admin.users.destroy', deleteTarget.id), {
            preserveScroll: true,
            onSuccess: () => setDeleteTarget(null),
            onError: () => setDeleteTarget(null),
            onFinish: () => setPendingAction(null),
        });
    };

    return (
        <AdminLayout title="Manajemen akun">
            <Head title="Manajemen akun" />
            {status && <p role="status" className="rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold text-forest">{status}</p>}
            {errors?.delete && <p role="alert" className="rounded-lg border border-red-300 bg-red-50 p-4 text-sm font-semibold text-red-800">{errors.delete}</p>}

            <section className="rounded-xl border border-stone-200 bg-white p-5 shadow-sm" aria-labelledby="create-user-title">
                <h2 id="create-user-title" className="text-lg font-bold text-forest">Buat akun petugas</h2>
                <p className="mt-1 text-sm text-stone-600">Admin menetapkan kata sandi awal dan menyampaikannya kepada petugas secara langsung.</p>
                <form onSubmit={create} className="mt-5 grid gap-4 md:grid-cols-3 md:items-end">
                    <div><label htmlFor="new-name" className="mb-1 block text-sm font-semibold">Nama</label><input id="new-name" required maxLength="255" value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} className={inputClass} />{form.errors.name && <p role="alert" className="mt-1 text-sm text-red-700">{form.errors.name}</p>}</div>
                    <div><label htmlFor="new-email" className="mb-1 block text-sm font-semibold">Email</label><input id="new-email" type="email" required maxLength="255" value={form.data.email} onChange={(event) => form.setData('email', event.target.value)} className={inputClass} />{form.errors.email && <p role="alert" className="mt-1 text-sm text-red-700">{form.errors.email}</p>}</div>
                    <div><label htmlFor="new-password" className="mb-1 block text-sm font-semibold">Kata sandi awal</label><input id="new-password" type="password" required minLength="8" autoComplete="new-password" value={form.data.password} onChange={(event) => form.setData('password', event.target.value)} className={inputClass} />{form.errors.password && <p role="alert" className="mt-1 text-sm text-red-700">{form.errors.password}</p>}</div>
                    <button type="submit" disabled={form.processing} aria-busy={form.processing} className="flex min-h-11 items-center justify-center gap-2 rounded-lg bg-forest px-5 font-semibold text-white disabled:opacity-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-forest"><BusyIndicator active={form.processing} />{form.processing ? 'Membuat akun...' : 'Buat akun'}</button>
                </form>
            </section>

            <section className="rounded-xl border border-stone-200 bg-white p-5 shadow-sm" aria-labelledby="user-list-title">
                <h2 id="user-list-title" className="text-lg font-bold text-forest">Akun petugas</h2>
                <p className="mt-1 text-sm text-stone-600">Nonaktifkan akun dan pastikan tidak ada data sensus sebelum menghapusnya. Data yang masih luring di perangkat harus dikirim lebih dulu.</p>
                <form onSubmit={searchUsers} className="mt-4 flex flex-wrap gap-2">
                    <label htmlFor="user-search" className="sr-only">Cari nama atau email</label>
                    <input id="user-search" type="search" maxLength="100" value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Cari nama atau email" className="min-h-11 min-w-56 flex-1 rounded-lg border-stone-300 focus:border-forest focus:ring-forest" />
                    <button type="submit" className="min-h-11 rounded-lg border border-forest px-4 font-semibold text-forest focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-forest">Cari</button>
                </form>
                <div className="mt-4">
                    {users.data.map((user) => (
                        <UserCard key={user.id} user={user} pendingAction={pendingAction} onStatusChange={changeStatus} onImpersonate={impersonate} onDelete={setDeleteTarget} />
                    ))}
                    {users.data.length === 0 && <p className="py-8 text-center text-sm text-stone-600">Belum ada akun yang cocok.</p>}
                </div>
                <div className="mt-5 flex items-center justify-between gap-3 text-sm">
                    <span className="text-stone-600">Halaman {users.current_page} dari {users.last_page}</span>
                    <div className="flex gap-2">
                        {users.prev_page_url && <Link href={users.prev_page_url} preserveState className={actionClass}>Sebelumnya</Link>}
                        {users.next_page_url && <Link href={users.next_page_url} preserveState className={actionClass}>Berikutnya</Link>}
                    </div>
                </div>
            </section>

            <Modal show={deleteTarget !== null} maxWidth="md" closeable={pendingAction?.type !== 'delete'} onClose={() => setDeleteTarget(null)}>
                <div className="space-y-4 p-6">
                    <DialogTitle as="h2" className="text-lg font-bold text-forest">Hapus akun petugas?</DialogTitle>
                    <p className="text-sm text-stone-700">Akun <strong>{deleteTarget?.name}</strong> akan dihapus permanen. Pastikan tidak ada data yang masih tersimpan di perangkat petugas.</p>
                    <div className="flex flex-wrap justify-end gap-2">
                        <button type="button" onClick={() => setDeleteTarget(null)} disabled={pendingAction?.type === 'delete'} className={actionClass}>Batal</button>
                        <button type="button" onClick={deleteUser} disabled={pendingAction?.type === 'delete'} className="min-h-11 rounded-lg bg-red-800 px-4 text-sm font-semibold text-white disabled:opacity-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-800">
                            {pendingAction?.type === 'delete' ? 'Menghapus...' : 'Hapus akun'}
                        </button>
                    </div>
                </div>
            </Modal>
        </AdminLayout>
    );
}
