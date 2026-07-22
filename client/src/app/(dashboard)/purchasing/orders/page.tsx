"use client";

import * as React from "react";
import Link from "next/link";
import { useForm, useFieldArray } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { PageHeader } from "@/components/shell/page-header";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import { Dialog } from "@/components/ui/dialog";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Tabs, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Skeleton } from "@/components/ui/skeleton";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { StatusBadge } from "@/components/shared/status-badge";
import { EmptyState } from "@/components/shared/empty-state";
import { PermissionGuard } from "@/components/shared/permission-guard";
import {
  usePurchaseOrdersQuery,
  useCreatePurchaseOrderMutation,
  useConfirmPurchaseOrderMutation,
  useCancelPurchaseOrderMutation,
  useSuppliersQuery,
  mutationErrorToast,
} from "@/features/purchasing/api/purchasing-api";
import {
  createPurchaseOrderSchema,
  CreatePurchaseOrderFormValues,
} from "@/features/purchasing/schemas/purchasing-schemas";
import { useAuthStore } from "@/lib/stores/auth-store";
import { useShellStore } from "@/lib/stores/shell-store";
import { ListPurchaseOrdersParams, PurchaseOrderStatus } from "@/types/purchasing";
import { parseProblemDetails, ProblemDetails } from "@/lib/api-client";
import {
  ShoppingCart,
  Plus,
  Trash2,
  ShieldAlert,
  CheckCircle2,
  XCircle,
  Eye,
  ChevronLeft,
  ChevronRight,
} from "lucide-react";
import { toast } from "sonner";

function fieldErrors(problem: ProblemDetails) {
  if (!problem.errors) return null;
  return (
    <ul className="mt-1 list-disc pl-4 text-xs">
      {Object.entries(problem.errors).map(([field, msgs]) => (
        <li key={field}>
          <strong>{field}:</strong>{" "}
          {Array.isArray(msgs)
            ? msgs
                .map((m) => (typeof m === "string" ? m : m.message))
                .join(", ")
            : String(msgs)}
        </li>
      ))}
    </ul>
  );
}

export default function PurchaseOrdersPage() {
  const { activeBranchId } = useShellStore();
  const { branches } = useAuthStore();
  const { data: supplierResponse } = useSuppliersQuery({ per_page: 100 });
  const [params, setParams] = React.useState<ListPurchaseOrdersParams>({
    per_page: 25,
    page: 1,
    status: undefined,
  });

  const { data: response, isLoading, isError, error, refetch } = usePurchaseOrdersQuery(params);
  const createMutation = useCreatePurchaseOrderMutation();
  const confirmMutation = useConfirmPurchaseOrderMutation();
  const cancelMutation = useCancelPurchaseOrderMutation();

  const [isModalOpen, setIsModalOpen] = React.useState(false);
  const [apiError, setApiError] = React.useState<ProblemDetails | null>(null);

  const orders = response?.data || [];
  const meta = response?.meta;
  const suppliers = supplierResponse?.data || [];
  const today = new Date().toISOString().split("T")[0];

  const {
    register,
    control,
    handleSubmit,
    reset,
    formState: { errors },
  } = useForm({
    resolver: zodResolver(createPurchaseOrderSchema),
    defaultValues: {
      branch_id: activeBranchId || branches[0]?.id || 1,
      supplier_id: suppliers[0]?.id || 1,
      order_date: today,
      expected_date: "",
      notes: "",
      items: [{ variant_id: 1, quantity: 1, unit_cost: 1 }],
    },
  });

  const { fields, append, remove } = useFieldArray({ control, name: "items" });

  React.useEffect(() => {
    if (activeBranchId) {
      reset((prev) => ({ ...prev, branch_id: activeBranchId }));
    }
  }, [activeBranchId, reset]);

  const handleStatusChange = (val: string) => {
    setParams((prev) => ({
      ...prev,
      page: 1,
      status: val === "all" ? undefined : (val as PurchaseOrderStatus),
    }));
  };

  const onSubmit = async (values: CreatePurchaseOrderFormValues) => {
    setApiError(null);
    try {
      const created = await createMutation.mutateAsync({
        branch_id: Number(values.branch_id),
        supplier_id: Number(values.supplier_id),
        order_date: values.order_date,
        expected_date: values.expected_date || null,
        notes: values.notes || null,
        items: values.items.map((i) => ({
          variant_id: Number(i.variant_id),
          quantity: Number(i.quantity),
          unit_cost: Number(i.unit_cost),
        })),
      });
      toast.success(`Purchase order #${created.id} created (draft).`);
      reset();
      setIsModalOpen(false);
      refetch();
    } catch (err: unknown) {
      const problem = parseProblemDetails(err);
      setApiError(problem);
      toast.error(mutationErrorToast(problem));
    }
  };

  const handleConfirm = async (id: number) => {
    try {
      await confirmMutation.mutateAsync(id);
      toast.success(`Purchase order #${id} confirmed.`);
      refetch();
    } catch (err: unknown) {
      toast.error(mutationErrorToast(parseProblemDetails(err)));
    }
  };

  const handleCancel = async (id: number) => {
    try {
      await cancelMutation.mutateAsync(id);
      toast.success(`Purchase order #${id} cancelled.`);
      refetch();
    } catch (err: unknown) {
      toast.error(mutationErrorToast(parseProblemDetails(err)));
    }
  };

  const branchOptions = branches.map((b) => ({ label: b.name, value: b.id }));
  const supplierOptions =
    suppliers.length > 0
      ? suppliers.map((s) => ({ label: `${s.name} (#${s.id})`, value: s.id }))
      : [];

  return (
    <PermissionGuard permission="purchase.create" showBanner>
      <div className="space-y-6">
        <PageHeader
          title="Purchase Orders"
          description="Create, confirm, and cancel POs. Receive stock via GRN from the order detail."
          actions={
            <Button onClick={() => setIsModalOpen(true)} className="gap-2 text-xs">
              <Plus className="h-4 w-4" /> Issue Purchase Order
            </Button>
          }
        />

        {isError && (
          <Alert variant="destructive">
            <ShieldAlert className="h-4 w-4" />
            <AlertTitle>Failed to load purchase orders</AlertTitle>
            <AlertDescription>{parseProblemDetails(error).detail}</AlertDescription>
          </Alert>
        )}

        <Card>
          <CardHeader className="py-3 px-4 border-b border-slate-200 dark:border-slate-800">
            <CardTitle className="text-sm font-semibold flex items-center gap-2">
              <ShoppingCart className="h-4 w-4 text-teal-600" />
              Status Filter
            </CardTitle>
          </CardHeader>
          <CardContent className="p-4">
            <Tabs defaultValue="all" onValueChange={handleStatusChange}>
              <TabsList>
                <TabsTrigger value="all">All</TabsTrigger>
                <TabsTrigger value="draft">Draft</TabsTrigger>
                <TabsTrigger value="confirmed">Confirmed</TabsTrigger>
                <TabsTrigger value="partially_received">Partially Received</TabsTrigger>
                <TabsTrigger value="received">Received</TabsTrigger>
                <TabsTrigger value="cancelled">Cancelled</TabsTrigger>
              </TabsList>
            </Tabs>
          </CardContent>
        </Card>

        <Card>
          <CardContent className="p-0">
            <Table dense>
              <TableHeader>
                <TableRow>
                  <TableHead className="w-16">ID</TableHead>
                  <TableHead>PO Ref</TableHead>
                  <TableHead>Supplier</TableHead>
                  <TableHead>Branch</TableHead>
                  <TableHead>Order Date</TableHead>
                  <TableHead>Expected</TableHead>
                  <TableHead>Status</TableHead>
                  <TableHead className="text-right">Actions</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {isLoading ? (
                  Array.from({ length: 5 }).map((_, idx) => (
                    <TableRow key={idx}>
                      {Array.from({ length: 8 }).map((__, c) => (
                        <TableCell key={c}>
                          <Skeleton className="h-4 w-16" />
                        </TableCell>
                      ))}
                    </TableRow>
                  ))
                ) : orders.length === 0 ? (
                  <TableRow>
                    <TableCell colSpan={8} className="py-12">
                      <EmptyState
                        title="No purchase orders found"
                        description="No purchase orders match the active filter."
                        actionLabel="Issue Purchase Order"
                        onAction={() => setIsModalOpen(true)}
                      />
                    </TableCell>
                  </TableRow>
                ) : (
                  orders.map((po) => (
                    <TableRow key={po.id}>
                      <TableCell className="font-mono text-xs font-semibold">#{po.id}</TableCell>
                      <TableCell className="font-mono font-bold text-xs">
                        {po.po_number || `PO-${po.id}`}
                      </TableCell>
                      <TableCell className="text-xs">
                        {po.supplier?.name || `Supplier #${po.supplier_id}`}
                      </TableCell>
                      <TableCell className="text-xs">Branch #{po.branch_id}</TableCell>
                      <TableCell className="text-xs font-mono text-slate-500">{po.order_date}</TableCell>
                      <TableCell className="text-xs font-mono text-slate-500">
                        {po.expected_date || "—"}
                      </TableCell>
                      <TableCell>
                        <StatusBadge status={po.status} />
                      </TableCell>
                      <TableCell className="text-right">
                        <div className="flex justify-end gap-1 flex-wrap">
                          <Link href={`/purchasing/orders/${po.id}`}>
                            <Button size="sm" variant="ghost" className="gap-1 text-xs">
                              <Eye className="h-3.5 w-3.5" /> View
                            </Button>
                          </Link>
                          {po.status === "draft" && (
                            <>
                              <Button
                                size="sm"
                                variant="outline"
                                onClick={() => handleConfirm(po.id)}
                                isLoading={confirmMutation.isPending}
                                className="gap-1 text-xs text-emerald-600"
                              >
                                <CheckCircle2 className="h-3.5 w-3.5" /> Confirm
                              </Button>
                              <Button
                                size="sm"
                                variant="ghost"
                                onClick={() => handleCancel(po.id)}
                                isLoading={cancelMutation.isPending}
                                className="gap-1 text-xs text-red-500"
                              >
                                <XCircle className="h-3.5 w-3.5" /> Cancel
                              </Button>
                            </>
                          )}
                        </div>
                      </TableCell>
                    </TableRow>
                  ))
                )}
              </TableBody>
            </Table>

            {meta && meta.last_page > 1 && (
              <div className="flex items-center justify-between border-t border-slate-200 p-4 dark:border-slate-800">
                <span className="text-xs text-slate-500">
                  Page {meta.current_page} of {meta.last_page}
                </span>
                <div className="flex gap-2">
                  <Button
                    size="sm"
                    variant="outline"
                    disabled={meta.current_page <= 1}
                    onClick={() => setParams((p) => ({ ...p, page: (p.page || 1) - 1 }))}
                    className="gap-1 text-xs"
                  >
                    <ChevronLeft className="h-3.5 w-3.5" /> Previous
                  </Button>
                  <Button
                    size="sm"
                    variant="outline"
                    disabled={meta.current_page >= meta.last_page}
                    onClick={() => setParams((p) => ({ ...p, page: (p.page || 1) + 1 }))}
                    className="gap-1 text-xs"
                  >
                    Next <ChevronRight className="h-3.5 w-3.5" />
                  </Button>
                </div>
              </div>
            )}
          </CardContent>
        </Card>

        <Dialog
          isOpen={isModalOpen}
          onClose={() => setIsModalOpen(false)}
          title="Issue New Purchase Order"
          description="Payload matches StorePurchaseOrderRequest (expected_date, not expected_delivery_date)."
          className="max-w-2xl"
        >
          <form onSubmit={handleSubmit(onSubmit)} className="space-y-4 pt-2">
            {apiError && (
              <Alert variant="destructive">
                <ShieldAlert className="h-4 w-4" />
                <AlertTitle>{apiError.title}</AlertTitle>
                <AlertDescription>
                  {apiError.detail}
                  {fieldErrors(apiError)}
                </AlertDescription>
              </Alert>
            )}

            {supplierOptions.length === 0 && (
              <Alert variant="warning">
                <AlertTitle>No suppliers</AlertTitle>
                <AlertDescription>
                  Register a supplier before creating a purchase order.
                </AlertDescription>
              </Alert>
            )}

            <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
              <div className="space-y-1">
                <Label required>Branch</Label>
                <Select {...register("branch_id")} options={branchOptions} />
                {errors.branch_id && (
                  <span className="text-xs text-red-500">{errors.branch_id.message}</span>
                )}
              </div>
              <div className="space-y-1">
                <Label required>Supplier</Label>
                <Select {...register("supplier_id")} options={supplierOptions} />
                {errors.supplier_id && (
                  <span className="text-xs text-red-500">{errors.supplier_id.message}</span>
                )}
              </div>
              <div className="space-y-1">
                <Label required>Order Date</Label>
                <Input type="date" {...register("order_date")} className="h-9 text-xs" />
              </div>
            </div>

            <div className="space-y-1">
              <Label>Expected Date (optional)</Label>
              <Input type="date" {...register("expected_date")} className="h-9 text-xs" />
            </div>

            <div className="space-y-1">
              <Label>Notes</Label>
              <Textarea {...register("notes")} rows={2} />
            </div>

            <div className="space-y-3 border-t border-slate-200 pt-3 dark:border-slate-800">
              <div className="flex items-center justify-between">
                <h4 className="text-xs font-semibold">Order Line Items</h4>
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  onClick={() => append({ variant_id: 1, quantity: 1, unit_cost: 1 })}
                  className="gap-1 text-xs"
                >
                  <Plus className="h-3 w-3" /> Add Line
                </Button>
              </div>

              <div className="space-y-2 max-h-48 overflow-y-auto pr-1">
                {fields.map((field, index) => (
                  <div
                    key={field.id}
                    className="grid grid-cols-1 sm:grid-cols-4 gap-2 rounded border border-slate-200 p-2 bg-slate-50 dark:border-slate-800 dark:bg-slate-900"
                  >
                    <div>
                      <Label className="text-[10px]" required>
                        Variant ID
                      </Label>
                      <Input
                        type="number"
                        {...register(`items.${index}.variant_id` as const)}
                        className="h-7 text-xs font-mono"
                      />
                    </div>
                    <div>
                      <Label className="text-[10px]" required>
                        Quantity
                      </Label>
                      <Input
                        type="number"
                        step="0.0001"
                        {...register(`items.${index}.quantity` as const)}
                        className="h-7 text-xs font-mono"
                      />
                    </div>
                    <div>
                      <Label className="text-[10px]" required>
                        Unit Cost
                      </Label>
                      <Input
                        type="number"
                        step="0.0001"
                        {...register(`items.${index}.unit_cost` as const)}
                        className="h-7 text-xs font-mono"
                      />
                    </div>
                    <div className="flex items-end justify-end">
                      {fields.length > 1 && (
                        <Button
                          type="button"
                          variant="ghost"
                          size="icon"
                          onClick={() => remove(index)}
                          className="h-7 w-7 text-red-500"
                        >
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
              <Button
                type="submit"
                isLoading={createMutation.isPending}
                disabled={supplierOptions.length === 0}
              >
                Create Draft PO
              </Button>
            </div>
          </form>
        </Dialog>
      </div>
    </PermissionGuard>
  );
}
