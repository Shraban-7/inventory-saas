import { z } from "zod";

export const customerSchema = z.object({
  name: z.string().min(1, "Customer name is required").max(255),
  email: z.string().email("Invalid email address").optional().or(z.literal("")),
  phone: z.string().max(50).optional(),
  default_branch_id: z.preprocess((v) => (v ? Number(v) : undefined), z.number().optional()),
});

export const invoiceItemSchema = z.object({
  variant_id: z.preprocess((v) => Number(v || 0), z.number().min(1, "Variant ID is required")),
  quantity: z.preprocess((v) => Number(v || 0), z.number().gt(0, "Quantity must be greater than 0")),
  unit_price: z.preprocess((v) => Number(v || 0), z.number().min(0, "Unit price must be >= 0")),
});

export const createInvoiceSchema = z.object({
  branch_id: z.preprocess((v) => Number(v || 0), z.number().min(1, "Branch ID is required")),
  customer_id: z.preprocess((v) => Number(v || 0), z.number().min(1, "Customer is required")),
  invoice_date: z.string().min(1, "Invoice date is required"),
  due_date: z.string().optional(),
  notes: z.string().max(5000).optional(),
  items: z.array(invoiceItemSchema).min(1, "At least one item is required"),
});

export const recordReceiptSchema = z.object({
  amount: z.preprocess((v) => Number(v || 0), z.number().gt(0, "Receipt amount must be greater than 0")),
  payment_method: z.enum(["cash", "bank_transfer", "card", "cheque", "other"]),
  payment_date: z.string().min(1, "Payment date is required"),
  reference: z.string().max(255).optional(),
});

export const creditNoteItemSchema = z.object({
  variant_id: z.preprocess((v) => Number(v || 0), z.number().min(1, "Variant ID is required")),
  quantity: z.preprocess((v) => Number(v || 0), z.number().gt(0, "Quantity must be greater than 0")),
  unit_price: z.preprocess((v) => (v !== "" && v !== undefined ? Number(v) : undefined), z.number().min(0).optional()),
});

export const createCreditNoteSchema = z.object({
  branch_id: z.preprocess((v) => Number(v || 0), z.number().min(1, "Branch ID is required")),
  customer_id: z.preprocess((v) => Number(v || 0), z.number().min(1, "Customer is required")),
  invoice_id: z.preprocess((v) => (v ? Number(v) : undefined), z.number().optional()),
  reason: z.string().min(1, "Reason is required").max(255),
  items: z.array(creditNoteItemSchema).min(1, "At least one item is required"),
});

export type CustomerFormValues = z.infer<typeof customerSchema>;
export type CreateInvoiceFormValues = z.infer<typeof createInvoiceSchema>;
export type RecordReceiptFormValues = z.infer<typeof recordReceiptSchema>;
export type CreateCreditNoteFormValues = z.infer<typeof createCreditNoteSchema>;
