import {
    Dialog,
    DialogPanel,
    Transition,
    TransitionChild,
} from '@headlessui/react';

export default function Modal({
    children,
    show = false,
    maxWidth = '2xl',
    closeable = true,
    onClose = () => {},
}) {
    const close = () => {
        if (closeable) {
            onClose();
        }
    };

    const maxWidthClass = {
        sm: 'sm:max-w-sm',
        md: 'sm:max-w-md',
        lg: 'sm:max-w-lg',
        xl: 'sm:max-w-xl',
        '2xl': 'sm:max-w-2xl',
    }[maxWidth];

    return (
        <Transition show={show} leave="duration-150 motion-reduce:duration-0">
            <Dialog
                as="div"
                id="modal"
                className="fixed inset-0 z-50 flex items-center overflow-y-auto px-4 py-6 sm:px-0"
                onClose={close}
            >
                <TransitionChild
                    enter="transition-opacity ease-out duration-200 motion-reduce:duration-0"
                    enterFrom="opacity-0"
                    enterTo="opacity-100"
                    leave="transition-opacity ease-in duration-150 motion-reduce:duration-0"
                    leaveFrom="opacity-100"
                    leaveTo="opacity-0"
                >
                    <div className="absolute inset-0 bg-gray-500/75" />
                </TransitionChild>

                <TransitionChild
                    enter="transition-opacity ease-out duration-200 motion-reduce:duration-0"
                    enterFrom="opacity-0"
                    enterTo="opacity-100"
                    leave="transition-opacity ease-in duration-150 motion-reduce:duration-0"
                    leaveFrom="opacity-100"
                    leaveTo="opacity-0"
                >
                    <DialogPanel
                        className={`mb-6 overflow-hidden rounded-lg bg-white shadow-xl sm:mx-auto sm:w-full ${maxWidthClass}`}
                    >
                        {children}
                    </DialogPanel>
                </TransitionChild>
            </Dialog>
        </Transition>
    );
}
