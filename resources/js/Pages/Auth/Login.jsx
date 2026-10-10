import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import BusyIndicator from '../../Components/BusyIndicator';
import ApplicationLogo from '../../Components/ApplicationLogo';

export default function Login({ status, canResetPassword, googleLoginEnabled = false }) {
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
        <div className="relative flex min-h-screen min-h-[100dvh] flex-col items-center justify-center bg-surface text-on-surface">
            <Head title="Masuk" />
            
            {/* Subtle Dot Pattern Background */}
            <div 
                className="absolute inset-0 z-0 opacity-[0.15]" 
                style={{ 
                    backgroundImage: 'radial-gradient(#143E2C 1px, transparent 1px)', 
                    backgroundSize: '24px 24px' 
                }}
            ></div>

            <main className="relative z-10 w-full max-w-md px-4 py-4 sm:py-6">
                <div className="mb-4 flex flex-col items-center text-center">
                    <Link href="/">
                        <ApplicationLogo className="h-12 w-12 sm:h-14 sm:w-14" />
                    </Link>
                    <h1 className="mt-2 text-xl font-bold tracking-tight text-forest sm:text-2xl">Masuk ke SIPINTAR HUT</h1>
                    <p className="mt-1 text-xs leading-snug text-forest/70 sm:text-sm">
                        Sistem Informasi Pendataan dan Inventarisasi Hutan Sosial
                    </p>
                </div>

                <section aria-label="Formulir masuk" className="rounded-2xl border border-outline-variant/30 bg-surface-container-lowest p-4 shadow-[0_8px_30px_rgb(0,0,0,0.06)] sm:p-6">
                    {status && (
                        <p role="status" className="mb-3 rounded-xl bg-primary-fixed px-4 py-3 text-sm text-on-primary-fixed font-medium">
                            {status}
                        </p>
                    )}

                    {errors.google && (
                        <p role="alert" className="mb-3 rounded-xl bg-error/10 px-4 py-3 text-sm font-medium text-error">
                            {errors.google}
                        </p>
                    )}

                    <form onSubmit={submit} className="space-y-3">
                        <div>
                            <label htmlFor="email" className="mb-1 block text-sm font-semibold text-forest">
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
                            <div className="mb-1 flex items-center justify-between gap-3">
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
                            {processing ? 'Memverifikasi...' : 'Masuk ke Sistem'}
                        </button>
                    </form>

                    {googleLoginEnabled && (
                        <div className="mt-3">
                            <div className="mb-3 flex items-center gap-3" aria-hidden="true">
                                <span className="h-px flex-1 bg-outline-variant/60" />
                                <span className="text-xs font-medium text-on-surface-variant">atau</span>
                                <span className="h-px flex-1 bg-outline-variant/60" />
                            </div>
                            <a
                                href={route('login.google.redirect')}
                                className="flex min-h-12 w-full items-center justify-center gap-3 rounded-xl border border-outline-variant/70 bg-surface-container-lowest px-4 text-sm font-semibold text-forest transition-colors hover:bg-surface focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-forest"
                            >
                                <svg aria-hidden="true" width="20" height="20" viewBox="0 0 48 48">
                                    <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5Z" />
                                    <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.28 5.48-4.8 7.18l7.73 6C44.38 38.03 46.98 31.68 46.98 24.55Z" />
                                    <path fill="#FBBC05" d="M10.53 28.59A14.4 14.4 0 0 1 9.75 24c0-1.59.27-3.13.76-4.59l-7.98-6.2A23.9 23.9 0 0 0 0 24c0 3.87.93 7.52 2.56 10.78l7.97-6.19Z" />
                                    <path fill="#34A853" d="M24 48c6.47 0 11.9-2.13 15.87-5.8l-7.73-6c-2.15 1.45-4.92 2.3-8.14 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.2C6.51 42.62 14.62 48 24 48Z" />
                                </svg>
                                Masuk dengan Google
                            </a>
                        </div>
                    )}
                </section>
                
                <footer className="mt-4 hidden text-center text-xs text-on-surface-variant/60 sm:block">
                    &copy; {new Date().getFullYear()} SIPINTAR HUT. Seluruh hak cipta dilindungi.
                </footer>
            </main>
        </div>
    );
}
