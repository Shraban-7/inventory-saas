"use client";

import * as React from "react";
import Link from "next/link";
import { useParams, useRouter } from "next/navigation";
import { PageHeader } from "@/components/shell/page-header";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Skeleton } from "@/components/ui/skeleton";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { StatusBadge } from "@/components/shared/status-badge";
import { EmptyState } from "@/components/shared/empty-state";
import { PermissionGuard } from "@/components/shared/permission-guard";
import {
  usePurchaseOrdersQuery,
  useConfirmPurchaseOrderMutation,
  useCancelPurchaseOrderMutation,
  mutationErrorToast,
} from "@/features/purchasing/api/purchasing-api";
import {
  GRN_PREFILL_STORAGE_KEY,
  GrnPrefillPayload,
  PurchaseOrder,
} from "@/types/purchasing";
import { formatCurrency } from "@/lib/utils";
import { parseProblemDetails } from "@/lib/api-client";
import {
  ArrowLeft,
  PackageCheck,
  CheckCircle2,
  XCircle,
  ShieldAlert,
  Info,
} from "lucide-react";
import { toast } from "sonner";

/**
 * PO detail uses list index (items eager-loaded). There is no GET /purchase-orders/{id}.
 * BACKEND CHANGE REQUIRED for reliable deep-links beyond the first list pages.
 */
export default function PurchaseOrderDetailPage() {
  const params = useParams();
  const router = useRouter();
  const id = Number(params.id);

  const { data: response, isLoading, refetch } = usePurchaseOrdersQuery({
    per_page: 100,
    page: 1,
  });
  const confirmMutation = useConfirmPurchaseOrderMutation();
  const cancelMutation = useCancelPurchaseOrderMutation();

  const order: PurchaseOrder | undefined = response?.data?.find((o) => o.id === id);

  const receivable =
    order &&
    (order.status === "confirmed" || order.status === "partially_received");

  const goReceive = () => {
    if (!order) return;
    const prefill: GrnPrefillPayload = {
      purchase_order_id: order.id,
      branch_id: order.branch_id,
      supplier_id: order.supplier_id,
      items: (order.items || []).map((item) => {
        const ordered = Number(item.quantity_ordered || 0);
        const received = Number(item.quantity_received || 0);
        const remaining = Math.max(ordered - received, 0);
        return {
          variant_id: item.variant_id,
          quantity: remaining > 0 ? remaining : ordered || 1,
          unit_cost: item.unit_cost,
        };
      }),
    };
    sessionStorage.setItem(GRN_PREFILL_STORAGE_KEY, JSON.stringify(prefill));
    router.push(
      `/purchasing/grn?purchase_order_id=${order.id}&branch_id=${order.branch_id}&supplier_id=${order.supplier_id}`,
    );
  };

  const handleConfirm = async () => {
    try {
      await confirmMutation.mutateAsync(id);
      toast.success("Purchase order confirmed.");
      refetch();
    } catch (err: unknown) {
      toast.error(mutationErrorToast(parseProblemDetails(err)));
    }
  };

  const handleCancel = async () => {
    try {
      await cancelMutation.mutateAsync(id);
      toast.success("Purchase order cancelled.");
      refetch();
    } catch (err: unknown) {
      toast.error(mutationErrorToast(parseProblemDetails(err)));
    }
  };

  return (
    <PermissionGuard permission="purchase.create" showBanner>
      <div className="space-y-6">
        <PageHeader
          title={order ? `Purchase Order ${order.po_number || `#${order.id}`}` : "Purchase Order"}
          description="Confirm/cancel draft orders. Receive shipments from confirmed or partially received POs."
          actions={
            <Link href="/purchasing/orders">
              <Button variant="outline" className="gap-2 text-xs">
                <ArrowLeft className="h-4 w-4" /> Back to Orders
              </Button>
            </Link>
          }
        />

        <Alert>
          <Info className="h-4 w-4" />
          <AlertTitle>No dedicated PO show endpoint</AlertTitle>
          <AlertDescription>
            This page resolves the order from <code>GET /purchase-orders</code> (items included).
            Deep links outside the first pages need{" "}
            <strong>GET /api/v1/purchase-orders/&#123;id&#125;</strong> (BACKEND CHANGE REQUIRED).
          </AlertDescription>
        </Alert>

        {isLoading ? (
          <Card>
            <CardContent className="p-6 space-y-3">
              <Skeleton className="h-6 w-48" />
              <Skeleton className="h-4 w-full" />
              <Skeleton className="h-32 w-full" />
            </CardContent>
          </Card>
        ) : !order ? (
          <EmptyState
            title={`Purchase order #${id} not found in loaded list`}
            description="Open the order from the purchase orders table, or wait for a dedicated show API."
            actionLabel="Back to Orders"
            onAction={() => router.push("/purchasing/orders")}
          />
        ) : (
          <>
            <Card>
              <CardHeader className="flex flex-row items-start justify-between gap-4">
                <div className="space-y-1">
                  <CardTitle className="text-base flex items-center gap-2">
                    {order.po_number || `PO-${order.id}`}
                    <StatusBadge status={order.status} />
                  </CardTitle>
                  <p className="text-xs text-slate-500">
                    Supplier: {order.supplier?.name || `#${order.supplier_id}`} · Branch #
                    {order.branch_id} · Ordered {order.order_date}
                    {order.expected_date ? ` · Expected ${order.expected_date}` : ""}
                  </p>
                  {order.notes && (
                    <p className="text-xs text-slate-600 dark:text-slate-400 pt-1">{order.notes}</p>
                  )}
                </div>
                <div className="flex flex-wrap gap-2 justify-end">
                  {order.status === "draft" && (
                    <>
                      <Button
                        size="sm"
                        variant="outline"
                        className="gap-1 text-xs text-emerald-600"
                        isLoading={confirmMutation.isPending}
                        onClick={handleConfirm}
                      >
                        <CheckCircle2 className="h-3.5 w-3.5" /> Confirm
                      </Button>
                      <Button
                        size="sm"
                        variant="ghost"
                        className="gap-1 text-xs text-red-500"
                        isLoading={cancelMutation.isPending}
                        onClick={handleCancel}
                      >
                        <XCircle className="h-3.5 w-3.5" /> Cancel
                      </Button>
                    </>
                  )}
                  <PermissionGuard permission="purchase.receive">
                    {receivable && (
                      <Button size="sm" className="gap-1 text-xs" onClick={goReceive}>
                        <PackageCheck className="h-3.5 w-3.5" /> Receive Shipment
                      </Button>
                    )}
                  </PermissionGuard>
                </div>
              </CardHeader>
              <CardContent className="p-0">
                <Table dense>
                  <TableHeader>
                    <TableRow>
                      <TableHead>Variant ID</TableHead>
                      <TableHead>Qty Ordered</TableHead>
                      <TableHead>Qty Received</TableHead>
                      <TableHead>Unit Cost</TableHead>
                      <TableHead>Line Est.</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {(order.items || []).length === 0 ? (
                      <TableRow>
                        <TableCell colSpan={5} className="py-8 text-center text-xs text-slate-500">
                          No line items on this order payload.
                        </TableCell>
                      </TableRow>
                    ) : (
                      order.items!.map((item) => {
                        const qty = Number(item.quantity_ordered || 0);
                        const cost = Number(item.unit_cost || 0);
                        return (
                          <TableRow key={item.id}>
                            <TableCell className="font-mono text-xs">#{item.variant_id}</TableCell>
                            <TableCell className="font-mono text-xs tabular-nums">
                              {item.quantity_ordered}
                            </TableCell>
                            <TableCell className="font-mono text-xs tabular-nums">
                              {item.quantity_received}
                            </TableCell>
                            <TableCell className="font-mono text-xs tabular-nums">
                              {formatCurrency(cost)}
                            </TableCell>
                            <TableCell className="font-mono text-xs tabular-nums font-semibold text-teal-700">
                              {formatCurrency(qty * cost)}
                            </TableCell>
                          </TableRow>
                        );
                      })
                    )}
                  </TableBody>
                </Table>
              </CardContent>
            </Card>

            {!receivable && order.status !== "draft" && (
              <Alert>
                <ShieldAlert className="h-4 w-4" />
                <AlertTitle>Receiving unavailable</AlertTitle>
                <AlertDescription>
                  Receive Shipment is only available when status is{" "}
                  <code>confirmed</code> or <code>partially_received</code>.
                </AlertDescription>
              </Alert>
            )}
          </>
        )}
      </div>
    </PermissionGuard>
  );
}
