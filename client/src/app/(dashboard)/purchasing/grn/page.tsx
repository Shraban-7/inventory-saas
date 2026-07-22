"use client";

import * as React from "react";
import { Suspense } from "react";
import { useSearchParams } from "next/navigation";
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
import { Skeleton } from "@/components/ui/skeleton";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { EmptyState } from "@/components/shared/empty-state";
import { PermissionGuard } from "@/components/shared/permission-guard";
import {
  useGoodsReceiptNotesQuery,
  useCreateGoodsReceiptNoteMutation,
  useSuppliersQuery,
  mutationErrorToast,
} from "@/features/purchasing/api/purchasing-api";
import { createGrnSchema, CreateGrnFormValues } from "@/features/purchasing/schemas/purchasing-schemas";
import { useAuthStore } from "@/lib/stores/auth-store";
import { useShellStore } from "@/lib/stores/shell-store";
import { GRN_PREFILL_STORAGE_KEY, GrnPrefillPayload } from "@/types/purchasing";
import { parseProblemDetails, ProblemDetails } from "@/lib/api-client";
import {
  PackageCheck,
  Plus,
  Trash2,
  ShieldAlert,
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
            ? msgs.map((m) => (typeof m === "string" ? m : m.message)).join(", ")
            : String(msgs)}
        </li>
      ))}
    </ul>
  );
}

function GoodsReceiptNotesContent() {
  const searchParams = useSearchParams();
  const { activeBranchId } = useShellStore();
  const { branches } = useAuthStore();
  const [page, setPage] = React.useState(1);
  const { data: supplierResponse } = useSuppliersQuery({ per_page: 100 });
  const { data: response, isLoading, isError, error, refetch } = useGoodsReceiptNotesQuery({
    page,
    per_page: 25,
  });
  const createMutation = useCreateGoodsReceiptNoteMutation();

  const [isModalOpen, setIsModalOpen] = React.useState(false);
  const [apiError, setApiError] = React.useState<ProblemDetails | null>(null);
  const prefillApplied = React.useRef(false);

  const grns = response?.data || [];
  const meta = response?.meta;
  const suppliers = supplierResponse?.data || [];
  const today = new Date().toISOString().split("T")[0];

  const { register, control, handleSubmit, reset } = useForm({
    resolver: zodResolver(createGrnSchema),
    defaultValues: {
      branch_id: activeBranchId || branches[0]?.id || 1,
      supplier_id: suppliers[0]?.id || 1,
      purchase_order_id: undefined as number | undefined,
      received_at: today,
      notes: "",
      items: [{ variant_id: 1, quantity: 1, unit_cost: 1 }],
    },
  });

  const { fields, append, remove } = useFieldArray({ control, name: "items" });

  React.useEffect(() => {
    if (prefillApplied.current) return;

    let prefill: GrnPrefillPayload | null = null;
    try {
      const raw = sessionStorage.getItem(GRN_PREFILL_STORAGE_KEY);
      if (raw) {
        prefill = JSON.parse(raw) as GrnPrefillPayload;
        sessionStorage.removeItem(GRN_PREFILL_STORAGE_KEY);
      }
    } catch {
      prefill = null;
    }

    const poId = Number(searchParams.get("purchase_order_id") || prefill?.purchase_order_id || 0);
    const branchId = Number(
      searchParams.get("branch_id") || prefill?.branch_id || activeBranchId || branches[0]?.id || 1,
    );
    const supplierId = Number(
      searchParams.get("supplier_id") || prefill?.supplier_id || suppliers[0]?.id || 1,
    );

    if (poId > 0 || prefill) {
      prefillApplied.current = true;
      reset({
        branch_id: branchId,
        supplier_id: supplierId,
        purchase_order_id: poId || undefined,
        received_at: today,
        notes: "",
        items:
          prefill?.items?.length
            ? prefill.items.map((i) => ({
                variant_id: Number(i.variant_id),
                quantity: Number(i.quantity),
                unit_cost: Number(i.unit_cost),
              }))
            : [{ variant_id: 1, quantity: 1, unit_cost: 1 }],
      });
      setIsModalOpen(true);
    }
  }, [searchParams, activeBranchId, branches, suppliers, reset, today]);

  const onSubmit = async (values: CreateGrnFormValues) => {
    setApiError(null);
    try {
      const created = await createMutation.mutateAsync({
        branch_id: Number(values.branch_id),
        supplier_id: Number(values.supplier_id),
        purchase_order_id: values.purchase_order_id ? Number(values.purchase_order_id) : null,
        received_at: values.received_at,
        notes: values.notes || null,
        items: values.items.map((i) => ({
          variant_id: Number(i.variant_id),
          quantity: Number(i.quantity),
          unit_cost: Number(i.unit_cost),
        })),
      });
      toast.success(`GRN #${created.id} recorded. Stock levels updated.`);
      reset();
      setIsModalOpen(false);
      refetch();
    } catch (err: unknown) {
      const problem = parseProblemDetails(err);
      setApiError(problem);
      toast.error(mutationErrorToast(problem));
    }
  };

  const branchOptions = branches.map((b) => ({ label: b.name, value: b.id }));
  const supplierOptions = suppliers.map((s) => ({
    label: `${s.name} (#${s.id})`,
    value: s.id,
  }));

  return (
    <div className="space-y-6">
      <PageHeader
        title="Goods Receipt Notes (GRN)"
        description="Log received shipments. Idempotent POST updates stock and GRNI liability on the backend."
        actions={
          <Button onClick={() => setIsModalOpen(true)} className="gap-2 text-xs">
            <Plus className="h-4 w-4" /> Receive Shipment
          </Button>
        }
      />

      {isError && (
        <Alert variant="destructive">
          <ShieldAlert className="h-4 w-4" />
          <AlertTitle>Failed to load GRNs</AlertTitle>
          <AlertDescription>{parseProblemDetails(error).detail}</AlertDescription>
        </Alert>
      )}

      <Card>
        <CardHeader className="py-3 px-4 flex flex-row items-center justify-between border-b border-slate-200 dark:border-slate-800">
          <CardTitle className="text-sm font-semibold flex items-center gap-2">
            <PackageCheck className="h-4 w-4 text-teal-600" />
            Goods Receipt Notes
          </CardTitle>
          <Badge variant="outline" className="text-[10px]">
            purchase.receive
          </Badge>
        </CardHeader>
        <CardContent className="p-0">
          <Table dense>
            <TableHeader>
              <TableRow>
                <TableHead className="w-16">ID</TableHead>
                <TableHead>GRN Ref</TableHead>
                <TableHead>Linked PO</TableHead>
                <TableHead>Supplier</TableHead>
                <TableHead>Branch</TableHead>
                <TableHead>Received</TableHead>
                <TableHead>Notes</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {isLoading ? (
                Array.from({ length: 4 }).map((_, idx) => (
                  <TableRow key={idx}>
                    {Array.from({ length: 7 }).map((__, c) => (
                      <TableCell key={c}>
                        <Skeleton className="h-4 w-16" />
                      </TableCell>
                    ))}
                  </TableRow>
                ))
              ) : grns.length === 0 ? (
                <TableRow>
                  <TableCell colSpan={7} className="py-12">
                    <EmptyState
                      title="No GRNs recorded"
                      description="Receive a shipment from a confirmed PO or create a direct GRN."
                      actionLabel="Receive Shipment"
                      onAction={() => setIsModalOpen(true)}
                    />
                  </TableCell>
                </TableRow>
              ) : (
                grns.map((g) => (
                  <TableRow key={g.id}>
                    <TableCell className="font-mono text-xs font-semibold">#{g.id}</TableCell>
                    <TableCell className="font-mono font-bold text-xs">
                      {g.grn_number || `GRN-${g.id}`}
                    </TableCell>
                    <TableCell className="text-xs font-mono text-slate-500">
                      {g.purchase_order_id ? `PO #${g.purchase_order_id}` : "Direct"}
                    </TableCell>
                    <TableCell className="text-xs">
                      {g.supplier?.name || `Supplier #${g.supplier_id}`}
                    </TableCell>
                    <TableCell className="text-xs">Branch #{g.branch_id}</TableCell>
                    <TableCell className="text-xs font-mono text-slate-500">{g.received_at}</TableCell>
                    <TableCell className="text-xs text-slate-600 dark:text-slate-400">
                      {g.notes || "—"}
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
                  onClick={() => setPage((p) => Math.max(1, p - 1))}
                  className="gap-1 text-xs"
                >
                  <ChevronLeft className="h-3.5 w-3.5" /> Previous
                </Button>
                <Button
                  size="sm"
                  variant="outline"
                  disabled={meta.current_page >= meta.last_page}
                  onClick={() => setPage((p) => p + 1)}
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
        title="Receive Shipment (GRN)"
        description="Matches StoreGoodsReceiptNoteRequest. Idempotency-Key is attached automatically."
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

          <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
            <div className="space-y-1">
              <Label required>Branch</Label>
              <Select {...register("branch_id")} options={branchOptions} />
            </div>
            <div className="space-y-1">
              <Label required>Supplier</Label>
              <Select {...register("supplier_id")} options={supplierOptions} />
            </div>
            <div className="space-y-1">
              <Label>Linked PO ID (optional)</Label>
              <Input
                type="number"
                {...register("purchase_order_id")}
                placeholder="Optional"
                className="h-9 text-xs font-mono"
              />
            </div>
          </div>

          <div className="space-y-1">
            <Label required>Received At</Label>
            <Input type="date" {...register("received_at")} className="h-9 text-xs" />
          </div>

          <div className="space-y-1">
            <Label>Notes</Label>
            <Textarea {...register("notes")} rows={2} />
          </div>

          <div className="space-y-3 border-t border-slate-200 pt-3 dark:border-slate-800">
            <div className="flex items-center justify-between">
              <h4 className="text-xs font-semibold">Received Lines</h4>
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
                      Qty
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
            <Button type="submit" isLoading={createMutation.isPending}>
              Log GRN & Increase Stock
            </Button>
          </div>
        </form>
      </Dialog>
    </div>
  );
}

export default function GoodsReceiptNotesPage() {
  return (
    <PermissionGuard permission="purchase.receive" showBanner>
      <Suspense
        fallback={
          <div className="space-y-4 p-4">
            <Skeleton className="h-8 w-64" />
            <Skeleton className="h-48 w-full" />
          </div>
        }
      >
        <GoodsReceiptNotesContent />
      </Suspense>
    </PermissionGuard>
  );
}
