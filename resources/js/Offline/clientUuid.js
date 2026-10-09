const UUID_PATTERN = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;

export function isValidClientUuid(value) {
    return typeof value === 'string' && UUID_PATTERN.test(value);
}

export function createClientUuid(cryptoProvider = globalThis.crypto) {
    if (typeof cryptoProvider?.randomUUID === 'function') {
        return cryptoProvider.randomUUID();
    }

    if (typeof cryptoProvider?.getRandomValues !== 'function') {
        throw new Error('Browser tidak dapat membuat ID sensus. Buka situs melalui HTTPS dan coba lagi.');
    }

    const bytes = cryptoProvider.getRandomValues(new Uint8Array(16));
    bytes[6] = (bytes[6] & 0x0f) | 0x40;
    bytes[8] = (bytes[8] & 0x3f) | 0x80;
    const hex = [...bytes].map((byte) => byte.toString(16).padStart(2, '0')).join('');

    return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
}

export function validOrNewClientUuid(value, cryptoProvider = globalThis.crypto) {
    return isValidClientUuid(value) ? value : createClientUuid(cryptoProvider);
}

export function repairQueuedCreateEntry(entry, cryptoProvider = globalThis.crypto) {
    if (entry.operation === 'update' || entry.status === 'synced' || isValidClientUuid(entry.client_uuid)) {
        return null;
    }

    return {
        ...entry,
        client_uuid: createClientUuid(cryptoProvider),
        status: 'pending',
        retryable: true,
        error: null,
    };
}
