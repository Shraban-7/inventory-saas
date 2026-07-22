"use client";

import * as React from "react";
import { useParams } from "next/navigation";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { PageHeader } from "@/components/shell/page-header";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select } from "@/components/ui/select";
import { Dialog } from "@/components/ui/dialog";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { StatusBadge } from "@/components/shared/status-badge";
import { Skeleton } from "@/components/ui/skeleton";
import { PermissionGuard } from "@/components/shared/permission-guard";
import {
  useInvoiceDetailQuery,
  useRecordReceiptMutation,
  useVoidInvoiceMutation,
} from "@/features/sales/api/sales-api";
import { recordReceiptSchema, RecordReceiptFormValues } from "@/features/sales/schemas/sales-schemas";
import { formatCurrency } from "@/lib/utils";
import { parseProblemDetails, ProblemDetails } from "@/lib/api-client";
import { CreditCard, Ban, ShieldAlert, Building2, User as UserIcon, Calendar, ArrowLeft } from "lucide-react";
import Link from "next/link";
import { toast } from "sonner";

export default function InvoiceDetailPage() {
  const params = useParams();
  const invoiceId = Number(params?.id || 0);

  const { data: invoice, isLoading, refetch } = useInvoiceDetailQuery(invoiceId);
  const recordReceiptMutation = useRecordReceiptMutation(invoiceId);
  const voidMutation = useVoidInvoiceMutation(invoiceId);

  const [isReceiptModalOpen, setIsReceiptModalOpen] = React.useState(false);
  const [isVoidModalOpen, setIsVoidModalOpen] = React.useState(false);
  const [voidReason, setVoidReason] = React.useState("Billing error / customer cancellation");
  const [apiError, setApiError] = React.useState<ProblemDetails | null>(null);

  const today = new Date().toISOString().split("T")[0];

  const {
    register,
    handleSubmit,
    reset,
    formState: { errors },
  } = useForm({
    resolver: zodResolver(recordReceiptSchema),
    defaultValues: {
      amount: 100.0,
      payment_method: "bank_transfer" as const,
      payment_date: today,
      reference: "",
    },
  });

  const onRecordReceipt = async (values: RecordReceiptFormValues) => {
    setApiError(null);
    try {
      await recordReceiptMutation.mutateAsync({
        amount: Number(values.amount),
        payment_method: values.payment_method,
        payment_date: values.payment_date,
        reference: values.reference || null,
      });
      toast.success("Receipt payment recorded successfully!");
      reset();
      setIsReceiptModalOpen(false);
      refetch();
    } catch (err: unknown) {
      const problem = parseProblemDetails(err);
      setApiError(problem);
      toast.error(problem.title || "Failed to record payment");
    }
  };

  const onVoidInvoice = async () => {
    setApiError(null);
    try {
      await voidMutation.mutateAsync(voidReason);
      toast.success("Invoice voided successfully!");
      setIsVoidModalOpen(false);
      refetch();
    } catch (err: unknown) {
      const problem = parseProblemDetails(err);
      setApiError(problem);
      toast.error(problem.title || "Failed to void invoice");
    }
  };

  if (isLoading) {
    return (
      <div className="space-y-6">
        <Skeleton className="h-10 w-48" />
        <Skeleton className="h-64 w-full" />
      </div>
    );
  }

  if (!invoice) {
    return (
      <div className="space-y-4 text-center py-12">
        <div className="text-lg font-bold text-red-600">Invoice not found or access denied.</div>
        <Link href="/sales/invoices">
          <Button variant="outline">Back to Invoices</Button>
        </Link>
      </div>
    );
  }

  const items = invoice.items || [];
  const receipts = invoice.receipts || [];
  const totalAmount = Number(invoice.total_amount || 0);
  const paidAmount = Number(invoice.paid_amount || 0);
  const balanceDue = totalAmount - paidAmount;
  const isVoided = invoice.status === "voided";
  const isFullyPaid = invoice.status === "paid";

  return (
    <div className="space-y-6">
      <PageHeader
        title={`Sales Invoice #${invoice.invoice_number || invoice.id}`}
        description={`Issued on ${invoice.invoice_date} · Branch #${invoice.branch_id}`}
        actions={
          <div className="flex gap-2">
            <Link href="/sales/invoices">
              <Button variant="outline" size="sm" className="gap-1 text-xs">
                <ArrowLeft className="h-3.5 w-3.5" /> Back to Invoices
              </Button>
            </Link>

            {!isVoided && !isFullyPaid && (
              <PermissionGuard permission="invoice.create">
                <Button
                  size="sm"
                  onClick={() => setIsReceiptModalOpen(true)}
                  className="gap-1 text-xs"
                >
                  <CreditCard className="h-3.5 w-3.5" /> Record Payment
                </Button>
              </PermissionGuard>
            )}

            {!isVoided && (
              <PermissionGuard permission="invoice.void">
                <Button
                  size="sm"
                  variant="destructive"
                  onClick={() => setIsVoidModalOpen(true)}
                  className="gap-1 text-xs"
                >
                  <Ban className="h-3.5 w-3.5" /> Void Invoice (Admin)
                </Button>
              </PermissionGuard>
            )}
          </div>
        }
      />

      {/* Invoice Summary Banner */}
      <Card className="border-slate-200 shadow-sm dark:border-slate-800">
        <CardContent className="p-6">
          <div className="flex flex-col md:flex-row justify-between gap-6 pb-6 border-b border-slate-200 dark:border-slate-800">
            
            <div className="space-y-2">
              <div className="flex items-center gap-3">
                <h2 className="text-xl font-bold text-slate-900 dark:text-slate-100">
                  {invoice.invoice_number || `INV-${invoice.id}`}
                </h2>
                <StatusBadge status={invoice.status} />
              </div>
              <div className="flex items-center gap-4 text-xs text-slate-500">
                <span className="flex items-center gap-1"><Calendar className="h-3.5 w-3.5" /> Issued: {invoice.invoice_date}</span>
                {invoice.due_date && <span>Due: {invoice.due_date}</span>}
              </div>
            </div>

            <div className="space-y-1 text-left md:text-right">
              <div className="text-xs text-slate-500">Total Billed Amount</div>
              <div className="text-2xl font-bold text-teal-700 dark:text-teal-400 tabular-nums">
                {formatCurrency(totalAmount)}
              </div>
              <div className="text-xs text-slate-500">
                Paid: {formatCurrency(paidAmount)} · Balance: <strong className="text-slate-900 dark:text-slate-100">{formatCurrency(balanceDue)}</strong>
              </div>
            </div>

          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-6 pt-6 text-xs">
            <div className="space-y-1">
              <div className="font-semibold text-slate-900 dark:text-slate-100 flex items-center gap-1">
                <UserIcon className="h-3.5 w-3.5 text-teal-600" /> Billed To Customer
              </div>
              <div className="text-slate-700 dark:text-slate-300 font-medium">
                {invoice.customer?.name || `Customer #${invoice.customer_id}`}
              </div>
              {invoice.customer?.email && <div className="text-slate-500">{invoice.customer.email}</div>}
              {invoice.customer?.phone && <div className="text-slate-500">{invoice.customer.phone}</div>}
            </div>

            <div className="space-y-1">
              <div className="font-semibold text-slate-900 dark:text-slate-100 flex items-center gap-1">
                <Building2 className="h-3.5 w-3.5 text-teal-600" /> Branch Context
              </div>
              <div className="text-slate-700 dark:text-slate-300 font-medium">
                {invoice.branch?.name || `Branch #${invoice.branch_id}`}
              </div>
              {invoice.notes && <div className="text-slate-500 mt-2 italic">&quot;{invoice.notes}&quot;</div>}
            </div>
          </div>

        </CardContent>
      </Card>

      {/* Items Table */}
      <Card>
        <CardHeader>
          <CardTitle className="text-sm font-semibold">Line Items & Variant Quantities</CardTitle>
        </CardHeader>
        <CardContent className="p-0">
          <Table dense>
            <TableHeader>
              <TableRow>
                <TableHead>Line ID</TableHead>
                <TableHead>Variant Details</TableHead>
                <TableHead className="text-right">Quantity</TableHead>
                <TableHead className="text-right">Unit Price</TableHead>
                <TableHead className="text-right">Line Subtotal</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {items.map((item) => {
                const q = Number(item.quantity || 0);
                const p = Number(item.unit_price || 0);
                const subtotal = Number(item.subtotal || q * p);

                return (
                  <TableRow key={item.id || item.product_variant_id}>
                    <TableCell className="font-mono text-xs">#{item.id}</TableCell>
                    <TableCell>
                      <div className="font-mono font-bold text-xs">Variant #{item.product_variant_id}</div>
                      {item.variant?.sku && <div className="text-[10px] text-slate-500">SKU: {item.variant.sku}</div>}
                    </TableCell>
                    <TableCell className="text-right tabular-nums text-xs">{q.toFixed(2)}</TableCell>
                    <TableCell className="text-right tabular-nums text-xs">{formatCurrency(p)}</TableCell>
                    <TableCell className="text-right tabular-nums font-bold text-xs text-teal-700 dark:text-teal-400">
                      {formatCurrency(subtotal)}
                    </TableCell>
                  </TableRow>
                );
              })}
            </TableBody>
          </Table>
        </CardContent>
      </Card>

      {/* Receipts History Ledger */}
      <Card>
        <CardHeader>
          <CardTitle className="text-sm font-semibold">Customer Payment Receipts Ledger</CardTitle>
        </CardHeader>
        <CardContent className="p-0">
          <Table dense>
            <TableHeader>
              <TableRow>
                <TableHead>Receipt ID</TableHead>
                <TableHead>Payment Method</TableHead>
                <TableHead>Payment Date</TableHead>
                <TableHead>Reference Code</TableHead>
                <TableHead className="text-right">Amount Received</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {receipts.length === 0 ? (
                <TableRow>
                  <TableCell colSpan={5} className="py-6 text-center text-xs text-slate-500">
                    No receipts recorded against this invoice yet.
                  </TableCell>
                </TableRow>
              ) : (
                receipts.map((r) => (
                  <TableRow key={r.id}>
                    <TableCell className="font-mono text-xs font-semibold">#{r.id}</TableCell>
                    <TableCell className="capitalize font-mono text-xs">{r.payment_method.replace("_", " ")}</TableCell>
                    <TableCell className="text-xs text-slate-500 font-mono">{r.payment_date}</TableCell>
                    <TableCell className="text-xs font-mono">{r.reference || "—"}</TableCell>
                    <TableCell className="text-right tabular-nums font-bold text-emerald-600 text-xs">
                      +{formatCurrency(Number(r.amount))}
                    </TableCell>
                  </TableRow>
                ))
              )}
            </TableBody>
          </Table>
        </CardContent>
      </Card>

      {/* Record Payment Receipt Modal */}
      <Dialog
        isOpen={isReceiptModalOpen}
        onClose={() => setIsReceiptModalOpen(false)}
        title="Record Payment Receipt"
        description={`Remaining balance due: ${formatCurrency(balanceDue)}`}
      >
        <form onSubmit={handleSubmit(onRecordReceipt)} className="space-y-4 pt-2">
          {apiError && (
            <Alert variant="destructive">
              <ShieldAlert className="h-4 w-4" />
              <AlertTitle>{apiError.title}</AlertTitle>
              <AlertDescription>{apiError.detail}</AlertDescription>
            </Alert>
          )}

          <div className="space-y-1">
            <Label required>Amount Received ($)</Label>
            <Input
              type="number"
              step="0.01"
              {...register("amount")}
              className="tabular-nums font-mono"
            />
            {errors.amount && <span className="text-xs text-red-500">{errors.amount.message}</span>}
          </div>

          <div className="space-y-1">
            <Label required>Payment Method</Label>
            <Select
              {...register("payment_method")}
              options={[
                { label: "Bank Transfer", value: "bank_transfer" },
                { label: "Cash", value: "cash" },
                { label: "Credit / Debit Card", value: "card" },
                { label: "Cheque", value: "cheque" },
                { label: "Other", value: "other" },
              ]}
            />
          </div>

          <div className="space-y-1">
            <Label required>Payment Date (Y-m-d)</Label>
            <Input type="date" {...register("payment_date")} className="h-9 text-xs" />
          </div>

          <div className="space-y-1">
            <Label>Reference Code (Optional)</Label>
            <Input {...register("reference")} placeholder="Transaction / Cheque # / Ref" />
          </div>

          <div className="flex justify-end gap-2 pt-2">
            <Button type="button" variant="outline" onClick={() => setIsReceiptModalOpen(false)}>
              Cancel
            </Button>
            <Button type="submit" isLoading={recordReceiptMutation.isPending}>
              Save Payment Receipt
            </Button>
          </div>
        </form>
      </Dialog>

      {/* Void Invoice Confirmation Modal */}
      <Dialog
        isOpen={isVoidModalOpen}
        onClose={() => setIsVoidModalOpen(false)}
        title="Confirm Void Invoice (Admin Only)"
        description="Voiding an invoice reverses revenue entries and restores stock to warehouse via append-only ledger entries."
      >
        <div className="space-y-4 pt-2">
          <div className="space-y-1">
            <Label required>Reason for Voiding</Label>
            <Input
              value={voidReason}
              onChange={(e) => setVoidReason(e.target.value)}
              placeholder="Reason code..."
            />
          </div>

          <div className="flex justify-end gap-2 pt-2">
            <Button type="button" variant="outline" onClick={() => setIsVoidModalOpen(false)}>
              Cancel
            </Button>
            <Button variant="destructive" onClick={onVoidInvoice} isLoading={voidMutation.isPending}>
              Confirm Void Invoice
            </Button>
          </div>
        </div>
      </Dialog>
    </div>
  );
}
