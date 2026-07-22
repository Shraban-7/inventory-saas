export type PurchaseOrderStatus =
  | "draft"
  | "confirmed"
  | "partially_received"
  | "received"
  | "cancelled";

export type BillStatus = "draft" | "approved" | "partially_paid" | "paid" | "cancelled";

export type PurchasePaymentMethod = "cash" | "bank_transfer" | "cheque" | "other";

export interface SupplierAddress {
  [key: string]: string | undefined;
}

export interface Supplier {
  id: number;
  name: string;
  contact_name?: string | null;
  email?: string | null;
  phone?: string | null;
  address?: SupplierAddress | null;
  created_at?: string;
  updated_at?: string;
}

export interface PurchaseOrderItemPayload {
  variant_id: number;
  quantity: number | string;
  unit_cost: number | string;
}

export interface PurchaseOrderItem {
  id: number;
  variant_id: number;
  quantity_ordered: number | string;
  quantity_received: number | string;
  unit_cost: number | string;
}

export interface PurchaseOrderPayload {
  branch_id: number;
  supplier_id: number;
  order_date: string;
  expected_date?: string | null;
  notes?: string | null;
  items: PurchaseOrderItemPayload[];
}

export interface PurchaseOrder {
  id: number;
  branch_id: number;
  supplier_id: number;
  po_number?: string;
  order_date: string;
  expected_date?: string | null;
  status: PurchaseOrderStatus;
  notes?: string | null;
  supplier?: Supplier;
  items?: PurchaseOrderItem[];
  created_at?: string;
  updated_at?: string;
}

export interface GRNItemPayload {
  variant_id: number;
  quantity: number | string;
  unit_cost: number | string;
}

export interface GoodsReceiptNotePayload {
  branch_id: number;
  supplier_id: number;
  purchase_order_id?: number | null;
  received_at: string;
  notes?: string | null;
  items: GRNItemPayload[];
}

export interface GrnItem {
  id: number;
  variant_id: number;
  quantity_received: number | string;
  unit_cost: number | string;
}

export interface GoodsReceiptNote {
  id: number;
  branch_id: number;
  supplier_id: number;
  purchase_order_id?: number | null;
  grn_number?: string;
  received_at: string;
  notes?: string | null;
  supplier?: Supplier;
  purchase_order?: PurchaseOrder;
  items?: GrnItem[];
  created_at?: string;
}

export interface BillItemPayload {
  variant_id: number;
  quantity: number | string;
  unit_cost: number | string;
  tax_id?: number | null;
}

export interface BillPayload {
  branch_id: number;
  supplier_id: number;
  grn_id?: number | null;
  bill_number: string;
  bill_date: string;
  due_date?: string | null;
  items: BillItemPayload[];
}

export interface BillPayment {
  id: number;
  bill_id?: number;
  amount: number | string;
  payment_method: PurchasePaymentMethod;
  payment_date: string;
  reference?: string | null;
  created_at?: string;
}

export interface BillItem {
  id: number;
  variant_id: number;
  tax_id?: number | null;
  quantity: number | string;
  unit_cost: number | string;
  tax_rate_snapshot?: number | string | null;
  line_total?: number | string;
}

export interface Bill {
  id: number;
  branch_id: number;
  supplier_id: number;
  grn_id?: number | null;
  bill_number?: string;
  bill_date: string;
  due_date?: string | null;
  status: BillStatus;
  total_amount?: number | string;
  tax_amount?: number | string;
  balance_due?: number | string;
  supplier?: Supplier;
  goods_receipt_note?: GoodsReceiptNote;
  items?: BillItem[];
  payments?: BillPayment[];
  created_at?: string;
  updated_at?: string;
}

export interface RecordBillPaymentPayload {
  amount: number | string;
  payment_method: PurchasePaymentMethod;
  payment_date: string;
  reference?: string | null;
}

export interface ListPurchaseOrdersParams {
  status?: PurchaseOrderStatus;
  supplier_id?: number;
  per_page?: number;
  page?: number;
}

export interface ListBillsParams {
  status?: BillStatus;
  supplier_id?: number;
  per_page?: number;
  page?: number;
}

export interface ListSuppliersParams {
  per_page?: number;
  page?: number;
}

export interface ListGrnsParams {
  per_page?: number;
  page?: number;
}

/** sessionStorage key when navigating PO → GRN prefill */
export const GRN_PREFILL_STORAGE_KEY = "purchasing.grn.prefill";

export interface GrnPrefillPayload {
  purchase_order_id: number;
  branch_id: number;
  supplier_id: number;
  items: GRNItemPayload[];
}
