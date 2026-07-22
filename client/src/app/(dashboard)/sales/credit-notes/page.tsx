"use client";

import * as React from "react";
import { useForm, useFieldArray } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { PageHeader } from "@/components/shell/page-header";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select } from "@/components/ui/select";
import { Dialog } from "@/components/ui/dialog";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Skeleton } from "@/components/ui/skeleton";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { StatusBadge } from "@/components/shared/status-badge";
import { EmptyState } from "@/components/shared/empty-state";
import { PermissionGuard } from "@/components/shared/permission-guard";
import {
  useCreditNotesQuery,
  useCreateCreditNoteMutation,
  useApproveCreditNoteMutation,
  useCustomersQuery,
} from "@/features/sales/api/sales-api";
import { createCreditNoteSchema, CreateCreditNoteFormValues } from "@/features/sales/schemas/sales-schemas";
import { useAuthStore } from "@/lib/stores/auth-store";
import { useShellStore } from "@/lib/stores/shell-store";
import { formatCurrency } from "@/lib/utils";
import { parseProblemDetails, ProblemDetails } from "@/lib/api-client";
import { RotateCcw, Plus, Trash2, ShieldAlert, CheckCircle2 } from "lucide-react";
import { toast } from "sonner";

export default function CreditNotesPage() {
  const { activeBranchId } = useShellStore();
  const { branches } = useAuthStore();
  const { data: customerResponse } = useCustomersQuery();
  const { data: response, isLoading, refetch } = useCreditNotesQuery();
  const createMutation = useCreateCreditNoteMutation();
  const approveMutation = useApproveCreditNoteMutation();

  const [isModalOpen, setIsModalOpen] = React.useState(false);
  const [apiError, setApiError] = React.useState<ProblemDetails | null>(null);

  const creditNotes = response?.data || [];
  const customers = customerResponse?.data || [];

  const {
    register,
    control,
    handleSubmit,
    reset,
    formState: { errors },
  } = useForm({
    resolver: zodResolver(createCreditNoteSchema),
    defaultValues: {
      branch_id: activeBranchId || 1,
      customer_id: customers[0]?.id || 1,
      invoice_id: undefined,
      reason: "Customer sales return / damaged item",
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

  const onSubmit = async (values: CreateCreditNoteFormValues) => {
    setApiError(null);
    try {
      await createMutation.mutateAsync({
        branch_id: Number(values.branch_id),
        customer_id: Number(values.customer_id),
        invoice_id: values.invoice_id ? Number(values.invoice_id) : undefined,
        reason: values.reason,
        items: values.items.map((i) => ({
          variant_id: Number(i.variant_id),
          quantity: Number(i.quantity),
          unit_price: i.unit_price ? Number(i.unit_price) : undefined,
        })),
      });
      toast.success("Credit Note issue created in draft status!");
      reset();
      setIsModalOpen(false);
      refetch();
    } catch (err: unknown) {
      const problem = parseProblemDetails(err);
      setApiError(problem);
      toast.error(problem.title || "Failed to create credit note");
    }
  };

  const handleApprove = async (id: number) => {
    try {
      await approveMutation.mutateAsync(id);
      toast.success(`Credit Note #${id} approved!`);
      refetch();
    } catch (err: unknown) {
      const problem = parseProblemDetails(err);
      toast.error(problem.title || "Failed to approve credit note");
    }
  };

  const branchOptions = branches.map((b) => ({ label: b.name, value: b.id }));
  const customerOptions =
    customers.length > 0
      ? customers.map((c) => ({ label: `${c.name} (#${c.id})`, value: c.id }))
      : [{ label: "Default Customer (#1)", value: 1 }];

  return (
    <PermissionGuard permission="invoice.view" showBanner>
      <div className="space-y-6">
        <PageHeader
          title="Credit Notes & Sales Returns"
          description="Manage customer sales returns, credit note approvals, and linked invoice adjustments."
          actions={
            <PermissionGuard permission="invoice.void">
              <Button onClick={() => setIsModalOpen(true)} className="gap-2 text-xs">
                <Plus className="h-4 w-4" /> Issue Credit Note (Admin)
              </Button>
            </PermissionGuard>
          }
        />

        {/* Credit Notes Data Table */}
        <Card>
          <CardHeader className="py-3 px-4 flex flex-row items-center justify-between border-b border-slate-200 dark:border-slate-800">
            <CardTitle className="text-sm font-semibold flex items-center gap-2">
              <RotateCcw className="h-4 w-4 text-teal-600" />
              Credit Notes Journal
            </CardTitle>
          </CardHeader>
          <CardContent className="p-0">
            <Table dense>
              <TableHeader>
                <TableRow>
                  <TableHead className="w-16">ID</TableHead>
                  <TableHead>CN Reference</TableHead>
                  <TableHead>Linked Invoice</TableHead>
                  <TableHead>Customer</TableHead>
                  <TableHead>Reason</TableHead>
                  <TableHead>Total Amount</TableHead>
                  <TableHead>Status</TableHead>
                  <TableHead className="text-right">Actions</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {isLoading ? (
                  Array.from({ length: 4 }).map((_, idx) => (
                    <TableRow key={idx}>
                      <TableCell><Skeleton className="h-4 w-8" /></TableCell>
                      <TableCell><Skeleton className="h-4 w-24" /></TableCell>
                      <TableCell><Skeleton className="h-4 w-16" /></TableCell>
                      <TableCell><Skeleton className="h-4 w-32" /></TableCell>
                      <TableCell><Skeleton className="h-4 w-36" /></TableCell>
                      <TableCell><Skeleton className="h-4 w-20" /></TableCell>
                      <TableCell><Skeleton className="h-4 w-16" /></TableCell>
                      <TableCell className="text-right"><Skeleton className="h-8 w-16 ml-auto" /></TableCell>
                    </TableRow>
                  ))
                ) : creditNotes.length === 0 ? (
                  <TableRow>
                    <TableCell colSpan={8} className="py-12">
                      <EmptyState
                        title="No credit notes issued"
                        description="No sales returns or credit notes have been registered."
                        actionLabel="Issue Credit Note"
                        onAction={() => setIsModalOpen(true)}
                      />
                    </TableCell>
                  </TableRow>
                ) : (
                  creditNotes.map((cn) => (
                    <TableRow key={cn.id}>
                      <TableCell className="font-mono text-xs font-semibold">#{cn.id}</TableCell>
                      <TableCell className="font-mono font-bold text-xs text-slate-900 dark:text-slate-100">
                        {cn.credit_note_number || `CN-${cn.id}`}
                      </TableCell>
                      <TableCell className="text-xs font-mono text-slate-500">
                        {cn.invoice_id ? `Invoice #${cn.invoice_id}` : "Unlinked"}
                      </TableCell>
                      <TableCell className="text-xs">
                        {cn.customer?.name || `Customer #${cn.customer_id}`}
                      </TableCell>
                      <TableCell className="text-xs text-slate-600 dark:text-slate-400">{cn.reason}</TableCell>
                      <TableCell className="tabular-nums font-bold text-xs text-teal-700 dark:text-teal-400">
                        {formatCurrency(Number(cn.total_amount || 0))}
                      </TableCell>
                      <TableCell>
                        <StatusBadge status={cn.status} />
                      </TableCell>
                      <TableCell className="text-right">
                        {cn.status === "draft" && (
                          <PermissionGuard permission="invoice.void">
                            <Button
                              size="sm"
                              variant="outline"
                              onClick={() => handleApprove(cn.id)}
                              isLoading={approveMutation.isPending}
                              className="gap-1 text-xs text-emerald-600 hover:text-emerald-700"
                            >
                              <CheckCircle2 className="h-3.5 w-3.5" /> Approve
                            </Button>
                          </PermissionGuard>
                        )}
                      </TableCell>
                    </TableRow>
                  ))
                )}
              </TableBody>
            </Table>
          </CardContent>
        </Card>

        {/* Create Credit Note Modal */}
        <Dialog
          isOpen={isModalOpen}
          onClose={() => setIsModalOpen(false)}
          title="Issue New Credit Note (Admin Only)"
          description="Create a credit note for sales returns or adjustments. Requires Admin role ('invoice.void')."
          className="max-w-2xl"
        >
          <form onSubmit={handleSubmit(onSubmit)} className="space-y-4 pt-2">
            {apiError && (
              <Alert variant="destructive">
                <ShieldAlert className="h-4 w-4" />
                <AlertTitle>{apiError.title}</AlertTitle>
                <AlertDescription>{apiError.detail}</AlertDescription>
              </Alert>
            )}

            <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
              <div className="space-y-1">
                <Label required>Branch</Label>
                <Select {...register("branch_id")} options={branchOptions} />
              </div>

              <div className="space-y-1">
                <Label required>Customer</Label>
                <Select {...register("customer_id")} options={customerOptions} />
              </div>

              <div className="space-y-1">
                <Label>Invoice ID Link (Optional)</Label>
                <Input type="number" {...register("invoice_id")} placeholder="Optional Invoice ID" className="h-9 text-xs" />
              </div>
            </div>

            <div className="space-y-1">
              <Label required>Reason for Credit Note</Label>
              <Input {...register("reason")} placeholder="Damaged shipment, price error, return..." />
              {errors.reason && <span className="text-xs text-red-500">{errors.reason.message}</span>}
            </div>

            {/* Item Lines */}
            <div className="space-y-3 border-t border-slate-200 pt-3 dark:border-slate-800">
              <div className="flex items-center justify-between">
                <h4 className="text-xs font-semibold text-slate-900 dark:text-slate-100">Credit Items</h4>
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  onClick={() => append({ variant_id: 1, quantity: 1.0, unit_price: 10.0 })}
                  className="gap-1 text-xs"
                >
                  <Plus className="h-3 w-3" /> Add Item Line
                </Button>
              </div>

              <div className="space-y-2 max-h-48 overflow-y-auto pr-1">
                {fields.map((field, index) => (
                  <div key={field.id} className="grid grid-cols-1 sm:grid-cols-4 gap-2 rounded border border-slate-200 p-2 bg-slate-50 dark:border-slate-800 dark:bg-slate-900">
                    <div>
                      <Label className="text-[10px]" required>Variant ID</Label>
                      <Input type="number" {...register(`items.${index}.variant_id` as const)} className="h-7 text-xs font-mono" />
                    </div>
                    <div>
                      <Label className="text-[10px]" required>Quantity</Label>
                      <Input type="number" step="0.0001" {...register(`items.${index}.quantity` as const)} className="h-7 text-xs font-mono" />
                    </div>
                    <div>
                      <Label className="text-[10px]">Unit Price ($)</Label>
                      <Input type="number" step="0.01" {...register(`items.${index}.unit_price` as const)} className="h-7 text-xs font-mono" />
                    </div>
                    <div className="flex items-end justify-end">
                      {fields.length > 1 && (
                        <Button type="button" variant="ghost" size="icon" onClick={() => remove(index)} className="h-7 w-7 text-red-500">
                          <Trash2 className="h-3 w-3" />
                        </Button>
                      )}
                    </div>
                  </div>
                ))}
              </div>
            </div>

            <div className="flex justify-end gap-2 pt-2 border-t border-slate-200 dark:border-slate-800">
              <Button type="button" variant="outline" onClick={() => setIsModalOpen(false)}>
                Cancel
              </Button>
              <Button type="submit" isLoading={createMutation.isPending}>
                Create Draft Credit Note
              </Button>
            </div>
          </form>
        </Dialog>
      </div>
    </PermissionGuard>
  );
}
