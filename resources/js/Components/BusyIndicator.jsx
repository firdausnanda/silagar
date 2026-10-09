import { useEffect, useState } from 'react';

export default function BusyIndicator({ active, className = '' }) {
    const [visible, setVisible] = useState(false);

    useEffect(() => {
        if (!active) {
            setVisible(false);
            return;
        }

        const timer = window.setTimeout(() => setVisible(true), 250);
        return () => window.clearTimeout(timer);
    }, [active]);

    if (!active) {
        return null;
    }

    return (
        <span
            aria-hidden="true"
            className={`inline-block h-4 w-4 shrink-0 rounded-full border-2 border-current border-r-transparent motion-safe:animate-spin motion-reduce:animate-none ${visible ? '' : 'opacity-0'} ${className}`}
        />
    );
}
