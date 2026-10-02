export type Decimal = string;
export interface Contact {
    id: number;
    name: string;
    email?: string;
    phone?: string;
    address?: string;
    tax_number?: string;
    status: string;
}
export interface Account {
    id: number;
    code: string;
    name: string;
    type: string;
    is_active: boolean;
    parent_id?: number | null;
    cash_flow_class?: string;
}
export interface Item {
    id?: number;
    description: string;
    quantity: Decimal;
    unit_price: Decimal;
    discount: Decimal;
    account_id: number | string;
    tax_rate_id: number | string | null;
    subtotal?: Decimal;
    tax_amount?: Decimal;
    total?: Decimal;
}
export interface Document {
    id: number;
    customer_id?: number;
    supplier_id?: number;
    invoice_number?: string;
    bill_number?: string;
    issue_date?: string;
    bill_date?: string;
    due_date: string;
    status: string;
    currency: string;
    subtotal: Decimal;
    tax_total: Decimal;
    total: Decimal;
    paid_total: Decimal;
    outstanding: Decimal;
    notes?: string;
    customer?: Contact;
    supplier?: Contact;
    items: Item[];
    payments: {
        id: number;
        paid_at: string;
        amount: Decimal;
        reference?: string;
    }[];
}
export interface Paginated<T> {
    data: T[];
    total: number;
    from: number;
    to: number;
    links: { url: string | null; label: string; active: boolean }[];
}
export interface Company {
    id: number;
    name: string;
    currency: string;
}
