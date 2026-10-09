import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import BusyIndicator from '../../Components/BusyIndicator';

export default function Login({ status, canResetPassword }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false,
    });
    const [showPassword, setShowPassword] = useState(false);

    const submit = (event) => {
        event.preventDefault();

        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <div className="min-h-screen bg-surface text-on-surface">
            <Head title="Masuk" />

            <header className="bg-primary-container px-4 pb-28 pt-8 text-white sm:pb-32 sm:pt-12">
                <div className="mx-auto max-w-md">
                    <p className="text-sm font-semibold tracking-wide text-emerald-100">SIPINTAR HUT</p>
                    <h1 className="mt-7 text-3xl font-bold leading-tight sm:text-4xl">Masuk ke akun Anda</h1>
                    <p className="mt-2 text-sm leading-6 text-emerald-100 sm:text-base">
                        Sistem Informasi Pendataan dan Inventarisasi Hutan Sosial
                    </p>
                </div>
            </header>

            <main className="mx-auto -mt-16 w-full max-w-md px-4 pb-10 sm:-mt-20">
                <section aria-label="Formulir masuk" className="rounded-xl border border-stone-200 bg-white p-5 shadow-sm sm:p-8">
                    {status && (
                        <p role="status" className="mb-5 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-forest">
                            {status}
                        </p>
                    )}

                    <form onSubmit={submit} className="space-y-5">
                        <div>
                            <label htmlFor="email" className="mb-2 block text-sm font-semibold text-forest">
                                Email
                            </label>
                            <input
                                id="email"
                                name="email"
                                type="email"
                                autoComplete="username"
                                required
                                className="field-input"
                                placeholder="nama@email.com"
                                value={data.email}
                                onChange={(event) => setData('email', event.target.value)}
                                aria-invalid={Boolean(errors.email)}
                                aria-describedby={errors.email ? 'email-error' : undefined}
                            />
                            {errors.email && <p id="email-error" role="alert" className="mt-2 text-sm text-red-700">{errors.email}</p>}
                        </div>

                        <div>
                            <div className="mb-2 flex items-center justify-between gap-3">
                                <label htmlFor="password" className="text-sm font-semibold text-forest">
                                    Kata sandi
                                </label>
                                {canResetPassword && (
                                    <Link href={route('password.request')} className="text-sm font-semibold text-forest underline-offset-2 hover:underline focus-visible:rounded-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-forest">
                                        Lupa kata sandi?
                                    </Link>
                                )}
                            </div>
                            <div className="relative">
                                <input
                                    id="password"
                                    name="password"
                                    type={showPassword ? 'text' : 'password'}
                                    autoComplete="current-password"
                                    required
                                    className="field-input pr-24"
                                    placeholder="Kata sandi"
                                    value={data.password}
                                    onChange={(event) => setData('password', event.target.value)}
                                    aria-invalid={Boolean(errors.password)}
                                    aria-describedby={errors.password ? 'password-error' : undefined}
                                />
                                <button
                                    type="button"
                                    onClick={() => setShowPassword((visible) => !visible)}
                                    aria-label={showPassword ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'}
                                    aria-pressed={showPassword}
                                    className="absolute inset-y-0 right-1 my-1 min-w-20 rounded-lg px-2 text-sm font-semibold text-forest hover:bg-emerald-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-forest"
                                >
                                    {showPassword ? 'Sembunyikan' : 'Tampilkan'}
                                </button>
                            </div>
                            {errors.password && <p id="password-error" role="alert" className="mt-2 text-sm text-red-700">{errors.password}</p>}
                        </div>

                        <label className="flex min-h-10 cursor-pointer items-center gap-3 text-sm text-stone-700">
                            <input
                                name="remember"
                                type="checkbox"
                                checked={data.remember}
                                onChange={(event) => setData('remember', event.target.checked)}
                                className="h-5 w-5 rounded border-stone-400 text-forest focus:ring-forest"
                            />
                            Ingat saya
                        </label>

                        <button
                            type="submit"
                            disabled={processing}
                            aria-busy={processing}
                            className="flex min-h-12 w-full items-center justify-center gap-2 rounded-xl bg-forest px-4 font-bold text-white shadow-sm transition-colors duration-150 hover:bg-forest-dark disabled:cursor-wait disabled:opacity-60 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-forest"
                        >
                            <BusyIndicator active={processing} />
                            {processing ? 'Memproses...' : 'Masuk'}
                        </button>
                    </form>
                </section>
            </main>
        </div>
    );
}
