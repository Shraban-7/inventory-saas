"use client";

import * as React from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useFieldArray, useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { PageHeader } from "@/components/shell/page-header";
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { PermissionGuard } from "@/components/shared/permission-guard";
import {
  useChartOfAccountsQuery,
  useCreateManualJournalMutation,
  accountingMutationToast,
} from "@/features/accounting/api/accounting-api";
import {
  createManualJournalSchema,
  CreateManualJournalFormValues,
  isBalanced,
  sumCredits,
  sumDebits,
} from "@/features/accounting/schemas/accounting-schemas";
import { flattenChartOfAccounts } from "@/types/accounting";
import { useAuthStore } from "@/lib/stores/auth-store";
import { useShellStore } from "@/lib/stores/shell-store";
import { formatCurrency } from "@/lib/utils";
import { parseProblemDetails, ProblemDetails } from "@/lib/api-client";
import { Plus, Trash2, ShieldAlert, ArrowLeft, Scale } from "lucide-react";
import { toast } from "sonner";

function fieldErrors(problem: ProblemDetails) {
  if (!problem.errors) return null;
  return (
    <ul className="mt-1 list-disc pl-4 text-xs">
      {Object.entries(problem.errors).map(([field, msgs]) => (
        <li key={field}>
          <strong>{field}:</strong>{" "}
          {Array.isArray(msgs)
            ? msgs.map((m) => (typeof m === "string" ? m : m.message)).join(", ")
            : String(msgs)}
        </li>
      ))}
    </ul>
  );
}

export default function NewManualJournalPage() {
  const router = useRouter();
  const { activeBranchId } = useShellStore();
  const { branches } = useAuthStore();
  const { data: coaRoots } = useChartOfAccountsQuery();
  const createMutation = useCreateManualJournalMutation();
  const [apiError, setApiError] = React.useState<ProblemDetails | null>(null);

  const today = new Date().toISOString().split("T")[0];
  const accountOptions = flattenChartOfAccounts(coaRoots || []).map((a) => ({
    label: a.label,
    value: a.id,
  }));

  const {
    register,
    control,
    handleSubmit,
    watch,
    formState: { errors },
  } = useForm({
    resolver: zodResolver(createManualJournalSchema),
    defaultValues: {
      branch_id: activeBranchId || branches[0]?.id || 1,
      posted_at: today,
      description: "",
      lines: [
        { coa_id: accountOptions[0]?.value || 1, debit: 100, credit: 0, description: "" },
        { coa_id: accountOptions[1]?.value || 1, debit: 0, credit: 100, description: "" },
      ],
    },
  });

  const { fields, append, remove } = useFieldArray({ control, name: "lines" });
  const watchedLines = (watch("lines") || []) as Array<{
    debit?: number | string;
    credit?: number | string;
  }>;
  const debitTotal = sumDebits(watchedLines);
  const creditTotal = sumCredits(watchedLines);
  const balanced = isBalanced(watchedLines);

  const onSubmit = async (raw: CreateManualJournalFormValues) => {
    const values = raw as CreateManualJournalFormValues;
    setApiError(null);
    if (!isBalanced(values.lines)) {
      toast.error("Debits must equal credits before submit.");
      return;
    }
    try {
      const created = await createMutation.mutateAsync({
        branch_id: Number(values.branch_id),
        posted_at: values.posted_at,
        description: values.description,
        lines: values.lines.map((line) => ({
          coa_id: Number(line.coa_id),
          debit: Number(line.debit || 0).toFixed(2),
          credit: Number(line.credit || 0).toFixed(2),
          description: line.description || null,
        })),
      });
      toast.success(`Manual journal #${created.id} posted.`);
      router.push(`/accounting/journals/${created.id}`);
    } catch (err: unknown) {
      const problem = parseProblemDetails(err);
      setApiError(problem);
      toast.error(accountingMutationToast(problem));
    }
  };

  const branchOptions = branches.map((b) => ({ label: b.name, value: b.id }));

  return (
    <PermissionGuard role="Accountant" showBanner>
      <div className="space-y-6">
        <PageHeader
          title="Post Manual Journal"
          description="Balanced double-entry journal. Requires Accountant role on the selected branch."
          actions={
            <Link href="/accounting/journals">
              <Button variant="outline" className="gap-2 text-xs">
                <ArrowLeft className="h-4 w-4" /> Cancel
              </Button>
            </Link>
          }
        />

        <form onSubmit={handleSubmit(onSubmit)} className="space-y-6">
          {apiError && (
            <Alert
              variant={
                apiError.type === "urn:problem:accounting-period-locked"
                  ? "warning"
                  : "destructive"
              }
            >
              <ShieldAlert className="h-4 w-4" />
              <AlertTitle>
                {apiError.type === "urn:problem:accounting-period-locked"
                  ? "Accounting period locked"
                  : apiError.title}
              </AlertTitle>
              <AlertDescription>
                {apiError.detail}
                {fieldErrors(apiError)}
              </AlertDescription>
            </Alert>
          )}

          <Card>
            <CardHeader>
              <CardTitle className="text-base">Journal Header</CardTitle>
              <CardDescription>Matches StoreManualJournalRequest field names.</CardDescription>
            </CardHeader>
            <CardContent className="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div className="space-y-1">
                <Label required>Branch</Label>
                <Select {...register("branch_id")} options={branchOptions} />
                {errors.branch_id && (
                  <span className="text-xs text-red-500">{errors.branch_id.message}</span>
                )}
              </div>
              <div className="space-y-1">
                <Label required>Posted At</Label>
                <Input type="date" {...register("posted_at")} className="h-9 text-xs" />
              </div>
              <div className="space-y-1 md:col-span-3">
                <Label required>Description</Label>
                <Textarea {...register("description")} rows={2} />
                {errors.description && (
                  <span className="text-xs text-red-500">{errors.description.message}</span>
                )}
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="flex flex-row items-center justify-between border-b border-slate-200 dark:border-slate-800">
              <div>
                <CardTitle className="text-base flex items-center gap-2">
                  <Scale className="h-4 w-4 text-teal-600" />
                  Journal Lines
                </CardTitle>
                <CardDescription>Minimum two lines. One-sided debit or credit per line.</CardDescription>
              </div>
              <Button
                type="button"
                variant="outline"
                size="sm"
                className="gap-1 text-xs"
                onClick={() =>
                  append({
                    coa_id: accountOptions[0]?.value || 1,
                    debit: 0,
                    credit: 0,
                    description: "",
                  })
                }
              >
                <Plus className="h-3.5 w-3.5" /> Add Line
              </Button>
            </CardHeader>
            <CardContent className="p-4 space-y-3">
              {errors.lines?.message && (
                <span className="text-xs text-red-500">{errors.lines.message}</span>
              )}

              {fields.map((field, index) => (
                <div
                  key={field.id}
                  className="grid grid-cols-1 sm:grid-cols-12 gap-2 rounded-md border border-slate-200 p-3 bg-slate-50 dark:border-slate-800 dark:bg-slate-900/40"
                >
                  <div className="sm:col-span-4 space-y-1">
                    <Label className="text-[10px]" required>
                      Account (coa_id)
                    </Label>
                    <Select
                      {...register(`lines.${index}.coa_id` as const)}
                      options={
                        accountOptions.length > 0
                          ? accountOptions
                          : [{ label: "Load CoA…", value: 1 }]
                      }
                      className="h-8 text-xs"
                    />
                  </div>
                  <div className="sm:col-span-2 space-y-1">
                    <Label className="text-[10px]">Debit</Label>
                    <Input
                      type="number"
                      step="0.01"
                      {...register(`lines.${index}.debit` as const)}
                      className="h-8 text-xs font-mono"
                    />
                  </div>
                  <div className="sm:col-span-2 space-y-1">
                    <Label className="text-[10px]">Credit</Label>
                    <Input
                      type="number"
                      step="0.01"
                      {...register(`lines.${index}.credit` as const)}
                      className="h-8 text-xs font-mono"
                    />
                  </div>
                  <div className="sm:col-span-3 space-y-1">
                    <Label className="text-[10px]">Line description</Label>
                    <Input
                      {...register(`lines.${index}.description` as const)}
                      className="h-8 text-xs"
                    />
                  </div>
                  <div className="sm:col-span-1 flex items-end justify-end">
                    {fields.length > 2 && (
                      <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="h-8 w-8 text-red-500"
                        onClick={() => remove(index)}
                      >
                        <Trash2 className="h-3.5 w-3.5" />
                      </Button>
                    )}
                  </div>
                </div>
              ))}

              <div className="flex flex-col items-end gap-1 pt-3 border-t border-slate-200 dark:border-slate-800">
                <div className="flex justify-between w-72 text-xs">
                  <span>Total Debits</span>
                  <span className="font-mono tabular-nums">{formatCurrency(debitTotal)}</span>
                </div>
                <div className="flex justify-between w-72 text-xs">
                  <span>Total Credits</span>
                  <span className="font-mono tabular-nums">{formatCurrency(creditTotal)}</span>
                </div>
                <Badge variant={balanced ? "success" : "warning"} className="mt-1">
                  {balanced ? "Balanced — ready to post" : "Out of balance — submit disabled"}
                </Badge>
              </div>
            </CardContent>
          </Card>

          <div className="flex justify-end gap-2">
            <Button type="button" variant="outline" onClick={() => router.push("/accounting/journals")}>
              Cancel
            </Button>
            <Button type="submit" isLoading={createMutation.isPending} disabled={!balanced}>
              Post Manual Journal
            </Button>
          </div>
        </form>
      </div>
    </PermissionGuard>
  );
}
