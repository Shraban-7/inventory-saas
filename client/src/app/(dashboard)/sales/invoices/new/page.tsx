"use client";

import * as React from "react";
import { useForm, useFieldArray } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { useRouter } from "next/navigation";
import { PageHeader } from "@/components/shell/page-header";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { PermissionGuard } from "@/components/shared/permission-guard";
import { useCreateInvoiceMutation, useCustomersQuery } from "@/features/sales/api/sales-api";
import { createInvoiceSchema, CreateInvoiceFormValues } from "@/features/sales/schemas/sales-schemas";
import { useAuthStore } from "@/lib/stores/auth-store";
import { useShellStore } from "@/lib/stores/shell-store";
import { formatCurrency } from "@/lib/utils";
import { parseProblemDetails, ProblemDetails } from "@/lib/api-client";
import { Plus, Trash2, ShieldAlert, FileText } from "lucide-react";
import { toast } from "sonner";

export default function CreateInvoicePage() {
  const router = useRouter();
  const { activeBranchId } = useShellStore();
  const { branches } = useAuthStore();
  const { data: customerResponse } = useCustomersQuery();
  const createMutation = useCreateInvoiceMutation();
  const [apiError, setApiError] = React.useState<ProblemDetails | null>(null);

  const today = new Date().toISOString().split("T")[0];
  const customers = customerResponse?.data || [];

  const {
    register,
    control,
    handleSubmit,
    watch,
    formState: { errors },
  } = useForm({
    resolver: zodResolver(createInvoiceSchema),
    defaultValues: {
      branch_id: activeBranchId || 1,
      customer_id: customers[0]?.id || 1,
      invoice_date: today,
      due_date: "",
      notes: "",
      items: [
        {
          variant_id: 1,
          quantity: 1.0,
          unit_price: 25.0,
        },
      ],
    },
  });

  const { fields, append, remove } = useFieldArray({
    control,
    name: "items",
  });

  const watchedItems = watch("items") || [];

  const totalCalculated = watchedItems.reduce((acc, item) => {
    const q = Number(item.quantity || 0);
    const p = Number(item.unit_price || 0);
    return acc + q * p;
  }, 0);

  const onSubmit = async (values: CreateInvoiceFormValues) => {
    setApiError(null);
    try {
      const created = await createMutation.mutateAsync({
        branch_id: Number(values.branch_id),
        customer_id: Number(values.customer_id),
        invoice_date: values.invoice_date,
        due_date: values.due_date || null,
        notes: values.notes || null,
        items: values.items.map((i) => ({
          variant_id: Number(i.variant_id),
          quantity: Number(i.quantity),
          unit_price: Number(i.unit_price),
        })),
      });
      toast.success("Sales invoice issued successfully!");
      router.push(`/sales/invoices/${created.id}`);
    } catch (err: unknown) {
      const problem = parseProblemDetails(err);
      setApiError(problem);
      toast.error(problem.title || "Failed to create invoice");
    }
  };

  const branchOptions = branches.map((b) => ({ label: b.name, value: b.id }));
  const customerOptions =
    customers.length > 0
      ? customers.map((c) => ({ label: `${c.name} (#${c.id})`, value: c.id }))
      : [{ label: "Default Customer (#1)", value: 1 }];

  return (
    <PermissionGuard permission="invoice.create" showBanner>
      <div className="space-y-6">
        <PageHeader
          title="Issue Sales Invoice"
          description="Create a sales invoice for a client. Submitting triggers FIFO stock deduction on backend."
        />

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

          {/* Header Grid */}
          <Card>
            <CardHeader>
              <CardTitle className="text-base flex items-center gap-2">
                <FileText className="h-5 w-5 text-teal-600" />
                Invoice Header & Client Context
              </CardTitle>
            </CardHeader>
            <CardContent className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
              
              <div className="space-y-1">
                <Label required>Branch Context</Label>
                <Select {...register("branch_id")} options={branchOptions} />
                {errors.branch_id && <span className="text-xs text-red-500">{errors.branch_id.message}</span>}
              </div>

              <div className="space-y-1">
                <Label required>Customer Account</Label>
                <Select {...register("customer_id")} options={customerOptions} />
                {errors.customer_id && <span className="text-xs text-red-500">{errors.customer_id.message}</span>}
              </div>

              <div className="space-y-1">
                <Label required>Invoice Date</Label>
                <Input type="date" {...register("invoice_date")} className="h-9 text-xs" />
                {errors.invoice_date && <span className="text-xs text-red-500">{errors.invoice_date.message}</span>}
              </div>

              <div className="space-y-1">
                <Label>Due Date (Optional)</Label>
                <Input type="date" {...register("due_date")} className="h-9 text-xs" />
              </div>

            </CardContent>
          </Card>

          {/* Line Items Table */}
          <Card>
            <CardHeader className="flex flex-row items-center justify-between border-b border-slate-200 dark:border-slate-800">
              <div>
                <CardTitle className="text-base">Invoice Line Items</CardTitle>
                <CardDescription>Specify variant IDs and selling prices.</CardDescription>
              </div>
              <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={() => append({ variant_id: 1, quantity: 1.0, unit_price: 10.0 })}
                className="gap-1 text-xs"
              >
                <Plus className="h-3.5 w-3.5" /> Add Line Item
              </Button>
            </CardHeader>
            <CardContent className="p-4 space-y-4">
              
              {errors.items?.message && (
                <span className="text-xs text-red-500">{errors.items.message}</span>
              )}

              <div className="space-y-3">
                {fields.map((field, index) => {
                  const q = Number(watchedItems[index]?.quantity || 0);
                  const p = Number(watchedItems[index]?.unit_price || 0);
                  const lineTotal = q * p;

                  return (
                    <div
                      key={field.id}
                      className="grid grid-cols-1 sm:grid-cols-5 gap-3 rounded-md border border-slate-200 p-3 bg-slate-50 dark:border-slate-800 dark:bg-slate-900/50 items-end"
                    >
                      <div className="space-y-1">
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

                      <div className="space-y-1">
                        <Label className="text-xs" required>Quantity</Label>
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

                      <div className="space-y-1">
                        <Label className="text-xs" required>Unit Price ($)</Label>
                        <Input
                          type="number"
                          step="0.01"
                          {...register(`items.${index}.unit_price` as const)}
                          className="h-8 text-xs tabular-nums font-mono"
                        />
                        {errors.items?.[index]?.unit_price && (
                          <span className="text-[10px] text-red-500">{errors.items[index]?.unit_price?.message}</span>
                        )}
                      </div>

                      <div className="space-y-1">
                        <Label className="text-xs">Line Total</Label>
                        <div className="h-8 flex items-center px-3 font-bold text-xs tabular-nums text-teal-700 bg-teal-50 dark:bg-teal-950 dark:text-teal-300 rounded border border-teal-200 dark:border-teal-900">
                          {formatCurrency(lineTotal)}
                        </div>
                      </div>

                      <div className="flex justify-end">
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
                  );
                })}
              </div>

              {/* Totals Summary */}
              <div className="flex flex-col items-end pt-4 border-t border-slate-200 dark:border-slate-800 space-y-1">
                <div className="flex justify-between w-64 text-xs text-slate-500">
                  <span>Subtotal Amount:</span>
                  <span className="tabular-nums font-mono">{formatCurrency(totalCalculated)}</span>
                </div>
                <div className="flex justify-between w-64 text-xs text-slate-500">
                  <span>Tax Amount:</span>
                  <Badge variant="outline" className="text-[10px] text-amber-600">GET /taxes Pending</Badge>
                </div>
                <div className="flex justify-between w-64 text-base font-bold text-slate-900 dark:text-slate-100 pt-2 border-t border-slate-200 dark:border-slate-800">
                  <span>Total Amount:</span>
                  <span className="tabular-nums text-teal-700 dark:text-teal-400">{formatCurrency(totalCalculated)}</span>
                </div>
              </div>

            </CardContent>
          </Card>

          {/* Notes & Actions */}
          <Card>
            <CardContent className="p-4 space-y-4">
              <div className="space-y-1">
                <Label>Invoice Notes / Terms</Label>
                <Textarea {...register("notes")} placeholder="Enter payment terms, bank details, or delivery notes..." rows={2} />
              </div>

              <div className="flex justify-end gap-3 pt-2">
                <Button type="button" variant="outline" onClick={() => router.push("/sales/invoices")}>
                  Cancel
                </Button>
                <Button type="submit" isLoading={createMutation.isPending}>
                  Issue Invoice & Save
                </Button>
              </div>
            </CardContent>
          </Card>

        </form>
      </div>
    </PermissionGuard>
  );
}
