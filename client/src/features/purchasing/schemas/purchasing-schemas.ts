import { z } from "zod";

const decimalPositive = z.preprocess(
  (v) => (v === "" || v === null || v === undefined ? undefined : Number(v)),
  z.number().gt(0, "Must be greater than 0"),
);

const optionalInt = z.preprocess((v) => {
  if (v === "" || v === null || v === undefined) return undefined;
  const n = Number(v);
  return Number.isFinite(n) ? n : undefined;
}, z.number().int().positive().optional());

export const supplierSchema = z.object({
  name: z.string().min(1, "Supplier name is required").max(255),
  contact_name: z.string().max(255).optional().or(z.literal("")),
  email: z.string().email("Invalid email address").max(254).optional().or(z.literal("")),
  phone: z.string().max(50).optional().or(z.literal("")),
});

export const purchaseOrderItemSchema = z.object({
  variant_id: z.preprocess((v) => Number(v || 0), z.number().int().min(1, "Variant ID is required")),
  quantity: decimalPositive,
  unit_cost: decimalPositive,
});

/** Matches StorePurchaseOrderRequest */
export const createPurchaseOrderSchema = z.object({
  branch_id: z.preprocess((v) => Number(v || 0), z.number().int().min(1, "Branch is required")),
  supplier_id: z.preprocess((v) => Number(v || 0), z.number().int().min(1, "Supplier is required")),
  order_date: z.string().regex(/^\d{4}-\d{2}-\d{2}$/, "Format must be YYYY-MM-DD"),
  expected_date: z
    .string()
    .regex(/^\d{4}-\d{2}-\d{2}$/, "Format must be YYYY-MM-DD")
    .optional()
    .or(z.literal("")),
  notes: z.string().max(5000).optional().or(z.literal("")),
  items: z.array(purchaseOrderItemSchema).min(1, "At least one item line is required"),
});

/** Matches StoreGoodsReceiptNoteRequest */
export const createGrnSchema = z.object({
  branch_id: z.preprocess((v) => Number(v || 0), z.number().int().min(1, "Branch is required")),
  supplier_id: z.preprocess((v) => Number(v || 0), z.number().int().min(1, "Supplier is required")),
  purchase_order_id: optionalInt,
  received_at: z.string().min(1, "Receipt date is required"),
  notes: z.string().max(5000).optional().or(z.literal("")),
  items: z.array(purchaseOrderItemSchema).min(1, "At least one item line is required"),
});

/** Matches StoreBillRequest — tax_id omitted (GET /taxes gap) */
export const createBillSchema = z.object({
  branch_id: z.preprocess((v) => Number(v || 0), z.number().int().min(1, "Branch is required")),
  supplier_id: z.preprocess((v) => Number(v || 0), z.number().int().min(1, "Supplier is required")),
  grn_id: optionalInt,
  bill_number: z.string().min(1, "Bill number is required").max(255),
  bill_date: z.string().regex(/^\d{4}-\d{2}-\d{2}$/, "Format must be YYYY-MM-DD"),
  due_date: z
    .string()
    .regex(/^\d{4}-\d{2}-\d{2}$/, "Format must be YYYY-MM-DD")
    .optional()
    .or(z.literal("")),
  items: z.array(purchaseOrderItemSchema).min(1, "At least one item line is required"),
});

/** Matches StoreBillPaymentRequest — purchase payment methods only (no card) */
export const recordBillPaymentSchema = z.object({
  amount: decimalPositive,
  payment_method: z.enum(["cash", "bank_transfer", "cheque", "other"]),
  payment_date: z.string().regex(/^\d{4}-\d{2}-\d{2}$/, "Format must be YYYY-MM-DD"),
  reference: z.string().max(255).optional().or(z.literal("")),
});

export type SupplierFormValues = z.infer<typeof supplierSchema>;
export type CreatePurchaseOrderFormValues = z.infer<typeof createPurchaseOrderSchema>;
export type CreateGrnFormValues = z.infer<typeof createGrnSchema>;
export type CreateBillFormValues = z.infer<typeof createBillSchema>;
export type RecordBillPaymentFormValues = z.infer<typeof recordBillPaymentSchema>;
