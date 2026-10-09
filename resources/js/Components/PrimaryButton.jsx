import BusyIndicator from './BusyIndicator';

export default function PrimaryButton({
    className = '',
    disabled,
    busy = false,
    children,
    ...props
}) {
    return (
        <button
            {...props}
            className={
                `inline-flex items-center gap-2 rounded-md border border-transparent bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition-colors duration-150 ease-in-out hover:bg-gray-700 focus:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 active:bg-gray-900 ${
                    disabled && 'opacity-25'
                } ` + className
            }
            disabled={disabled}
            aria-busy={busy}
        >
            <BusyIndicator active={busy} />
            {children}
        </button>
    );
}
