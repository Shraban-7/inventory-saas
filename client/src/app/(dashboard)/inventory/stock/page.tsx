"use client";

import * as React from "react";
import Link from "next/link";
import { PageHeader } from "@/components/shell/page-header";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Select } from "@/components/ui/select";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { StatusBadge } from "@/components/shared/status-badge";
import { EmptyState } from "@/components/shared/empty-state";
import { Skeleton } from "@/components/ui/skeleton";
import { PermissionGuard } from "@/components/shared/permission-guard";
import { useProductsQuery } from "@/features/products/api/product-api";
import { useAuthStore } from "@/lib/stores/auth-store";
import { useShellStore } from "@/lib/stores/shell-store";
import { formatCurrency, formatQuantity } from "@/lib/utils";
import { Sliders, ArrowLeftRight, Filter, AlertTriangle } from "lucide-react";

export default function StockOverviewPage() {
  const { activeBranchId } = useShellStore();
  const { branches } = useAuthStore();
  const [branchFilter, setBranchFilter] = React.useState<number | undefined>(activeBranchId || undefined);
  const [isLowStockOnly, setIsLowStockOnly] = React.useState(false);

  const { data: response, isLoading } = useProductsQuery({
    page: 1,
    per_page: 50,
    filter: {
      low_stock: isLowStockOnly,
      branch_id: branchFilter,
    },
  });

  const products = response?.data || [];

  const branchOptions = [
    { label: "All Authorized Branches", value: "" },
    ...branches.map((b) => ({ label: b.name, value: b.id })),
  ];

  return (
    <div className="space-y-6">
      <PageHeader
        title="Multi-Branch Stock Levels"
        description="Inspect real-time on-hand inventory levels and reorder thresholds across authorized branches."
        actions={
          <div className="flex gap-2">
            <PermissionGuard permission="stock.adjust">
              <Link href="/inventory/adjustments">
                <Button className="gap-1 text-xs">
                  <Sliders className="h-3.5 w-3.5" /> Adjust Stock
                </Button>
              </Link>
            </PermissionGuard>
            <PermissionGuard permission="stock.transfer">
              <Link href="/inventory/transfers">
                <Button variant="outline" className="gap-1 text-xs">
                  <ArrowLeftRight className="h-3.5 w-3.5" /> Transfer Stock
                </Button>
              </Link>
            </PermissionGuard>
          </div>
        }
      />

      {/* Filter Bar */}
      <Card>
        <CardHeader className="py-3 px-4 flex flex-row items-center justify-between border-b border-slate-200 dark:border-slate-800">
          <CardTitle className="text-sm font-semibold flex items-center gap-2">
            <Filter className="h-4 w-4 text-teal-600" />
            Stock Level Filters
          </CardTitle>
          <span className="text-xs text-slate-500">Append-only audit trail</span>
        </CardHeader>
        <CardContent className="p-4 flex flex-wrap items-center gap-6">
          <div className="space-y-1 w-64">
            <label className="text-xs font-medium text-slate-700 dark:text-slate-300">Branch Scope</label>
            <Select
              value={branchFilter || ""}
              onChange={(e) => setBranchFilter(e.target.value ? Number(e.target.value) : undefined)}
              options={branchOptions}
              className="h-9 text-xs"
            />
          </div>

          <div className="flex items-center space-x-2 pt-5">
            <input
              type="checkbox"
              id="stock_low_toggle"
              checked={isLowStockOnly}
              onChange={(e) => setIsLowStockOnly(e.target.checked)}
              className="h-4 w-4 rounded border-slate-300 text-teal-600 focus:ring-teal-500 cursor-pointer"
            />
            <label htmlFor="stock_low_toggle" className="text-xs font-medium text-slate-700 dark:text-slate-300 cursor-pointer flex items-center gap-1.5">
              <AlertTriangle className="h-3.5 w-3.5 text-amber-500" />
              Show Low Stock Items Only (`filter[low_stock]`)
            </label>
          </div>
        </CardContent>
      </Card>

      {/* Stock Table */}
      <Card>
        <CardContent className="p-0">
          <Table dense>
            <TableHeader>
              <TableRow>
                <TableHead>Product & SKU</TableHead>
                <TableHead>Valuation</TableHead>
                <TableHead>Unit Cost</TableHead>
                <TableHead>Sale Price</TableHead>
                <TableHead>Reorder Pt</TableHead>
                <TableHead>Status</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {isLoading ? (
                Array.from({ length: 5 }).map((_, idx) => (
                  <TableRow key={idx}>
                    <TableCell><Skeleton className="h-4 w-48" /></TableCell>
                    <TableCell><Skeleton className="h-4 w-16" /></TableCell>
                    <TableCell><Skeleton className="h-4 w-20" /></TableCell>
                    <TableCell><Skeleton className="h-4 w-20" /></TableCell>
                    <TableCell><Skeleton className="h-4 w-16" /></TableCell>
                    <TableCell><Skeleton className="h-4 w-24" /></TableCell>
                  </TableRow>
                ))
              ) : products.length === 0 ? (
                <TableRow>
                  <TableCell colSpan={6} className="py-12">
                    <EmptyState
                      title="No inventory records found"
                      description="No product variants match your current branch or low-stock criteria."
                    />
                  </TableCell>
                </TableRow>
              ) : (
                products.map((p) => {
                  const variants = p.variants || [];
                  return variants.map((v) => {
                    const cost = Number(v.cost_price || 0);
                    const sale = Number(v.sale_price || 0);
                    const isLow = isLowStockOnly;

                    return (
                      <TableRow key={`${p.id}-${v.id}`}>
                        <TableCell>
                          <div className="font-semibold text-slate-900 dark:text-slate-100">{p.name}</div>
                          <div className="text-[11px] font-mono text-slate-500">SKU: {v.sku}</div>
                        </TableCell>
                        <TableCell className="text-xs uppercase font-mono">{p.costing_method}</TableCell>
                        <TableCell className="tabular-nums text-xs">{formatCurrency(cost)}</TableCell>
                        <TableCell className="tabular-nums font-semibold text-xs text-teal-700 dark:text-teal-400">
                          {formatCurrency(sale)}
                        </TableCell>
                        <TableCell className="tabular-nums text-xs font-medium">
                          {formatQuantity(v.reorder_point)}
                        </TableCell>
                        <TableCell>
                          {isLow ? (
                            <StatusBadge status="low_stock" />
                          ) : (
                            <StatusBadge status="completed" customLabel="In Stock" />
                          )}
                        </TableCell>
                      </TableRow>
                    );
                  });
                })
              )}
            </TableBody>
          </Table>
        </CardContent>
      </Card>
    </div>
  );
}
