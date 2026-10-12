import axios from 'axios';
import { useRef, useState } from 'react';
import BusyIndicator from './BusyIndicator';
import Modal from './Modal';

export default function SensusImportModal({ show, onClose, onImported }) {
    const [file, setFile] = useState(null);
    const [importing, setImporting] = useState(false);
    const [error, setError] = useState('');
    const fileInput = useRef(null);

    const close = () => {
        if (importing) {
            return;
        }
        setFile(null);
        setError('');
        if (fileInput.current) {
            fileInput.current.value = '';
        }
        onClose();
    };

    const submit = async (event) => {
        event.preventDefault();
        if (!file || importing) {
            return;
        }

        setImporting(true);
        setError('');
        const data = new FormData();
        data.append('file', file);

        try {
            const response = await axios.post(route('sensus.import'), data, {
                headers: { Accept: 'application/json' },
                withXSRFToken: true,
            });
            onImported(response.data.imported);
            setFile(null);
            if (fileInput.current) {
                fileInput.current.value = '';
            }
            onClose();
        } catch (requestError) {
            setError(requestError.response?.data?.errors?.file?.[0]
                ?? (requestError.response?.status === 401 || requestError.response?.status === 419
                    ? 'Sesi berakhir. Masuk kembali lalu coba impor.'
                    : 'Impor belum berhasil. Periksa file dan koneksi, lalu coba lagi.'));
        } finally {
            setImporting(false);
        }
    };

    return (
        <Modal show={show} onClose={close} closeable={!importing} maxWidth="md">
            <form onSubmit={submit} className="space-y-5 p-5 sm:p-6">
                <div>
                    <h2 className="text-xl font-bold text-forest">Impor data dari Excel</h2>
                    <p className="mt-1 text-sm text-stone-700">Impor nama, nomor HP, luas, dan lama menggarap. Foto dan titik GPS dapat dilengkapi melalui Edit data setelah impor.</p>
                </div>
                <div className="rounded-lg border border-stone-200 bg-stone-50 p-3 text-sm text-stone-700">
                    <p className="font-semibold text-forest">Kolom yang diperlukan</p>
                    <p className="mt-1">Nama penggarap · Nomor HP · Luas garapan (Ha) · Lama menggarap (tahun)</p>
                    <a href={route('sensus.import.template')} className="mt-2 inline-block font-semibold text-forest underline underline-offset-2 focus-visible:outline focus-visible:outline-2 focus-visible:outline-forest">Unduh template Excel</a>
                </div>
                <div>
                    <label htmlFor="sensus-import-file" className="mb-1 block text-sm font-semibold">File Excel (.xlsx)</label>
                    <input ref={fileInput} id="sensus-import-file" type="file" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required onChange={(event) => { setFile(event.target.files?.[0] ?? null); setError(''); }} className="block w-full rounded-lg border border-stone-300 bg-white p-3 text-sm text-stone-800 file:mr-3 file:rounded-md file:border-0 file:bg-emerald-100 file:px-3 file:py-2 file:font-semibold file:text-forest focus-visible:outline focus-visible:outline-2 focus-visible:outline-forest" />
                    <p className="mt-1 text-xs text-stone-600">Maksimal 5 MB dan 1.000 baris data. Nomor HP harus berformat teks agar angka nol di depan tetap ada.</p>
                </div>
                {error && <p role="alert" className="rounded-lg bg-red-50 p-3 text-sm font-medium text-red-800">{error}</p>}
                <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <button type="button" onClick={close} disabled={importing} className="min-h-12 rounded-lg border border-forest px-4 font-semibold text-forest disabled:opacity-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-forest">Batal</button>
                    <button type="submit" disabled={!file || importing} className="flex min-h-12 items-center justify-center gap-2 rounded-lg bg-forest px-4 font-semibold text-white disabled:opacity-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-forest"><BusyIndicator active={importing} />{importing ? 'Mengimpor...' : 'Impor bidang'}</button>
                </div>
            </form>
        </Modal>
    );
}
