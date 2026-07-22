"use client";

import * as React from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { PageHeader } from "@/components/shell/page-header";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { StatusBadge } from "@/components/shared/status-badge";
import { PermissionGuard } from "@/components/shared/permission-guard";
import { useCreateStockAdjustmentMutation } from "@/features/inventory/api/inventory-api";
import { stockAdjustmentSchema, StockAdjustmentFormValues } from "@/features/inventory/schemas/inventory-schemas";
import { useAuthStore } from "@/lib/stores/auth-store";
import { useShellStore } from "@/lib/stores/shell-store";
import { parseProblemDetails, ProblemDetails } from "@/lib/api-client";
import { Sliders, ShieldAlert, CheckCircle2 } from "lucide-react";
import { toast } from "sonner";

export default function StockAdjustmentsPage() {
  const { activeBranchId } = useShellStore();
  const { branches } = useAuthStore();
  const createMutation = useCreateStockAdjustmentMutation();
  const [apiError, setApiError] = React.useState<ProblemDetails | null>(null);

  const {
    register,
    handleSubmit,
    reset,
    watch,
    formState: { errors },
  } = useForm({
    resolver: zodResolver(stockAdjustmentSchema),
    defaultValues: {
      variant_id: 1,
      branch_id: activeBranchId || 1,
      quantity_delta: 1.0,
      reason: "Physical count correction",
      type: "STOCK_ADJUSTMENT_IN" as const,
    },
  });

  const selectedType = watch("type");

  const onSubmit = async (values: StockAdjustmentFormValues) => {
    setApiError(null);
    try {
      await createMutation.mutateAsync({
        variant_id: Number(values.variant_id),
        branch_id: Number(values.branch_id),
        quantity_delta: Number(values.quantity_delta),
        reason: values.reason,
        type: values.type,
      });
      toast.success("Stock adjustment logged successfully!");
      reset({
        variant_id: 1,
        branch_id: activeBranchId || 1,
        quantity_delta: 1.0,
        reason: "",
        type: "STOCK_ADJUSTMENT_IN",
      });
    } catch (err: unknown) {
      const problem = parseProblemDetails(err);
      setApiError(problem);
      toast.error(problem.title || "Failed to submit stock adjustment");
    }
  };

  const branchOptions = branches.map((b) => ({ label: b.name, value: b.id }));

  return (
    <PermissionGuard permission="stock.adjust" showBanner>
      <div className="space-y-6">
        <PageHeader
          title="Manual Stock Adjustments"
          description="Log manual stock adjustments (In/Out) with audit reason codes and append-only ledger entries."
        />

        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          
          {/* Form Card */}
          <Card className="lg:col-span-2">
            <CardHeader>
              <CardTitle className="text-base flex items-center gap-2">
                <Sliders className="h-5 w-5 text-teal-600" />
                New Adjustment Entry
              </CardTitle>
              <CardDescription>
                Idempotency-Key headers are automatically injected for state-modifying requests.
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

                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  
                  {/* Adjustment Type */}
                  <div className="space-y-1">
                    <Label required>Adjustment Type</Label>
                    <Select
                      {...register("type")}
                      options={[
                        { label: "Adjustment IN (+) Increase Stock", value: "STOCK_ADJUSTMENT_IN" },
                        { label: "Adjustment OUT (-) Decrease Stock", value: "STOCK_ADJUSTMENT_OUT" },
                      ]}
                    />
                    <span className="text-[11px] text-slate-500">
                      Current selection: <StatusBadge status={selectedType} />
                    </span>
                  </div>

                  {/* Branch Selector */}
                  <div className="space-y-1">
                    <Label required>Target Branch</Label>
                    <Select {...register("branch_id")} options={branchOptions} />
                  </div>

                  {/* Variant ID */}
                  <div className="space-y-1">
                    <Label required>Product Variant ID</Label>
                    <Input
                      type="number"
                      {...register("variant_id")}
                      placeholder="Variant ID (e.g. 1)"
                      className="font-mono text-xs"
                    />
                    {errors.variant_id && <span className="text-xs text-red-500">{errors.variant_id.message}</span>}
                  </div>

                  {/* Quantity Delta */}
                  <div className="space-y-1">
                    <Label required>Quantity Delta (Positive Amount)</Label>
                    <Input
                      type="number"
                      step="0.0001"
                      {...register("quantity_delta")}
                      className="tabular-nums font-mono text-xs"
                    />
                    {errors.quantity_delta && (
                      <span className="text-xs text-red-500">{errors.quantity_delta.message}</span>
                    )}
                  </div>

                </div>

                {/* Reason */}
                <div className="space-y-1">
                  <Label required>Audit Reason Code / Note</Label>
                  <Textarea
                    {...register("reason")}
                    placeholder="e.g. Physical inventory count correction, damaged stock write-off..."
                    rows={3}
                  />
                  {errors.reason && <span className="text-xs text-red-500">{errors.reason.message}</span>}
                </div>

                <div className="flex justify-end gap-3 pt-2">
                  <Button type="button" variant="outline" onClick={() => reset()}>
                    Reset Form
                  </Button>
                  <Button type="submit" isLoading={createMutation.isPending}>
                    Submit Stock Adjustment
                  </Button>
                </div>

              </form>
            </CardContent>
          </Card>

          {/* Audit Rule Callouts */}
          <div className="space-y-4">
            <Card className="bg-slate-50 dark:bg-slate-900 border-slate-200 dark:border-slate-800">
              <CardHeader>
                <CardTitle className="text-xs font-bold uppercase tracking-wider text-slate-500">
                  Adjustment Protocol Rules
                </CardTitle>
              </CardHeader>
              <CardContent className="space-y-3 text-xs text-slate-600 dark:text-slate-400">
                <div className="flex items-start gap-2">
                  <CheckCircle2 className="h-4 w-4 text-teal-600 shrink-0 mt-0.5" />
                  <span><strong>Append-Only Ledger:</strong> Historical stock adjustments can never be updated or deleted.</span>
                </div>
                <div className="flex items-start gap-2">
                  <CheckCircle2 className="h-4 w-4 text-teal-600 shrink-0 mt-0.5" />
                  <span><strong>Signed Delta:</strong> Frontend automatically signs the delta (+/-) based on type.</span>
                </div>
                <div className="flex items-start gap-2">
                  <CheckCircle2 className="h-4 w-4 text-teal-600 shrink-0 mt-0.5" />
                  <span><strong>Row Lock Concurrency:</strong> Concurrency conflicts return RFC 7807 409 Conflict alerts.</span>
                </div>
              </CardContent>
            </Card>
          </div>

        </div>
      </div>
    </PermissionGuard>
  );
}
