export type InvoiceStatus = "draft" | "issued" | "paid" | "partially_paid" | "voided";
export type CreditNoteStatus = "draft" | "approved" | "cancelled";
export type PaymentMethod = "cash" | "bank_transfer" | "card" | "cheque" | "other";

export interface CustomerAddress {
  street?: string;
  city?: string;
  state?: string;
  zip?: string;
  country?: string;
}

export interface Customer {
  id: number;
  default_branch_id?: number | null;
  name: string;
  email?: string | null;
  phone?: string | null;
  address?: CustomerAddress | null;
  created_at?: string;
  updated_at?: string;
}

export interface InvoiceItemPayload {
  variant_id: number;
  quantity: number;
  unit_price: number;
  tax_id?: number | null;
}

export interface InvoiceItem {
  id: number;
  invoice_id: number;
  product_variant_id: number;
  quantity: number | string;
  unit_price: number | string;
  subtotal?: number | string;
  variant?: {
    id: number;
    sku: string;
    barcode?: string | null;
    sale_price?: number | string;
  };
}

export interface Receipt {
  id: number;
  invoice_id: number;
  amount: number | string;
  payment_method: PaymentMethod;
  payment_date: string;
  reference?: string | null;
  created_at?: string;
}

export interface InvoicePayload {
  branch_id: number;
  customer_id: number;
  invoice_date: string;
  due_date?: string | null;
  notes?: string | null;
  items: InvoiceItemPayload[];
}

export interface Invoice {
  id: number;
  branch_id: number;
  customer_id: number;
  invoice_number?: string;
  invoice_date: string;
  due_date?: string | null;
  status: InvoiceStatus;
  total_amount?: number | string;
  paid_amount?: number | string;
  notes?: string | null;
  customer?: Customer;
  branch?: { id: number; name: string };
  items?: InvoiceItem[];
  receipts?: Receipt[];
  created_at?: string;
}

export interface RecordReceiptPayload {
  amount: number;
  payment_method: PaymentMethod;
  payment_date: string;
  reference?: string | null;
}

export interface CreditNoteItemPayload {
  variant_id: number;
  quantity: number;
  unit_price?: number | null;
  tax_id?: number | null;
}

export interface CreditNotePayload {
  branch_id: number;
  customer_id: number;
  invoice_id?: number | null;
  reason: string;
  items: CreditNoteItemPayload[];
}

export interface CreditNote {
  id: number;
  branch_id: number;
  customer_id: number;
  invoice_id?: number | null;
  credit_note_number?: string;
  reason: string;
  status: CreditNoteStatus;
  total_amount?: number | string;
  customer?: Customer;
  created_at?: string;
}

export interface ListInvoicesParams {
  status?: InvoiceStatus;
  date_from?: string;
  date_to?: string;
  customer_id?: number;
  per_page?: number;
  cursor?: string;
}

export interface CursorPaginatedResponse<T> {
  data: T[];
  path: string;
  per_page: number;
  next_cursor: string | null;
  prev_cursor: string | null;
}
