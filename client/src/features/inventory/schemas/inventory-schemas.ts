import { z } from "zod";

export const stockAdjustmentSchema = z.object({
  variant_id: z.preprocess((v) => Number(v || 0), z.number().min(1, "Variant ID is required")),
  branch_id: z.preprocess((v) => Number(v || 0), z.number().min(1, "Branch ID is required")),
  quantity_delta: z.preprocess((v) => Number(v || 0), z.number().gt(0, "Quantity delta must be greater than 0")),
  reason: z.string().min(1, "Reason is required").max(255, "Reason must be under 255 characters"),
  type: z.enum(["STOCK_ADJUSTMENT_IN", "STOCK_ADJUSTMENT_OUT"]),
});

export const transferItemSchema = z.object({
  variant_id: z.preprocess((v) => Number(v || 0), z.number().min(1, "Variant is required")),
  quantity: z.preprocess((v) => Number(v || 0), z.number().gt(0, "Quantity must be greater than 0")),
});

export const stockTransferSchema = z
  .object({
    from_branch_id: z.preprocess((v) => Number(v || 0), z.number().min(1, "Source branch is required")),
    to_branch_id: z.preprocess((v) => Number(v || 0), z.number().min(1, "Destination branch is required")),
    items: z.array(transferItemSchema).min(1, "At least one transfer item is required"),
  })
  .refine((data) => data.from_branch_id !== data.to_branch_id, {
    message: "Destination branch must be different from source branch",
    path: ["to_branch_id"],
  });

export type StockAdjustmentFormValues = z.infer<typeof stockAdjustmentSchema>;
export type StockTransferFormValues = z.infer<typeof stockTransferSchema>;
