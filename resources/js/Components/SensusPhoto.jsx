import { useEffect, useState } from 'react';

export default function SensusPhoto({ file, src, alt = 'Foto lahan', className = '' }) {
    const [objectUrl, setObjectUrl] = useState(null);
    const [failed, setFailed] = useState(false);

    useEffect(() => {
        if (!(file instanceof Blob)) {
            setObjectUrl(null);
            return;
        }

        const url = URL.createObjectURL(file);
        setObjectUrl(url);
        return () => URL.revokeObjectURL(url);
    }, [file]);

    useEffect(() => setFailed(false), [file, src]);

    const imageUrl = objectUrl ?? src;
    if (imageUrl && !failed) {
        return <img src={imageUrl} alt={alt} className={`object-cover ${className}`} onError={() => setFailed(true)} />;
    }

    return (
        <div className={`flex items-center justify-center bg-emerald-100 text-forest ${className}`} aria-label="Foto belum tersedia luring">
            <svg className="h-8 w-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" aria-hidden="true">
                <rect x="3" y="4" width="18" height="16" rx="2" />
                <circle cx="8.5" cy="9" r="1.5" />
                <path d="m4 17 5-5 3 3 3-4 5 6" />
            </svg>
        </div>
    );
}
