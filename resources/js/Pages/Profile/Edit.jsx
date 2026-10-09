import { Head, Link, usePage } from '@inertiajs/react';
import UpdatePasswordForm from './Partials/UpdatePasswordForm';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm';

export default function Edit({ mustVerifyEmail, status }) {
    const user = usePage().props.auth.user;

    return (
        <div className="min-h-screen bg-surface text-on-surface">
            <Head title="Profil" />
            <header className="bg-primary-container px-4 py-5 text-white">
                <div className="mx-auto flex max-w-3xl items-center justify-between gap-4">
                    <div>
                        <p className="text-sm text-emerald-100">SIPINTAR HUT</p>
                        <h1 className="text-xl font-bold">Profil petugas</h1>
                    </div>
                    <Link href={route('dashboard')} className="flex min-h-11 shrink-0 items-center rounded-lg border border-emerald-200 px-3 text-sm font-semibold text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white">
                        Kembali
                    </Link>
                </div>
            </header>

            <main className="mx-auto max-w-3xl space-y-5 px-4 py-5 pb-12">
                <section className="flex items-center gap-4 rounded-xl bg-white p-4 shadow-sm" aria-label="Akun saat ini">
                    <div className="flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-primary-fixed text-xl font-bold text-forest" aria-hidden="true">
                        {user.name.trim().charAt(0).toUpperCase()}
                    </div>
                    <div className="min-w-0">
                        <p className="break-words text-lg font-bold text-forest">{user.name}</p>
                        <p className="break-all text-sm text-stone-700">{user.email}</p>
                    </div>
                </section>

                <section className="rounded-xl bg-white p-5 shadow-sm sm:p-6">
                    <UpdateProfileInformationForm mustVerifyEmail={mustVerifyEmail} status={status} />
                </section>
                <section className="rounded-xl bg-white p-5 shadow-sm sm:p-6">
                    <UpdatePasswordForm />
                </section>
                <Link href={route('logout')} method="post" as="button" className="flex min-h-12 w-full items-center justify-center rounded-xl border border-forest bg-white px-4 font-semibold text-forest focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-forest">
                    Keluar dari akun
                </Link>
            </main>
        </div>
    );
}
