import {
    ArrowRightOnRectangleIcon,
    Bars3Icon,
    ChartBarSquareIcon,
    DocumentMagnifyingGlassIcon,
    MapIcon,
    UserCircleIcon,
    UsersIcon,
    XMarkIcon,
} from '@heroicons/react/24/outline';
import { Link, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

const navigation = [
    { name: 'admin.dashboard', label: 'Monitoring sensus', icon: ChartBarSquareIcon },
    { name: 'admin.users.index', label: 'Manajemen akun', icon: UsersIcon },
];

export default function AdminLayout({ title, children }) {
    const user = usePage().props.auth.user;
    const [menuOpen, setMenuOpen] = useState(false);
    const openButtonRef = useRef(null);
    const closeButtonRef = useRef(null);

    const navigationClass = (active) => `flex min-h-12 items-center gap-3 rounded-xl px-4 text-sm font-semibold transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white ${active ? 'bg-white text-forest shadow-sm' : 'text-emerald-50 hover:bg-white/10 hover:text-white'}`;

    const closeMenu = () => {
        setMenuOpen(false);
        openButtonRef.current?.focus();
    };

    useEffect(() => {
        if (!menuOpen) {
            return;
        }

        closeButtonRef.current?.focus();
        const mobileViewport = window.matchMedia('(max-width: 1279px)');
        const previousOverflow = document.body.style.overflow;

        const updateScrollLock = () => {
            document.body.style.overflow = mobileViewport.matches ? 'hidden' : previousOverflow;

            if (!mobileViewport.matches) {
                setMenuOpen(false);
            }
        };

        const handleEscape = (event) => {
            if (event.key === 'Escape') {
                setMenuOpen(false);
                openButtonRef.current?.focus();
            }
        };

        updateScrollLock();
        mobileViewport.addEventListener('change', updateScrollLock);
        window.addEventListener('keydown', handleEscape);

        return () => {
            document.body.style.overflow = previousOverflow;
            mobileViewport.removeEventListener('change', updateScrollLock);
            window.removeEventListener('keydown', handleEscape);
        };
    }, [menuOpen]);

    return (
        <div className="min-h-screen bg-surface text-on-surface xl:grid xl:grid-cols-[248px_minmax(0,1fr)]">
            <a href="#admin-content" className="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-lg focus:bg-white focus:px-4 focus:py-3 focus:font-semibold focus:text-forest focus:outline focus:outline-2 focus:outline-forest">
                Lewati ke konten
            </a>

            {menuOpen && <button type="button" tabIndex={-1} aria-hidden="true" onClick={closeMenu} className="fixed inset-0 z-30 bg-stone-950/50 xl:hidden" />}

            <aside id="admin-navigation" className={`${menuOpen ? 'visible translate-x-0' : 'invisible -translate-x-full'} fixed inset-y-0 left-0 z-40 flex w-[min(18rem,calc(100vw-3rem))] flex-col bg-forest text-white shadow-xl transition-transform duration-200 motion-reduce:transition-none xl:sticky xl:top-0 xl:visible xl:h-screen xl:w-auto xl:translate-x-0 xl:shadow-none`}>
                <div className="flex items-center justify-between gap-4 px-5 py-4 xl:px-5 xl:pb-8 xl:pt-7">
                    <div className="flex min-w-0 items-center gap-3">
                        <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-terracotta-dark text-white shadow-sm" aria-hidden="true">
                            <MapIcon className="h-6 w-6" />
                        </span>
                        <div className="min-w-0 leading-tight">
                            <p className="text-[11px] font-bold uppercase tracking-[0.18em] text-emerald-100">SIPINTAR HUT</p>
                            <p className="mt-1 text-base font-semibold text-white">Ruang admin</p>
                        </div>
                    </div>
                    <button
                        ref={closeButtonRef}
                        type="button"
                        aria-label="Tutup menu admin"
                        onClick={closeMenu}
                        className="flex min-h-11 min-w-11 items-center justify-center rounded-lg border border-white/30 text-white transition-colors hover:bg-white/10 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white xl:hidden"
                    >
                        <XMarkIcon className="h-6 w-6" />
                    </button>
                </div>

                <div className="flex min-h-0 flex-1 flex-col overflow-y-auto border-t border-white/10 px-3 pb-6 pt-5 xl:border-0 xl:pt-0">
                    <nav aria-label="Navigasi admin">
                        <p className="px-4 pb-3 text-[11px] font-bold uppercase tracking-[0.16em] text-emerald-200">Menu utama</p>
                        <div className="flex flex-col gap-1">
                            {navigation.map(({ name, label, icon: Icon }) => {
                                const active = route().current(name);

                                return (
                                    <Link key={name} href={route(name)} onClick={() => setMenuOpen(false)} aria-current={active ? 'page' : undefined} className={navigationClass(active)}>
                                        <Icon className="h-5 w-5 shrink-0" aria-hidden="true" />
                                        <span className="min-w-0 flex-1">{label}</span>
                                    </Link>
                                );
                            })}
                            {route().has('log-viewer.index') && (
                                <a href={route('log-viewer.index')} onClick={() => setMenuOpen(false)} className={navigationClass(false)}>
                                    <DocumentMagnifyingGlassIcon className="h-5 w-5 shrink-0" aria-hidden="true" />
                                    <span className="min-w-0 flex-1">Log aplikasi</span>
                                </a>
                            )}
                        </div>
                    </nav>

                    <div className="mt-auto border-t border-white/15 pt-5">
                        <p className="px-4 pb-3 text-[11px] font-bold uppercase tracking-[0.16em] text-emerald-200">Akun</p>
                        <div className="flex flex-col gap-1">
                            <Link href={route('profile.edit')} onClick={() => setMenuOpen(false)} className={navigationClass(false)}>
                                <UserCircleIcon className="h-5 w-5 shrink-0" aria-hidden="true" />
                                Profil saya
                            </Link>
                            <Link href={route('logout')} method="post" as="button" className={`${navigationClass(false)} w-full text-left`}>
                                <ArrowRightOnRectangleIcon className="h-5 w-5 shrink-0" aria-hidden="true" />
                                Keluar
                            </Link>
                        </div>
                        <div className="mt-5 flex items-center gap-3 rounded-xl bg-white/10 px-3 py-3">
                            <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-emerald-100 font-bold text-forest" aria-hidden="true">
                                {user.name.trim().charAt(0).toUpperCase()}
                            </span>
                            <div className="min-w-0">
                                <p className="truncate text-sm font-semibold text-white">{user.name}</p>
                                <p className="truncate text-xs text-emerald-100">{user.email}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </aside>

            <div className="min-w-0">
                <header className="border-b border-emerald-900/10 bg-white px-4 py-4 sm:px-6 sm:py-5 xl:px-8 xl:py-7">
                    <div className="mx-auto flex max-w-7xl items-center gap-4 xl:block">
                        <button
                            ref={openButtonRef}
                            type="button"
                            aria-expanded={menuOpen}
                            aria-controls="admin-navigation"
                            onClick={() => setMenuOpen(true)}
                            className="flex min-h-11 shrink-0 items-center gap-2 rounded-lg border border-forest px-3 text-sm font-semibold text-forest transition-colors hover:bg-surface focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-forest xl:hidden"
                        >
                            <Bars3Icon className="h-5 w-5" aria-hidden="true" />
                            Menu
                        </button>
                        <div className="min-w-0">
                            <p className="hidden text-xs font-bold uppercase tracking-[0.16em] text-terracotta-dark xl:block">Administrasi / SIPINTAR HUT</p>
                            <h1 className="text-xl font-bold tracking-tight text-forest sm:text-2xl xl:mt-2 xl:text-3xl">{title}</h1>
                        </div>
                    </div>
                </header>
                <main id="admin-content" className="mx-auto max-w-7xl space-y-6 px-4 py-5 sm:px-6 sm:py-6 xl:px-8 xl:py-8">{children}</main>
            </div>
        </div>
    );
}
