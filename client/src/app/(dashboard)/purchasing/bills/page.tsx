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
import { Tabs, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Skeleton } from "@/components/ui/skeleton";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { StatusBadge } from "@/components/shared/status-badge";
import { EmptyState } from "@/components/shared/empty-state";
import { PermissionGuard } from "@/components/shared/permission-guard";
import {
  useBillsQuery,
  useCreateBillMutation,
  useApproveBillMutation,
  useRecordBillPaymentMutation,
  useSuppliersQuery,
  useGoodsReceiptNotesQuery,
  mutationErrorToast,
} from "@/features/purchasing/api/purchasing-api";
import {
  createBillSchema,
  CreateBillFormValues,
  recordBillPaymentSchema,
  RecordBillPaymentFormValues,
} from "@/features/purchasing/schemas/purchasing-schemas";
import { useAuthStore } from "@/lib/stores/auth-store";
import { useShellStore } from "@/lib/stores/shell-store";
import { ListBillsParams, BillStatus } from "@/types/purchasing";
import { formatCurrency } from "@/lib/utils";
import { parseProblemDetails, ProblemDetails } from "@/lib/api-client";
import {
  Receipt,
  Plus,
  Trash2,
  ShieldAlert,
  CheckCircle2,
  CreditCard,
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

export default function VendorBillsPage() {
  const { activeBranchId } = useShellStore();
  const { branches } = useAuthStore();
  const { data: supplierResponse } = useSuppliersQuery({ per_page: 100 });
  const { data: grnResponse } = useGoodsReceiptNotesQuery({ per_page: 50 });
  const [params, setParams] = React.useState<ListBillsParams>({
    per_page: 25,
    page: 1,
    status: undefined,
  });

  const { data: response, isLoading, isError, error, refetch } = useBillsQuery(params);
  const createMutation = useCreateBillMutation();
  const approveMutation = useApproveBillMutation();

  const [selectedBillId, setSelectedBillId] = React.useState<number | null>(null);
  const paymentMutation = useRecordBillPaymentMutation(selectedBillId || 0);

  const [isCreateModalOpen, setIsCreateModalOpen] = React.useState(false);
  const [isPaymentModalOpen, setIsPaymentModalOpen] = React.useState(false);
  const [createError, setCreateError] = React.useState<ProblemDetails | null>(null);
  const [paymentError, setPaymentError] = React.useState<ProblemDetails | null>(null);

  const bills = response?.data || [];
  const meta = response?.meta;
  const suppliers = supplierResponse?.data || [];
  const grns = grnResponse?.data || [];
  const today = new Date().toISOString().split("T")[0];

  const {
    register: registerBill,
    control: controlBill,
    handleSubmit: handleSubmitBill,
    reset: resetBill,
  } = useForm({
    resolver: zodResolver(createBillSchema),
    defaultValues: {
      branch_id: activeBranchId || branches[0]?.id || 1,
      supplier_id: suppliers[0]?.id || 1,
      grn_id: undefined as number | undefined,
      bill_number: "",
      bill_date: today,
      due_date: "",
      items: [{ variant_id: 1, quantity: 1, unit_cost: 1 }],
    },
  });

  const { fields, append, remove } = useFieldArray({
    control: controlBill,
    name: "items",
  });

  const {
    register: registerPayment,
    handleSubmit: handleSubmitPayment,
    reset: resetPayment,
    formState: { errors: paymentErrors },
  } = useForm({
    resolver: zodResolver(recordBillPaymentSchema),
    defaultValues: {
      amount: 1,
      payment_method: "bank_transfer" as const,
      payment_date: today,
      reference: "",
    },
  });

  const handleStatusChange = (val: string) => {
    setParams((prev) => ({
      ...prev,
      page: 1,
      status: val === "all" ? undefined : (val as BillStatus),
    }));
  };

  const onSubmitBill = async (values: CreateBillFormValues) => {
    setCreateError(null);
    try {
      const created = await createMutation.mutateAsync({
        branch_id: Number(values.branch_id),
        supplier_id: Number(values.supplier_id),
        grn_id: values.grn_id ? Number(values.grn_id) : null,
        bill_number: values.bill_number,
        bill_date: values.bill_date,
        due_date: values.due_date || null,
        items: values.items.map((i) => ({
          variant_id: Number(i.variant_id),
          quantity: Number(i.quantity),
          unit_cost: Number(i.unit_cost),
        })),
      });
      toast.success(`Vendor bill #${created.id} created (draft).`);
      resetBill();
      setIsCreateModalOpen(false);
      refetch();
    } catch (err: unknown) {
      const problem = parseProblemDetails(err);
      setCreateError(problem);
      toast.error(mutationErrorToast(problem));
    }
  };

  const handleApprove = async (id: number) => {
    try {
      await approveMutation.mutateAsync(id);
      toast.success(`Bill #${id} approved.`);
      refetch();
    } catch (err: unknown) {
      toast.error(mutationErrorToast(parseProblemDetails(err)));
    }
  };

  const onSubmitPayment = async (values: RecordBillPaymentFormValues) => {
    if (!selectedBillId) return;
    setPaymentError(null);
    try {
      await paymentMutation.mutateAsync({
        amount: Number(values.amount),
        payment_method: values.payment_method,
        payment_date: values.payment_date,
        reference: values.reference || null,
      });
      toast.success(`Payment recorded for bill #${selectedBillId}.`);
      resetPayment();
      setIsPaymentModalOpen(false);
      setSelectedBillId(null);
      refetch();
    } catch (err: unknown) {
      const problem = parseProblemDetails(err);
      setPaymentError(problem);
      toast.error(mutationErrorToast(problem));
    }
  };

  const branchOptions = branches.map((b) => ({ label: b.name, value: b.id }));
  const supplierOptions = suppliers.map((s) => ({
    label: `${s.name} (#${s.id})`,
    value: s.id,
  }));
  const grnOptions = [
    { label: "No linked GRN", value: "" },
    ...grns.map((g) => ({
      label: `${g.grn_number || `GRN-${g.id}`} (#${g.id})`,
      value: g.id,
    })),
  ];

  return (
    <PermissionGuard permission="purchase.create" showBanner>
      <div className="space-y-6">
        <PageHeader
          title="Vendor Bills & Payments"
          description="Create draft bills, approve AP, and record payments. Statuses: draft, approved, partially_paid, paid, cancelled."
          actions={
            <Button onClick={() => setIsCreateModalOpen(true)} className="gap-2 text-xs">
              <Plus className="h-4 w-4" /> Create Vendor Bill
            </Button>
          }
        />

        {isError && (
          <Alert variant="destructive">
            <ShieldAlert className="h-4 w-4" />
            <AlertTitle>Failed to load bills</AlertTitle>
            <AlertDescription>{parseProblemDetails(error).detail}</AlertDescription>
          </Alert>
        )}

        <Card>
          <CardHeader className="py-3 px-4 border-b border-slate-200 dark:border-slate-800">
            <CardTitle className="text-sm font-semibold flex items-center gap-2">
              <Receipt className="h-4 w-4 text-teal-600" />
              Bill Status Filter
            </CardTitle>
          </CardHeader>
          <CardContent className="p-4">
            <Tabs defaultValue="all" onValueChange={handleStatusChange}>
              <TabsList>
                <TabsTrigger value="all">All</TabsTrigger>
                <TabsTrigger value="draft">Draft</TabsTrigger>
                <TabsTrigger value="approved">Approved</TabsTrigger>
                <TabsTrigger value="partially_paid">Partially Paid</TabsTrigger>
                <TabsTrigger value="paid">Paid</TabsTrigger>
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
                  <TableHead>Bill #</TableHead>
                  <TableHead>GRN</TableHead>
                  <TableHead>Supplier</TableHead>
                  <TableHead>Bill Date</TableHead>
                  <TableHead>Total</TableHead>
                  <TableHead>Balance Due</TableHead>
                  <TableHead>Status</TableHead>
                  <TableHead className="text-right">Actions</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {isLoading ? (
                  Array.from({ length: 5 }).map((_, idx) => (
                    <TableRow key={idx}>
                      {Array.from({ length: 9 }).map((__, c) => (
                        <TableCell key={c}>
                          <Skeleton className="h-4 w-14" />
                        </TableCell>
                      ))}
                    </TableRow>
                  ))
                ) : bills.length === 0 ? (
                  <TableRow>
                    <TableCell colSpan={9} className="py-12">
                      <EmptyState
                        title="No vendor bills found"
                        description="Create a bill after receiving goods (optional grn_id)."
                        actionLabel="Create Vendor Bill"
                        onAction={() => setIsCreateModalOpen(true)}
                      />
                    </TableCell>
                  </TableRow>
                ) : (
                  bills.map((bill) => (
                    <TableRow key={bill.id}>
                      <TableCell className="font-mono text-xs font-semibold">#{bill.id}</TableCell>
                      <TableCell className="font-mono font-bold text-xs">
                        {bill.bill_number || `BILL-${bill.id}`}
                      </TableCell>
                      <TableCell className="text-xs font-mono text-slate-500">
                        {bill.grn_id ? `GRN #${bill.grn_id}` : "—"}
                      </TableCell>
                      <TableCell className="text-xs">
                        {bill.supplier?.name || `Supplier #${bill.supplier_id}`}
                      </TableCell>
                      <TableCell className="text-xs font-mono text-slate-500">
                        {bill.bill_date}
                      </TableCell>
                      <TableCell className="tabular-nums font-bold text-xs text-teal-700">
                        {formatCurrency(Number(bill.total_amount || 0))}
                      </TableCell>
                      <TableCell className="tabular-nums text-xs">
                        {formatCurrency(Number(bill.balance_due || 0))}
                      </TableCell>
                      <TableCell>
                        <StatusBadge status={bill.status} />
                      </TableCell>
                      <TableCell className="text-right">
                        <div className="flex justify-end gap-1 flex-wrap">
                          {bill.status === "draft" && (
                            <Button
                              size="sm"
                              variant="outline"
                              onClick={() => handleApprove(bill.id)}
                              isLoading={approveMutation.isPending}
                              className="gap-1 text-xs text-emerald-600"
                            >
                              <CheckCircle2 className="h-3.5 w-3.5" /> Approve
                            </Button>
                          )}
                          {(bill.status === "approved" || bill.status === "partially_paid") && (
                            <Button
                              size="sm"
                              onClick={() => {
                                setSelectedBillId(bill.id);
                                setPaymentError(null);
                                resetPayment({
                                  amount: Number(bill.balance_due || bill.total_amount || 1),
                                  payment_method: "bank_transfer",
                                  payment_date: today,
                                  reference: "",
                                });
                                setIsPaymentModalOpen(true);
                              }}
                              className="gap-1 text-xs"
                            >
                              <CreditCard className="h-3.5 w-3.5" /> Pay
                            </Button>
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
          isOpen={isCreateModalOpen}
          onClose={() => setIsCreateModalOpen(false)}
          title="Create Vendor Bill"
          description="Matches StoreBillRequest. tax_id omitted until GET /taxes exists."
          className="max-w-2xl"
        >
          <form onSubmit={handleSubmitBill(onSubmitBill)} className="space-y-4 pt-2">
            {createError && (
              <Alert variant="destructive">
                <ShieldAlert className="h-4 w-4" />
                <AlertTitle>{createError.title}</AlertTitle>
                <AlertDescription>
                  {createError.detail}
                  {fieldErrors(createError)}
                </AlertDescription>
              </Alert>
            )}

            <div className="flex items-center gap-2">
              <Badge variant="outline" className="text-[10px] text-amber-700">
                GET /taxes pending — line tax_id omitted
              </Badge>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
              <div className="space-y-1">
                <Label required>Bill Number</Label>
                <Input {...registerBill("bill_number")} placeholder="VENDOR-INV-1001" className="h-9 text-xs" />
              </div>
              <div className="space-y-1">
                <Label>Linked GRN (optional)</Label>
                <Select {...registerBill("grn_id")} options={grnOptions} />
              </div>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
              <div className="space-y-1">
                <Label required>Branch</Label>
                <Select {...registerBill("branch_id")} options={branchOptions} />
              </div>
              <div className="space-y-1">
                <Label required>Supplier</Label>
                <Select {...registerBill("supplier_id")} options={supplierOptions} />
              </div>
              <div className="space-y-1">
                <Label required>Bill Date</Label>
                <Input type="date" {...registerBill("bill_date")} className="h-9 text-xs" />
              </div>
            </div>

            <div className="space-y-1">
              <Label>Due Date (optional)</Label>
              <Input type="date" {...registerBill("due_date")} className="h-9 text-xs" />
            </div>

            <div className="space-y-3 border-t border-slate-200 pt-3 dark:border-slate-800">
              <div className="flex items-center justify-between">
                <h4 className="text-xs font-semibold">Bill Lines</h4>
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
                        {...registerBill(`items.${index}.variant_id` as const)}
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
                        {...registerBill(`items.${index}.quantity` as const)}
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
                        {...registerBill(`items.${index}.unit_cost` as const)}
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
              <Button type="button" variant="outline" onClick={() => setIsCreateModalOpen(false)}>
                Cancel
              </Button>
              <Button type="submit" isLoading={createMutation.isPending}>
                Create Draft Bill
              </Button>
            </div>
          </form>
        </Dialog>

        <Dialog
          isOpen={isPaymentModalOpen}
          onClose={() => setIsPaymentModalOpen(false)}
          title={`Record Payment — Bill #${selectedBillId}`}
          description="Payment methods: cash, bank_transfer, cheque, other (no card)."
        >
          <form onSubmit={handleSubmitPayment(onSubmitPayment)} className="space-y-4 pt-2">
            {paymentError && (
              <Alert variant="destructive">
                <ShieldAlert className="h-4 w-4" />
                <AlertTitle>{paymentError.title}</AlertTitle>
                <AlertDescription>
                  {paymentError.detail}
                  {fieldErrors(paymentError)}
                </AlertDescription>
              </Alert>
            )}

            <div className="space-y-1">
              <Label required>Amount</Label>
              <Input
                type="number"
                step="0.01"
                {...registerPayment("amount")}
                className="tabular-nums font-mono"
              />
              {paymentErrors.amount && (
                <span className="text-xs text-red-500">{paymentErrors.amount.message}</span>
              )}
            </div>

            <div className="space-y-1">
              <Label required>Payment Method</Label>
              <Select
                {...registerPayment("payment_method")}
                options={[
                  { label: "Bank Transfer", value: "bank_transfer" },
                  { label: "Cash", value: "cash" },
                  { label: "Cheque", value: "cheque" },
                  { label: "Other", value: "other" },
                ]}
              />
            </div>

            <div className="space-y-1">
              <Label required>Payment Date</Label>
              <Input type="date" {...registerPayment("payment_date")} className="h-9 text-xs" />
            </div>

            <div className="space-y-1">
              <Label>Reference (optional)</Label>
              <Input {...registerPayment("reference")} placeholder="Cheque / bank ref" />
            </div>

            <div className="flex justify-end gap-2 pt-2">
              <Button type="button" variant="outline" onClick={() => setIsPaymentModalOpen(false)}>
                Cancel
              </Button>
              <Button type="submit" isLoading={paymentMutation.isPending}>
                Record Payment
              </Button>
            </div>
          </form>
        </Dialog>
      </div>
    </PermissionGuard>
  );
}
