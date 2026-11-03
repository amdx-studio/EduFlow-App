const formatter = new Intl.DateTimeFormat('id-ID', {
    timeZone: 'Asia/Jakarta',
    dateStyle: 'medium',
    timeStyle: 'short',
});

export function formatDateTime(iso) {
    return iso ? formatter.format(new Date(iso)) : '-';
}