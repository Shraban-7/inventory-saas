"use client";

import * as React from "react";
import Link from "next/link";
import { PageHeader } from "@/components/shell/page-header";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Tabs, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Skeleton } from "@/components/ui/skeleton";
import { StatusBadge } from "@/components/shared/status-badge";
import { EmptyState } from "@/components/shared/empty-state";
import { PermissionGuard } from "@/components/shared/permission-guard";
import { useInvoicesQuery } from "@/features/sales/api/sales-api";
import { ListInvoicesParams, InvoiceStatus } from "@/types/sales";
import { formatCurrency } from "@/lib/utils";
import { Plus, Filter, Eye, ChevronLeft, ChevronRight } from "lucide-react";

export default function InvoicesPage() {
  const [params, setParams] = React.useState<ListInvoicesParams>({
    per_page: 25,
    status: undefined,
  });

  const { data: response, isLoading } = useInvoicesQuery(params);

  const invoices = response?.data || [];
  const nextCursor = response?.next_cursor;
  const prevCursor = response?.prev_cursor;

  const handleStatusChange = (statusValue: string) => {
    setParams((prev) => ({
      ...prev,
      cursor: undefined,
      status: statusValue === "all" ? undefined : (statusValue as InvoiceStatus),
    }));
  };

  return (
    <PermissionGuard permission="invoice.view" showBanner>
      <div className="space-y-6">
        <PageHeader
          title="Sales Invoices"
          description="Manage sales invoices, record customer receipts, and inspect FIFO inventory cost deductions."
          actions={
            <PermissionGuard permission="invoice.create">
              <Link href="/sales/invoices/new">
                <Button className="gap-2 text-xs">
                  <Plus className="h-4 w-4" /> Create Invoice
                </Button>
              </Link>
            </PermissionGuard>
          }
        />

        {/* Filters Toolbar */}
        <Card>
          <CardHeader className="py-3 px-4 flex flex-row items-center justify-between border-b border-slate-200 dark:border-slate-800">
            <CardTitle className="text-sm font-semibold flex items-center gap-2">
              <Filter className="h-4 w-4 text-teal-600" />
              Invoice Filters
            </CardTitle>
            <span className="text-xs text-slate-500 font-mono">Cursor Pagination Mode Active</span>
          </CardHeader>
          <CardContent className="p-4 space-y-4">
            
            {/* Status Tabs */}
            <Tabs defaultValue="all" onValueChange={handleStatusChange}>
              <TabsList>
                <TabsTrigger value="all">All Invoices</TabsTrigger>
                <TabsTrigger value="issued">Issued</TabsTrigger>
                <TabsTrigger value="partially_paid">Partially Paid</TabsTrigger>
                <TabsTrigger value="paid">Paid</TabsTrigger>
                <TabsTrigger value="voided">Voided</TabsTrigger>
              </TabsList>
            </Tabs>

            {/* Date Range & Customer ID Inputs */}
            <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
              <div className="space-y-1">
                <label className="text-xs font-medium text-slate-700 dark:text-slate-300">Customer ID</label>
                <Input
                  type="number"
                  placeholder="Filter by Customer ID"
                  value={params.customer_id || ""}
                  onChange={(e) =>
                    setParams((prev) => ({
                      ...prev,
                      cursor: undefined,
                      customer_id: e.target.value ? Number(e.target.value) : undefined,
                    }))
                  }
                  className="h-9 text-xs"
                />
              </div>

              <div className="space-y-1">
                <label className="text-xs font-medium text-slate-700 dark:text-slate-300">Date From (Y-m-d)</label>
                <Input
                  type="date"
                  value={params.date_from || ""}
                  onChange={(e) =>
                    setParams((prev) => ({
                      ...prev,
                      cursor: undefined,
                      date_from: e.target.value || undefined,
                    }))
                  }
                  className="h-9 text-xs"
                />
              </div>

              <div className="space-y-1">
                <label className="text-xs font-medium text-slate-700 dark:text-slate-300">Date To (Y-m-d)</label>
                <Input
                  type="date"
                  value={params.date_to || ""}
                  onChange={(e) =>
                    setParams((prev) => ({
                      ...prev,
                      cursor: undefined,
                      date_to: e.target.value || undefined,
                    }))
                  }
                  className="h-9 text-xs"
                />
              </div>
            </div>

          </CardContent>
        </Card>

        {/* Invoice Data Table */}
        <Card>
          <CardContent className="p-0">
            <Table dense>
              <TableHeader>
                <TableRow>
                  <TableHead className="w-16">ID</TableHead>
                  <TableHead>Invoice Ref</TableHead>
                  <TableHead>Customer</TableHead>
                  <TableHead>Branch</TableHead>
                  <TableHead>Invoice Date</TableHead>
                  <TableHead>Total Amount</TableHead>
                  <TableHead>Status</TableHead>
                  <TableHead className="text-right">Actions</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {isLoading ? (
                  Array.from({ length: 5 }).map((_, idx) => (
                    <TableRow key={idx}>
                      <TableCell><Skeleton className="h-4 w-8" /></TableCell>
                      <TableCell><Skeleton className="h-4 w-24" /></TableCell>
                      <TableCell><Skeleton className="h-4 w-32" /></TableCell>
                      <TableCell><Skeleton className="h-4 w-20" /></TableCell>
                      <TableCell><Skeleton className="h-4 w-24" /></TableCell>
                      <TableCell><Skeleton className="h-4 w-20" /></TableCell>
                      <TableCell><Skeleton className="h-4 w-16" /></TableCell>
                      <TableCell className="text-right"><Skeleton className="h-8 w-16 ml-auto" /></TableCell>
                    </TableRow>
                  ))
                ) : invoices.length === 0 ? (
                  <TableRow>
                    <TableCell colSpan={8} className="py-12">
                      <EmptyState
                        title="No invoices found"
                        description="No sales invoices match your current filter parameters."
                        actionLabel="Create Sales Invoice"
                        onAction={() => window.location.href = "/sales/invoices/new"}
                      />
                    </TableCell>
                  </TableRow>
                ) : (
                  invoices.map((inv) => {
                    const total = Number(inv.total_amount || 0);

                    return (
                      <TableRow key={inv.id}>
                        <TableCell className="font-mono text-xs font-semibold">#{inv.id}</TableCell>
                        <TableCell className="font-mono font-bold text-slate-900 dark:text-slate-100">
                          {inv.invoice_number || `INV-${inv.id}`}
                        </TableCell>
                        <TableCell className="text-xs">
                          {inv.customer?.name || `Customer #${inv.customer_id}`}
                        </TableCell>
                        <TableCell className="text-xs">Branch #{inv.branch_id}</TableCell>
                        <TableCell className="text-xs text-slate-500 font-mono">
                          {inv.invoice_date}
                        </TableCell>
                        <TableCell className="tabular-nums font-bold text-teal-700 dark:text-teal-400">
                          {formatCurrency(total)}
                        </TableCell>
                        <TableCell>
                          <StatusBadge status={inv.status} />
                        </TableCell>
                        <TableCell className="text-right">
                          <Link href={`/sales/invoices/${inv.id}`}>
                            <Button size="sm" variant="ghost" className="gap-1 text-xs">
                              <Eye className="h-3.5 w-3.5" /> View
                            </Button>
                          </Link>
                        </TableCell>
                      </TableRow>
                    );
                  })
                )}
              </TableBody>
            </Table>

            {/* Cursor Pagination Bar */}
            {(nextCursor || prevCursor) && (
              <div className="flex items-center justify-between border-t border-slate-200 p-4 dark:border-slate-800">
                <span className="text-xs text-slate-500">
                  Cursor Paginated Result Stream
                </span>
                <div className="flex items-center gap-2">
                  <Button
                    size="sm"
                    variant="outline"
                    disabled={!prevCursor}
                    onClick={() => setParams((prev) => ({ ...prev, cursor: prevCursor || undefined }))}
                    className="gap-1 text-xs"
                  >
                    <ChevronLeft className="h-3.5 w-3.5" /> Previous Cursor
                  </Button>
                  <Button
                    size="sm"
                    variant="outline"
                    disabled={!nextCursor}
                    onClick={() => setParams((prev) => ({ ...prev, cursor: nextCursor || undefined }))}
                    className="gap-1 text-xs"
                  >
                    Next Cursor <ChevronRight className="h-3.5 w-3.5" />
                  </Button>
                </div>
              </div>
            )}
          </CardContent>
        </Card>
      </div>
    </PermissionGuard>
  );
}
