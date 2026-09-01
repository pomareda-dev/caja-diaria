export interface DebtData {
    id: number;
    name: string;
    principal_amount: number;
    disbursement_date: string;
    installment_amount: number;
    installments_count: number;
    payment_dates: string[];
    closed_at: string | null;
    rate_factor: number;
    total_to_pay: number;
    paid_installments: number;
    remaining: number;
    can_delete: boolean;
    is_active: boolean;
}
