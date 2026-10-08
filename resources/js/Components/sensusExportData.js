const jakartaDateFormat = new Intl.DateTimeFormat('en-GB', {
    timeZone: 'Asia/Jakarta',
    year: 'numeric', month: '2-digit', day: '2-digit',
});

function jakartaDate(value) {
    if (!value) {
        return null;
    }

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return null;
    }

    const parts = Object.fromEntries(jakartaDateFormat.formatToParts(date)
        .filter((part) => part.type !== 'literal')
        .map((part) => [part.type, part.value]));

    return `${parts.year}-${parts.month}-${parts.day}`;
}

export function filterExportRecords(records, {
    search = '', startDate = '', endDate = '', recorderScope = 'all', currentUserId = null, selectedUserId = null,
} = {}) {
    const query = search.trim().toLowerCase();

    return records.filter((record) => {
        if (record.status !== 'synced' || !Number.isInteger(Number(record.id)) || Number(record.id) < 1) {
            return false;
        }

        if ((recorderScope === 'mine' && Number(record.created_by) !== Number(currentUserId))
            || (recorderScope === 'user' && Number(record.created_by) !== Number(selectedUserId))) {
            return false;
        }

        if (query && ![record.nama, record.no_hp, record.latitude, record.longitude]
            .some((value) => String(value ?? '').toLowerCase().includes(query))) {
            return false;
        }

        if (startDate || endDate) {
            const date = jakartaDate(record.captured_at);
            if (!date || (startDate && date < startDate) || (endDate && date > endDate)) {
                return false;
            }
        }

        return true;
    });
}
