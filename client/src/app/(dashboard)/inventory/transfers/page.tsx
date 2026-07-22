"use client";

import * as React from "react";
import { useForm, useFieldArray } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { PageHeader } from "@/components/shell/page-header";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select } from "@/components/ui/select";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { PermissionGuard } from "@/components/shared/permission-guard";
import { useCreateStockTransferMutation } from "@/features/inventory/api/inventory-api";
import { stockTransferSchema, StockTransferFormValues } from "@/features/inventory/schemas/inventory-schemas";
import { useAuthStore } from "@/lib/stores/auth-store";
import { useShellStore } from "@/lib/stores/shell-store";
import { parseProblemDetails, ProblemDetails } from "@/lib/api-client";
import { ArrowLeftRight, Plus, Trash2, ShieldAlert, CheckCircle2 } from "lucide-react";
import { toast } from "sonner";

export default function StockTransfersPage() {
  const { activeBranchId } = useShellStore();
  const { branches } = useAuthStore();
  const createMutation = useCreateStockTransferMutation();
  const [apiError, setApiError] = React.useState<ProblemDetails | null>(null);

  const defaultFromBranch = activeBranchId || branches[0]?.id || 1;
  const defaultToBranch = branches.find((b) => b.id !== defaultFromBranch)?.id || defaultFromBranch + 1;

  const {
    register,
    control,
    handleSubmit,
    reset,
    formState: { errors },
  } = useForm({
    resolver: zodResolver(stockTransferSchema),
    defaultValues: {
      from_branch_id: defaultFromBranch,
      to_branch_id: defaultToBranch,
      items: [
        {
          variant_id: 1,
          quantity: 5.0,
        },
      ],
    },
  });

  const { fields, append, remove } = useFieldArray({
    control,
    name: "items",
  });

  const onSubmit = async (values: StockTransferFormValues) => {
    setApiError(null);
    try {
      await createMutation.mutateAsync({
        from_branch_id: Number(values.from_branch_id),
        to_branch_id: Number(values.to_branch_id),
        items: values.items.map((i) => ({
          variant_id: Number(i.variant_id),
          quantity: Number(i.quantity),
        })),
      });
      toast.success("Stock transfer processed successfully!");
      reset({
        from_branch_id: defaultFromBranch,
        to_branch_id: defaultToBranch,
        items: [{ variant_id: 1, quantity: 1.0 }],
      });
    } catch (err: unknown) {
      const problem = parseProblemDetails(err);
      setApiError(problem);
      toast.error(problem.title || "Failed to process stock transfer");
    }
  };

  const branchOptions = branches.map((b) => ({ label: b.name, value: b.id }));

  return (
    <PermissionGuard permission="stock.transfer" showBanner>
      <div className="space-y-6">
        <PageHeader
          title="Inter-Branch Stock Transfers"
          description="Execute atomic stock transfers between branches with dual-branch row concurrency locks."
        />

        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          
          {/* Form */}
          <Card className="lg:col-span-2">
            <CardHeader>
              <CardTitle className="text-base flex items-center gap-2">
                <ArrowLeftRight className="h-5 w-5 text-teal-600" />
                Create Stock Transfer Manifest
              </CardTitle>
              <CardDescription>
                Source and Destination branches must be different.
              </CardDescription>
            </CardHeader>
            <CardContent>
              <form onSubmit={handleSubmit(onSubmit)} className="space-y-6">
                
                {apiError && (
                  <Alert variant="destructive">
                    <ShieldAlert className="h-4 w-4" />
                    <AlertTitle>{apiError.title}</AlertTitle>
                    <AlertDescription>
                      {apiError.detail}
                      {apiError.errors && (
                        <ul className="mt-1 list-disc pl-4 text-xs">
                          {Object.entries(apiError.errors).map(([field, msgs]) => (
                            <li key={field}>
                              <strong>{field}:</strong> {Array.isArray(msgs) ? msgs.join(", ") : msgs}
                            </li>
                          ))}
                        </ul>
                      )}
                    </AlertDescription>
                  </Alert>
                )}

                {/* Branches Grid */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div className="space-y-1">
                    <Label required>Source Branch (From)</Label>
                    <Select {...register("from_branch_id")} options={branchOptions} />
                    {errors.from_branch_id && (
                      <span className="text-xs text-red-500">{errors.from_branch_id.message}</span>
                    )}
                  </div>

                  <div className="space-y-1">
                    <Label required>Destination Branch (To)</Label>
                    <Select {...register("to_branch_id")} options={branchOptions} />
                    {errors.to_branch_id && (
                      <span className="text-xs text-red-500">{errors.to_branch_id.message}</span>
                    )}
                  </div>
                </div>

                {/* Transfer Line Items */}
                <div className="space-y-3 border-t border-slate-200 pt-4 dark:border-slate-800">
                  <div className="flex items-center justify-between">
                    <h4 className="text-sm font-semibold text-slate-900 dark:text-slate-100">
                      Transfer Line Items
                    </h4>
                    <Button
                      type="button"
                      variant="outline"
                      size="sm"
                      onClick={() => append({ variant_id: 1, quantity: 1.0 })}
                      className="gap-1 text-xs"
                    >
                      <Plus className="h-3.5 w-3.5" /> Add Item Line
                    </Button>
                  </div>

                  {errors.items?.message && (
                    <span className="text-xs text-red-500">{errors.items.message}</span>
                  )}

                  <div className="space-y-3 max-h-60 overflow-y-auto pr-1">
                    {fields.map((field, index) => (
                      <div
                        key={field.id}
                        className="grid grid-cols-1 sm:grid-cols-3 gap-3 rounded-md border border-slate-200 p-3 bg-slate-50 dark:border-slate-800 dark:bg-slate-900/50"
                      >
                        <div className="space-y-1 sm:col-span-1">
                          <Label className="text-xs" required>Variant ID</Label>
                          <Input
                            type="number"
                            {...register(`items.${index}.variant_id` as const)}
                            placeholder="Variant ID"
                            className="h-8 text-xs font-mono"
                          />
                          {errors.items?.[index]?.variant_id && (
                            <span className="text-[10px] text-red-500">{errors.items[index]?.variant_id?.message}</span>
                          )}
                        </div>

                        <div className="space-y-1 sm:col-span-1">
                          <Label className="text-xs" required>Transfer Quantity</Label>
                          <Input
                            type="number"
                            step="0.0001"
                            {...register(`items.${index}.quantity` as const)}
                            className="h-8 text-xs tabular-nums font-mono"
                          />
                          {errors.items?.[index]?.quantity && (
                            <span className="text-[10px] text-red-500">{errors.items[index]?.quantity?.message}</span>
                          )}
                        </div>

                        <div className="flex items-end justify-end">
                          {fields.length > 1 && (
                            <Button
                              type="button"
                              variant="ghost"
                              size="icon"
                              onClick={() => remove(index)}
                              className="h-8 w-8 text-red-500 hover:bg-red-50"
                            >
                              <Trash2 className="h-3.5 w-3.5" />
                            </Button>
                          )}
                        </div>
                      </div>
                    ))}
                  </div>
                </div>

                <div className="flex justify-end gap-3 pt-4 border-t border-slate-200 dark:border-slate-800">
                  <Button type="button" variant="outline" onClick={() => reset()}>
                    Reset Form
                  </Button>
                  <Button type="submit" isLoading={createMutation.isPending}>
                    Execute Inter-Branch Transfer
                  </Button>
                </div>

              </form>
            </CardContent>
          </Card>

          {/* Info Card */}
          <div className="space-y-4">
            <Card className="bg-slate-50 dark:bg-slate-900 border-slate-200 dark:border-slate-800">
              <CardHeader>
                <CardTitle className="text-xs font-bold uppercase tracking-wider text-slate-500">
                  Transfer Guarantees
                </CardTitle>
              </CardHeader>
              <CardContent className="space-y-3 text-xs text-slate-600 dark:text-slate-400">
                <div className="flex items-start gap-2">
                  <CheckCircle2 className="h-4 w-4 text-teal-600 shrink-0 mt-0.5" />
                  <span><strong>Atomic Execution:</strong> Decrements source branch stock and increments destination stock in a single DB transaction.</span>
                </div>
                <div className="flex items-start gap-2">
                  <CheckCircle2 className="h-4 w-4 text-teal-600 shrink-0 mt-0.5" />
                  <span><strong>Idempotent:</strong> Re-submitting identical transfer manifests with the same Idempotency-Key returns cached response.</span>
                </div>
              </CardContent>
            </Card>
          </div>

        </div>
      </div>
    </PermissionGuard>
  );
}
