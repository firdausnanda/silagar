function noticeKey(ownerId) {
    return `sensus-saved-notice:${Number(ownerId)}`;
}

export function rememberSavedSensusNotice(ownerId, clientUuid, storageProvider = () => window.sessionStorage) {
    try {
        storageProvider().setItem(noticeKey(ownerId), String(clientUuid));
        return true;
    } catch {
        return false;
    }
}

export function consumeSavedSensusNotice(ownerId, localEntries, storageProvider = () => window.sessionStorage) {
    try {
        const storage = storageProvider();
        const key = noticeKey(ownerId);
        const clientUuid = storage.getItem(key);

        if (!clientUuid) {
            return false;
        }

        storage.removeItem(key);

        return localEntries.some((entry) => Number(entry.owner_id) === Number(ownerId)
            && entry.client_uuid === clientUuid);
    } catch {
        return false;
    }
}
