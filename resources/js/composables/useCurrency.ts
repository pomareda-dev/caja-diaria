export function useCurrency() {
    const format = (amount: number): string => {
        return new Intl.NumberFormat('es-PE', {
            style: 'currency',
            currency: 'PEN',
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        }).format(amount);
    };

    const formatSigned = (amount: number): string => {
        const formatted = format(Math.abs(amount));
        return amount < 0 ? `-${formatted}` : `+${formatted}`;
    };

    return { format, formatSigned };
}
