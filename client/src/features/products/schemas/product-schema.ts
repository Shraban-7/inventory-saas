import { z } from "zod";

export const createVariantSchema = z.object({
  sku: z.string().min(1, "SKU is required").max(255, "SKU must be under 255 characters"),
  barcode: z.string().optional(),
  cost_price: z.preprocess((v) => Number(v || 0), z.number().min(0, "Cost price must be >= 0")),
  sale_price: z.preprocess((v) => Number(v || 0), z.number().min(0, "Sale price must be >= 0")),
  reorder_point: z.preprocess(
    (v) => (v === "" || v === undefined || v === null ? 0 : Number(v)),
    z.number().min(0, "Reorder point must be >= 0").default(0)
  ),
});

export const createProductSchema = z.object({
  category_id: z.preprocess((v) => Number(v || 1), z.number().min(1, "Category ID is required")),
  name: z.string().min(1, "Product name is required").max(255, "Product name must be under 255 characters"),
  description: z.string().optional(),
  costing_method: z.enum(["fifo", "avco"]).default("fifo"),
  variants: z.array(createVariantSchema).min(1, "At least one variant is required"),
});

export const addVariantSchema = createVariantSchema;

export type CreateProductFormValues = z.infer<typeof createProductSchema>;
export type CreateVariantFormValues = z.infer<typeof createVariantSchema>;
