import { z } from "zod";

const money = z.preprocess(
  (v) => (v === "" || v === null || v === undefined ? undefined : Number(v)),
  z.number().gte(0, "Must be >= 0"),
);

export const manualJournalLineSchema = z.object({
  coa_id: z.preprocess((v) => Number(v || 0), z.number().int().min(1, "Account is required")),
  debit: money,
  credit: money,
  description: z.string().max(1000).optional().or(z.literal("")),
});

/** Matches StoreManualJournalRequest */
export const createManualJournalSchema = z
  .object({
    branch_id: z.preprocess((v) => Number(v || 0), z.number().int().min(1, "Branch is required")),
    posted_at: z.string().regex(/^\d{4}-\d{2}-\d{2}$/, "Format must be YYYY-MM-DD"),
    description: z.string().min(1, "Description is required").max(1000),
    lines: z.array(manualJournalLineSchema).min(2, "At least two lines are required"),
  })
  .superRefine((data, ctx) => {
    data.lines.forEach((line, index) => {
      const debit = Number(line.debit || 0);
      const credit = Number(line.credit || 0);
      if (debit === 0 && credit === 0) {
        ctx.addIssue({
          code: z.ZodIssueCode.custom,
          message: "Each line needs a debit or credit",
          path: ["lines", index, "debit"],
        });
      }
      if (debit > 0 && credit > 0) {
        ctx.addIssue({
          code: z.ZodIssueCode.custom,
          message: "Use debit or credit on a line, not both",
          path: ["lines", index, "credit"],
        });
      }
    });
  });

export const lockPeriodSchema = z.object({
  accounting_period_id: z.preprocess(
    (v) => Number(v || 0),
    z.number().int().min(1, "Period ID is required"),
  ),
});

export type CreateManualJournalFormValues = z.infer<typeof createManualJournalSchema>;
export type LockPeriodFormValues = z.infer<typeof lockPeriodSchema>;

export function sumDebits(lines: Array<{ debit?: number | string }>): number {
  return lines.reduce((acc, line) => acc + Number(line.debit || 0), 0);
}

export function sumCredits(lines: Array<{ credit?: number | string }>): number {
  return lines.reduce((acc, line) => acc + Number(line.credit || 0), 0);
}

export function isBalanced(
  lines: Array<{ debit?: number | string; credit?: number | string }>,
): boolean {
  const d = sumDebits(lines);
  const c = sumCredits(lines);
  return Math.abs(d - c) < 0.005 && d > 0;
}
