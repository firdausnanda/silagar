import InputError from '@/Components/InputError';
import { useForm } from '@inertiajs/react';
import { useRef } from 'react';
import BusyIndicator from '../../../Components/BusyIndicator';

export default function UpdatePasswordForm() {
    const passwordInput = useRef();
    const currentPasswordInput = useRef();
    const { data, setData, errors, put, reset, processing, recentlySuccessful } = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    const updatePassword = (event) => {
        event.preventDefault();
        put(route('password.update'), {
            preserveScroll: true,
            onSuccess: () => reset(),
            onError: (validationErrors) => {
                if (validationErrors.password) {
                    reset('password', 'password_confirmation');
                    passwordInput.current.focus();
                }
                if (validationErrors.current_password) {
                    reset('current_password');
                    currentPasswordInput.current.focus();
                }
            },
        });
    };

    return (
        <div>
            <h2 className="text-lg font-bold text-forest">Ubah kata sandi</h2>
            <p className="mt-1 text-sm text-stone-700">Gunakan kata sandi yang kuat agar akun tetap aman.</p>

            <form onSubmit={updatePassword} className="mt-5 space-y-4">
                <div>
                    <label htmlFor="current_password" className="mb-1 block text-sm font-semibold text-stone-800">Kata sandi saat ini</label>
                    <input id="current_password" ref={currentPasswordInput} type="password" className="field-input" value={data.current_password} onChange={(event) => setData('current_password', event.target.value)} autoComplete="current-password" required />
                    <InputError className="mt-2" message={errors.current_password} />
                </div>

                <div>
                    <label htmlFor="password" className="mb-1 block text-sm font-semibold text-stone-800">Kata sandi baru</label>
                    <input id="password" ref={passwordInput} type="password" className="field-input" value={data.password} onChange={(event) => setData('password', event.target.value)} autoComplete="new-password" required />
                    <InputError className="mt-2" message={errors.password} />
                </div>

                <div>
                    <label htmlFor="password_confirmation" className="mb-1 block text-sm font-semibold text-stone-800">Ulangi kata sandi baru</label>
                    <input id="password_confirmation" type="password" className="field-input" value={data.password_confirmation} onChange={(event) => setData('password_confirmation', event.target.value)} autoComplete="new-password" required />
                    <InputError className="mt-2" message={errors.password_confirmation} />
                </div>

                <div className="flex flex-wrap items-center gap-3 pt-1">
                    <button type="submit" disabled={processing} aria-busy={processing} className="flex min-h-12 items-center gap-2 rounded-lg bg-forest px-5 font-semibold text-white transition-colors duration-150 disabled:opacity-60 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-forest">
                        <BusyIndicator active={processing} />
                        {processing ? 'Menyimpan...' : 'Ubah kata sandi'}
                    </button>
                    {recentlySuccessful && <p role="status" className="text-sm font-medium text-forest">Kata sandi diperbarui.</p>}
                </div>
            </form>
        </div>
    );
}
