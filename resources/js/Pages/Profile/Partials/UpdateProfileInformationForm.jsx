import InputError from '@/Components/InputError';
import { Link, useForm, usePage } from '@inertiajs/react';

export default function UpdateProfileInformation({ mustVerifyEmail, status }) {
    const user = usePage().props.auth.user;
    const { data, setData, patch, errors, processing, recentlySuccessful } = useForm({
        name: user.name,
        email: user.email,
    });

    const submit = (event) => {
        event.preventDefault();
        patch(route('profile.update'), { preserveScroll: true });
    };

    return (
        <div>
            <h2 className="text-lg font-bold text-forest">Informasi akun</h2>
            <p className="mt-1 text-sm text-stone-700">Perbarui nama dan alamat email yang digunakan untuk masuk.</p>

            <form onSubmit={submit} className="mt-5 space-y-4">
                <div>
                    <label htmlFor="name" className="mb-1 block text-sm font-semibold text-stone-800">Nama petugas</label>
                    <input id="name" className="field-input" value={data.name} onChange={(event) => setData('name', event.target.value)} required autoComplete="name" />
                    <InputError className="mt-2" message={errors.name} />
                </div>

                <div>
                    <label htmlFor="email" className="mb-1 block text-sm font-semibold text-stone-800">Alamat email</label>
                    <input id="email" type="email" className="field-input" value={data.email} onChange={(event) => setData('email', event.target.value)} required autoComplete="username" />
                    <InputError className="mt-2" message={errors.email} />
                </div>

                {mustVerifyEmail && user.email_verified_at === null && (
                    <div className="rounded-lg bg-amber-50 p-3 text-sm text-amber-950">
                        <p>Alamat email belum diverifikasi.</p>
                        <Link href={route('verification.send')} method="post" as="button" className="mt-1 min-h-10 font-semibold underline underline-offset-2 focus-visible:outline focus-visible:outline-2 focus-visible:outline-amber-950">
                            Kirim ulang tautan verifikasi
                        </Link>
                        {status === 'verification-link-sent' && <p role="status" className="mt-1">Tautan verifikasi telah dikirim ke email Anda.</p>}
                    </div>
                )}

                <div className="flex flex-wrap items-center gap-3 pt-1">
                    <button type="submit" disabled={processing} className="min-h-12 rounded-lg bg-forest px-5 font-semibold text-white disabled:opacity-60 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-forest">
                        {processing ? 'Menyimpan...' : 'Simpan profil'}
                    </button>
                    {recentlySuccessful && <p role="status" className="text-sm font-medium text-forest">Profil tersimpan.</p>}
                </div>
            </form>
        </div>
    );
}
