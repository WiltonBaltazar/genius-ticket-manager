/** Single date, or a "start – end" range when the event spans more than one calendar day. */
export function formatEventDate(startDate: string, endDate: string | null): string {
    const start = new Date(startDate);
    const startFormatted = start.toLocaleDateString("pt", { dateStyle: "long" });

    if (!endDate) return startFormatted;

    const end = new Date(endDate);
    if (start.toDateString() === end.toDateString()) return startFormatted;

    const endFormatted = end.toLocaleDateString("pt", { dateStyle: "long" });
    return `${startFormatted} – ${endFormatted}`;
}
