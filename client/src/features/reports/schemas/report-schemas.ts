import { z } from "zod";

/** Matches QueueProfitAndLossReportRequest */
export const queueProfitAndLossSchema = z.object({
  start: z.string().regex(/^\d{4}-\d{2}-\d{2}$/, "Format must be YYYY-MM-DD"),
  end: z.string().regex(/^\d{4}-\d{2}-\d{2}$/, "Format must be YYYY-MM-DD"),
  branch_id: z.preprocess((v) => {
    if (v === "" || v === null || v === undefined) return undefined;
    const n = Number(v);
    return Number.isFinite(n) && n > 0 ? n : undefined;
  }, z.number().int().positive().optional()),
}).refine((data) => data.end >= data.start, {
  message: "End date must be on or after start date",
  path: ["end"],
});

export type QueueProfitAndLossFormValues = z.infer<typeof queueProfitAndLossSchema>;
