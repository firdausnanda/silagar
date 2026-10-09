import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import BusyIndicator from '../../Components/BusyIndicator';
import ApplicationLogo from '../../Components/ApplicationLogo';

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
        <div className="relative flex min-h-screen flex-col items-center justify-center bg-surface text-on-surface">
            <Head title="Masuk" />
            
            {/* Subtle Dot Pattern Background */}
            <div 
                className="absolute inset-0 z-0 opacity-[0.15]" 
                style={{ 
                    backgroundImage: 'radial-gradient(#143E2C 1px, transparent 1px)', 
                    backgroundSize: '24px 24px' 
                }}
            ></div>

            <main className="relative z-10 w-full max-w-md px-4 py-10 sm:px-0">
                <div className="mb-8 flex flex-col items-center text-center">
                    <Link href="/">
                        <ApplicationLogo className="h-20 w-20" />
                    </Link>
                    <h1 className="mt-6 text-2xl font-bold tracking-tight text-forest sm:text-3xl">Masuk ke SIPINTAR HUT</h1>
                    <p className="mt-2 text-sm text-forest/70">
                        Sistem Informasi Pendataan dan Inventarisasi Hutan Sosial
                    </p>
                </div>

                <section aria-label="Formulir masuk" className="rounded-2xl border border-outline-variant/30 bg-surface-container-lowest p-6 shadow-[0_8px_30px_rgb(0,0,0,0.06)] sm:p-10">
                    {status && (
                        <p role="status" className="mb-6 rounded-xl bg-primary-fixed px-4 py-3 text-sm text-on-primary-fixed font-medium">
                            {status}
                        </p>
                    )}

                    <form onSubmit={submit} className="space-y-6">
                        <div>
                            <label htmlFor="email" className="mb-2 block text-sm font-semibold text-forest">
                                Alamat Email
                            </label>
                            <input
                                id="email"
                                name="email"
                                type="email"
                                autoComplete="username"
                                required
                                className="field-input w-full bg-surface-container-lowest transition-shadow focus:ring-2 focus:ring-forest/20"
                                placeholder="nama@email.com"
                                value={data.email}
                                onChange={(event) => setData('email', event.target.value)}
                                aria-invalid={Boolean(errors.email)}
                                aria-describedby={errors.email ? 'email-error' : undefined}
                            />
                            {errors.email && <p id="email-error" role="alert" className="mt-2 text-sm font-medium text-error">{errors.email}</p>}
                        </div>

                        <div>
                            <div className="mb-2 flex items-center justify-between gap-3">
                                <label htmlFor="password" className="text-sm font-semibold text-forest">
                                    Kata Sandi
                                </label>
                                {canResetPassword && (
                                    <Link href={route('password.request')} className="text-sm font-semibold text-forest underline-offset-2 hover:text-forest-light hover:underline focus-visible:rounded-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-forest">
                                        Lupa sandi?
                                    </Link>
                                )}
                            </div>
                            <div className="relative flex items-center">
                                <input
                                    id="password"
                                    name="password"
                                    type={showPassword ? 'text' : 'password'}
                                    autoComplete="current-password"
                                    required
                                    className="field-input w-full bg-surface-container-lowest pr-12 transition-shadow focus:ring-2 focus:ring-forest/20"
                                    placeholder="••••••••"
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
                                    className="absolute right-2 flex h-8 w-8 items-center justify-center rounded-lg text-forest/70 hover:bg-surface hover:text-forest focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-forest"
                                >
                                    {showPassword ? (
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                            <path d="M9.88 9.88a3 3 0 1 0 4.24 4.24" />
                                            <path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68" />
                                            <path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61" />
                                            <line x1="2" x2="22" y1="2" y2="22" />
                                        </svg>
                                    ) : (
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                            <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z" />
                                            <circle cx="12" cy="12" r="3" />
                                        </svg>
                                    )}
                                </button>
                            </div>
                            {errors.password && <p id="password-error" role="alert" className="mt-2 text-sm font-medium text-error">{errors.password}</p>}
                        </div>

                        <label className="flex min-h-10 cursor-pointer items-center gap-3 text-sm font-medium text-on-surface-variant">
                            <input
                                name="remember"
                                type="checkbox"
                                checked={data.remember}
                                onChange={(event) => setData('remember', event.target.checked)}
                                className="h-5 w-5 rounded border-outline-variant text-forest transition-colors focus:ring-forest/30"
                            />
                            Ingat saya di perangkat ini
                        </label>

                        <button
                            type="submit"
                            disabled={processing}
                            aria-busy={processing}
                            className="flex min-h-12 w-full items-center justify-center gap-2 rounded-xl bg-forest px-4 font-bold text-white shadow-md transition-all duration-200 hover:bg-forest-light hover:shadow-lg disabled:cursor-wait disabled:opacity-70 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-forest"
                        >
                            <BusyIndicator active={processing} />
                            {processing ? 'Memverifikasi...' : 'Masuk ke Dasbor'}
                        </button>
                    </form>
                </section>
                
                <footer className="mt-10 text-center text-xs text-on-surface-variant/60">
                    &copy; {new Date().getFullYear()} SIPINTAR HUT. Seluruh hak cipta dilindungi.
                </footer>
            </main>
        </div>
    );
}
