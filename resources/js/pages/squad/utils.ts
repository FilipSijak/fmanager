export function formatMoney(value: number): string {
    return `£${Math.round(value).toLocaleString('en-US')}`;
}
