"use client";

import Link from "next/link";
import { PageHeader } from "@/components/shell/page-header";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Skeleton } from "@/components/ui/skeleton";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { StatusBadge } from "@/components/shared/status-badge";
import { PermissionGuard } from "@/components/shared/permission-guard";
import { useInvoicesQuery } from "@/features/sales/api/sales-api";
import { usePurchaseOrdersQuery } from "@/features/purchasing/api/purchasing-api";
import { useProductsQuery } from "@/features/products/api/product-api";
import { useAuthStore } from "@/lib/stores/auth-store";
import { useShellStore } from "@/lib/stores/shell-store";
import { formatCurrency } from "@/lib/utils";
import {
  Info,
  FileText,
  ShoppingCart,
  Sliders,
  ArrowLeftRight,
  Eye,
  PieChart,
  TriangleAlert,
} from "lucide-react";

export default function DashboardPage() {
  const { user, roles, hasPermission } = useAuthStore();
  const { activeBranchId } = useShellStore();

  const canInvoice = hasPermission("invoice.create");
  const canViewInvoice = hasPermission("invoice.view");
  const canPo = hasPermission("purchase.create");
  const canAdjust = hasPermission("stock.adjust");
  const canTransfer = hasPermission("stock.transfer");
  const canReport = hasPermission("report.view");

  const invoicesQuery = useInvoicesQuery({ per_page: 10 }, canViewInvoice);
  const openPosQuery = usePurchaseOrdersQuery(
    { per_page: 10, page: 1, status: "confirmed" },
    canPo,
  );
  const draftPosQuery = usePurchaseOrdersQuery(
    { per_page: 10, page: 1, status: "draft" },
    canPo,
  );
  const lowStockQuery = useProductsQuery({
    per_page: 10,
    page: 1,
    filter: {
      low_stock: true,
      ...(activeBranchId ? { branch_id: activeBranchId } : {}),
    },
  });

  const invoices = invoicesQuery.data?.data || [];
  const confirmedPos = openPosQuery.data?.data || [];
  const draftPos = draftPosQuery.data?.data || [];
  const lowStockProducts = lowStockQuery.data?.data || [];
  const lowStockTotal = lowStockQuery.data?.meta?.total;
  const openPoSampleCount = draftPos.length + confirmedPos.length;

  return (
    <div className="space-y-6">
      <PageHeader
        title="Operational Dashboard"
        description={`Branch context #${activeBranchId || "—"}. Widgets are composed from live list APIs — not BI aggregates.`}
      />

      <Alert>
        <Info className="h-4 w-4" />
        <AlertTitle>Honest composition (no KPI API)</AlertTitle>
        <AlertDescription>
          Counts and tables below are <strong>first-page samples</strong> from existing list
          endpoints. There is no dedicated dashboard/KPI API, no AR/AP rollup, no inventory
          valuation, and no stock-movement timeline.
        </AlertDescription>
      </Alert>

      <Card className="bg-teal-500/10 border-teal-500/20">
        <CardContent className="p-4 flex items-center justify-between gap-3 flex-wrap">
          <div>
            <div className="font-semibold text-slate-900 dark:text-slate-100">
              Welcome, {user?.name || "User"}
            </div>
            <div className="text-xs text-slate-600 dark:text-slate-400">
              Role: <span className="font-semibold text-teal-700">{roles[0] || "—"}</span> · Tenant #
              {user?.tenant_id || "—"}
            </div>
          </div>
          <div className="flex flex-wrap gap-2">
            <PermissionGuard permission="invoice.create">
              <Link href="/sales/invoices/new">
                <Button size="sm" className="gap-1 text-xs">
                  <FileText className="h-3.5 w-3.5" /> New Invoice
                </Button>
              </Link>
            </PermissionGuard>
            <PermissionGuard permission="purchase.create">
              <Link href="/purchasing/orders">
                <Button size="sm" variant="outline" className="gap-1 text-xs">
                  <ShoppingCart className="h-3.5 w-3.5" /> New PO
                </Button>
              </Link>
            </PermissionGuard>
            <PermissionGuard permission="stock.adjust">
              <Link href="/inventory/adjustments">
                <Button size="sm" variant="outline" className="gap-1 text-xs">
                  <Sliders className="h-3.5 w-3.5" /> Adjust Stock
                </Button>
              </Link>
            </PermissionGuard>
            <PermissionGuard permission="stock.transfer">
              <Link href="/inventory/transfers">
                <Button size="sm" variant="outline" className="gap-1 text-xs">
                  <ArrowLeftRight className="h-3.5 w-3.5" /> Transfer
                </Button>
              </Link>
            </PermissionGuard>
            <PermissionGuard permission="report.view">
              <Link href="/reports/profit-and-loss">
                <Button size="sm" variant="outline" className="gap-1 text-xs">
                  <PieChart className="h-3.5 w-3.5" /> P&amp;L
                </Button>
              </Link>
            </PermissionGuard>
            {!canInvoice && !canPo && !canAdjust && !canTransfer && !canReport && (
              <Badge variant="secondary" className="text-[10px]">
                No mutation quick actions for this role
              </Badge>
            )}
          </div>
        </CardContent>
      </Card>

      <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
        <Card>
          <CardHeader className="pb-2">
            <CardDescription className="text-xs flex items-center justify-between">
              Recent invoices (sample)
              <Badge variant="outline" className="text-[9px]">
                first page
              </Badge>
            </CardDescription>
            <CardTitle className="text-2xl font-bold tabular-nums">
              {invoicesQuery.isLoading ? "…" : canViewInvoice ? invoices.length : "—"}
            </CardTitle>
          </CardHeader>
          <CardContent className="text-[11px] text-slate-500">
            From <code>GET /invoices?per_page=10</code>
            {canViewInvoice ? (
              <Link href="/sales/invoices" className="block mt-1 text-teal-700 hover:underline">
                Open invoices
              </Link>
            ) : (
              <span className="block mt-1">Requires invoice.view</span>
            )}
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="pb-2">
            <CardDescription className="text-xs flex items-center justify-between">
              Open POs (draft + confirmed samples)
              <Badge variant="outline" className="text-[9px]">
                first page
              </Badge>
            </CardDescription>
            <CardTitle className="text-2xl font-bold tabular-nums">
              {openPosQuery.isLoading || draftPosQuery.isLoading
                ? "…"
                : canPo
                  ? openPoSampleCount
                  : "—"}
            </CardTitle>
          </CardHeader>
          <CardContent className="text-[11px] text-slate-500">
            From <code>GET /purchase-orders?status=…</code>
            {canPo ? (
              <Link href="/purchasing/orders" className="block mt-1 text-teal-700 hover:underline">
                Open purchase orders
              </Link>
            ) : (
              <span className="block mt-1">Requires purchase.create</span>
            )}
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="pb-2">
            <CardDescription className="text-xs flex items-center justify-between gap-2">
              <span className="flex items-center gap-1">
                <TriangleAlert className="h-3.5 w-3.5" /> Low stock
              </span>
              <Badge variant="warning" className="text-[9px]">
                filter[low_stock]
              </Badge>
            </CardDescription>
            <CardTitle className="text-2xl font-bold tabular-nums">
              {lowStockQuery.isLoading
                ? "…"
                : lowStockTotal != null
                  ? lowStockTotal
                  : lowStockProducts.length}
            </CardTitle>
          </CardHeader>
          <CardContent className="text-[11px] text-slate-500">
            {lowStockTotal != null
              ? "Uses paginator total when present."
              : "Sample length from first page only."}
            <Link href="/inventory/products" className="block mt-1 text-teal-700 hover:underline">
              View products
            </Link>
          </CardContent>
        </Card>
      </div>

      <div className="grid grid-cols-1 xl:grid-cols-2 gap-4">
        <Card>
          <CardHeader className="py-3 px-4 border-b border-slate-200 dark:border-slate-800 flex flex-row items-center justify-between">
            <CardTitle className="text-sm font-semibold">Recent invoices</CardTitle>
            {canViewInvoice && (
              <Link href="/sales/invoices">
                <Button size="sm" variant="ghost" className="text-xs gap-1">
                  <Eye className="h-3 w-3" /> All
                </Button>
              </Link>
            )}
          </CardHeader>
          <CardContent className="p-0">
            {!canViewInvoice ? (
              <p className="p-4 text-xs text-slate-500">Hidden — missing invoice.view</p>
            ) : invoicesQuery.isLoading ? (
              <div className="p-4 space-y-2">
                <Skeleton className="h-6 w-full" />
                <Skeleton className="h-6 w-full" />
              </div>
            ) : invoices.length === 0 ? (
              <p className="p-4 text-xs text-slate-500">No invoices in this sample.</p>
            ) : (
              <Table dense>
                <TableHeader>
                  <TableRow>
                    <TableHead>Ref</TableHead>
                    <TableHead>Date</TableHead>
                    <TableHead>Total</TableHead>
                    <TableHead>Status</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {invoices.slice(0, 5).map((inv) => (
                    <TableRow key={inv.id}>
                      <TableCell className="font-mono text-xs">
                        <Link
                          href={`/sales/invoices/${inv.id}`}
                          className="text-teal-700 hover:underline"
                        >
                          {inv.invoice_number || `#${inv.id}`}
                        </Link>
                      </TableCell>
                      <TableCell className="font-mono text-xs">{inv.invoice_date}</TableCell>
                      <TableCell className="font-mono text-xs tabular-nums">
                        {formatCurrency(Number(inv.total_amount || 0))}
                      </TableCell>
                      <TableCell>
                        <StatusBadge status={inv.status} />
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            )}
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="py-3 px-4 border-b border-slate-200 dark:border-slate-800 flex flex-row items-center justify-between">
            <CardTitle className="text-sm font-semibold">Open purchase orders</CardTitle>
            {canPo && (
              <Link href="/purchasing/orders">
                <Button size="sm" variant="ghost" className="text-xs gap-1">
                  <Eye className="h-3 w-3" /> All
                </Button>
              </Link>
            )}
          </CardHeader>
          <CardContent className="p-0">
            {!canPo ? (
              <p className="p-4 text-xs text-slate-500">Hidden — missing purchase.create</p>
            ) : openPosQuery.isLoading || draftPosQuery.isLoading ? (
              <div className="p-4 space-y-2">
                <Skeleton className="h-6 w-full" />
                <Skeleton className="h-6 w-full" />
              </div>
            ) : openPoSampleCount === 0 ? (
              <p className="p-4 text-xs text-slate-500">No draft/confirmed POs in sample.</p>
            ) : (
              <Table dense>
                <TableHeader>
                  <TableRow>
                    <TableHead>PO</TableHead>
                    <TableHead>Supplier</TableHead>
                    <TableHead>Date</TableHead>
                    <TableHead>Status</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {[...draftPos, ...confirmedPos].slice(0, 5).map((po) => (
                    <TableRow key={po.id}>
                      <TableCell className="font-mono text-xs">
                        <Link
                          href={`/purchasing/orders/${po.id}`}
                          className="text-teal-700 hover:underline"
                        >
                          {po.po_number || `#${po.id}`}
                        </Link>
                      </TableCell>
                      <TableCell className="text-xs">
                        {po.supplier?.name || `#${po.supplier_id}`}
                      </TableCell>
                      <TableCell className="font-mono text-xs">{po.order_date}</TableCell>
                      <TableCell>
                        <StatusBadge status={po.status} />
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            )}
          </CardContent>
        </Card>

        <Card className="xl:col-span-2">
          <CardHeader className="py-3 px-4 border-b border-slate-200 dark:border-slate-800 flex flex-row items-center justify-between">
            <CardTitle className="text-sm font-semibold">Low stock products</CardTitle>
            <Link href="/inventory/products">
              <Button size="sm" variant="ghost" className="text-xs gap-1">
                <Eye className="h-3 w-3" /> Catalog
              </Button>
            </Link>
          </CardHeader>
          <CardContent className="p-0">
            {lowStockQuery.isLoading ? (
              <div className="p-4 space-y-2">
                <Skeleton className="h-6 w-full" />
                <Skeleton className="h-6 w-full" />
              </div>
            ) : lowStockProducts.length === 0 ? (
              <p className="p-4 text-xs text-slate-500">
                No low-stock products on this page (or none match the filter).
              </p>
            ) : (
              <Table dense>
                <TableHeader>
                  <TableRow>
                    <TableHead>ID</TableHead>
                    <TableHead>Name</TableHead>
                    <TableHead>Costing</TableHead>
                    <TableHead></TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {lowStockProducts.slice(0, 8).map((p) => (
                    <TableRow key={p.id}>
                      <TableCell className="font-mono text-xs">#{p.id}</TableCell>
                      <TableCell className="text-xs font-medium">{p.name}</TableCell>
                      <TableCell className="font-mono text-xs uppercase">{p.costing_method}</TableCell>
                      <TableCell>
                        <StatusBadge status="low_stock" />
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            )}
          </CardContent>
        </Card>
      </div>
    </div>
  );
}
