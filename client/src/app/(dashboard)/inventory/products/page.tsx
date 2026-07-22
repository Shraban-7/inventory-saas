"use client";

import * as React from "react";
import { PageHeader } from "@/components/shell/page-header";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Badge } from "@/components/ui/badge";
import { Skeleton } from "@/components/ui/skeleton";
import { EmptyState } from "@/components/shared/empty-state";
import { StatusBadge } from "@/components/shared/status-badge";
import { PermissionGuard } from "@/components/shared/permission-guard";
import { useProductsQuery } from "@/features/products/api/product-api";
import { ProductFormModal } from "@/features/products/components/product-form-modal";
import { VariantListModal } from "@/features/products/components/variant-list-modal";
import { Product, ListProductsParams } from "@/types/products";
import { useShellStore } from "@/lib/stores/shell-store";
import { useAuthStore } from "@/lib/stores/auth-store";
import { formatCurrency } from "@/lib/utils";
import { Plus, Filter, ChevronLeft, ChevronRight, Eye } from "lucide-react";

export default function ProductsPage() {
  const { activeBranchId } = useShellStore();
  const { branches } = useAuthStore();

  const [params, setParams] = React.useState<ListProductsParams>({
    page: 1,
    per_page: 25,
    filter: {
      low_stock: false,
      branch_id: activeBranchId || undefined,
    },
  });

  const [isCreateModalOpen, setIsCreateModalOpen] = React.useState(false);
  const [selectedProductForVariants, setSelectedProductForVariants] = React.useState<Product | null>(null);

  const { data: response, isLoading, isError, refetch } = useProductsQuery(params);

  const products = response?.data || [];
  const meta = response?.meta;

  const branchOptions = [
    { label: "All Authorized Branches", value: "" },
    ...branches.map((b) => ({ label: b.name, value: b.id })),
  ];

  return (
    <div className="space-y-6">
      <PageHeader
        title="Products & Variant Catalog"
        description="Manage product headers, costing methods (FIFO/AVCO), SKUs, barcodes, and variant pricing."
        actions={
          <PermissionGuard permission="product.manage">
            <Button onClick={() => setIsCreateModalOpen(true)} className="gap-2">
              <Plus className="h-4 w-4" /> Create Product
            </Button>
          </PermissionGuard>
        }
      />

      {/* Filters Toolbar */}
      <Card>
        <CardHeader className="py-3 px-4 flex flex-row items-center justify-between border-b border-slate-200 dark:border-slate-800">
          <CardTitle className="text-sm font-semibold flex items-center gap-2">
            <Filter className="h-4 w-4 text-teal-600" />
            Catalog Filters
          </CardTitle>
          <span className="text-xs text-slate-500">No search endpoint — Supported filters only</span>
        </CardHeader>
        <CardContent className="p-4">
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            
            {/* Category ID Filter */}
            <div className="space-y-1">
              <label className="text-xs font-medium text-slate-700 dark:text-slate-300">Category ID</label>
              <Input
                type="number"
                placeholder="e.g. 1"
                value={params.category_id || ""}
                onChange={(e) =>
                  setParams((prev) => ({
                    ...prev,
                    page: 1,
                    category_id: e.target.value ? Number(e.target.value) : undefined,
                  }))
                }
                className="h-9 text-xs"
              />
            </div>

            {/* Branch Filter */}
            <div className="space-y-1">
              <label className="text-xs font-medium text-slate-700 dark:text-slate-300">Branch Scope</label>
              <Select
                value={params.filter?.branch_id || ""}
                onChange={(e) =>
                  setParams((prev) => ({
                    ...prev,
                    page: 1,
                    filter: {
                      ...prev.filter,
                      branch_id: e.target.value ? Number(e.target.value) : undefined,
                    },
                  }))
                }
                options={branchOptions}
                className="h-9 text-xs"
              />
            </div>

            {/* Low Stock Toggle */}
            <div className="flex items-center space-x-2 pt-6">
              <input
                type="checkbox"
                id="low_stock_checkbox"
                checked={!!params.filter?.low_stock}
                onChange={(e) =>
                  setParams((prev) => ({
                    ...prev,
                    page: 1,
                    filter: {
                      ...prev.filter,
                      low_stock: e.target.checked,
                    },
                  }))
                }
                className="h-4 w-4 rounded border-slate-300 text-teal-600 focus:ring-teal-500 cursor-pointer"
              />
              <label htmlFor="low_stock_checkbox" className="text-xs font-medium text-slate-700 dark:text-slate-300 cursor-pointer">
                Low Stock Threshold Only
              </label>
            </div>

            {/* Per Page Selector */}
            <div className="space-y-1">
              <label className="text-xs font-medium text-slate-700 dark:text-slate-300">Rows per page</label>
              <Select
                value={params.per_page || 25}
                onChange={(e) =>
                  setParams((prev) => ({
                    ...prev,
                    page: 1,
                    per_page: Number(e.target.value),
                  }))
                }
                options={[
                  { label: "10 per page", value: 10 },
                  { label: "25 per page", value: 25 },
                  { label: "50 per page", value: 50 },
                  { label: "100 per page", value: 100 },
                ]}
                className="h-9 text-xs"
              />
            </div>

          </div>
        </CardContent>
      </Card>

      {/* Product Data Table */}
      <Card>
        <CardContent className="p-0">
          <Table dense>
            <TableHeader>
              <TableRow>
                <TableHead className="w-16">ID</TableHead>
                <TableHead>Product Name</TableHead>
                <TableHead>Category</TableHead>
                <TableHead>Costing Method</TableHead>
                <TableHead>Variants</TableHead>
                <TableHead>Sale Price Range</TableHead>
                <TableHead>Status</TableHead>
                <TableHead className="text-right">Actions</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {isLoading ? (
                Array.from({ length: 5 }).map((_, idx) => (
                  <TableRow key={idx}>
                    <TableCell><Skeleton className="h-4 w-8" /></TableCell>
                    <TableCell><Skeleton className="h-4 w-48" /></TableCell>
                    <TableCell><Skeleton className="h-4 w-16" /></TableCell>
                    <TableCell><Skeleton className="h-4 w-16" /></TableCell>
                    <TableCell><Skeleton className="h-4 w-12" /></TableCell>
                    <TableCell><Skeleton className="h-4 w-24" /></TableCell>
                    <TableCell><Skeleton className="h-4 w-20" /></TableCell>
                    <TableCell className="text-right"><Skeleton className="h-8 w-20 ml-auto" /></TableCell>
                  </TableRow>
                ))
              ) : isError ? (
                <TableRow>
                  <TableCell colSpan={8} className="py-8 text-center">
                    <div className="text-sm text-red-500 font-medium">Failed to load product catalog.</div>
                    <Button size="sm" variant="outline" onClick={() => refetch()} className="mt-2">
                      Retry Request
                    </Button>
                  </TableCell>
                </TableRow>
              ) : products.length === 0 ? (
                <TableRow>
                  <TableCell colSpan={8} className="py-12">
                    <EmptyState
                      title="No products found"
                      description="No products match your active category or low stock filters."
                      actionLabel="Create Product"
                      onAction={() => setIsCreateModalOpen(true)}
                    />
                  </TableCell>
                </TableRow>
              ) : (
                products.map((p) => {
                  const variants = p.variants || [];
                  const prices = variants.map((v) => Number(v.sale_price || 0));
                  const minPrice = prices.length > 0 ? Math.min(...prices) : 0;
                  const maxPrice = prices.length > 0 ? Math.max(...prices) : 0;
                  const priceDisplay =
                    prices.length === 0
                      ? "—"
                      : minPrice === maxPrice
                      ? formatCurrency(minPrice)
                      : `${formatCurrency(minPrice)} - ${formatCurrency(maxPrice)}`;

                  return (
                    <TableRow key={p.id}>
                      <TableCell className="font-mono text-xs font-semibold">#{p.id}</TableCell>
                      <TableCell className="font-semibold text-slate-900 dark:text-slate-100">
                        {p.name}
                        {p.description && (
                          <div className="text-[11px] text-slate-500 font-normal line-clamp-1">
                            {p.description}
                          </div>
                        )}
                      </TableCell>
                      <TableCell className="text-xs">Category #{p.category_id}</TableCell>
                      <TableCell>
                        <Badge variant="outline" className="font-mono text-[11px] uppercase">
                          {p.costing_method}
                        </Badge>
                      </TableCell>
                      <TableCell className="tabular-nums font-medium">
                        {variants.length} variant{variants.length !== 1 ? "s" : ""}
                      </TableCell>
                      <TableCell className="tabular-nums font-semibold text-teal-700 dark:text-teal-400">
                        {priceDisplay}
                      </TableCell>
                      <TableCell>
                        {params.filter?.low_stock ? (
                          <StatusBadge status="low_stock" />
                        ) : (
                          <StatusBadge status="completed" customLabel="Active" />
                        )}
                      </TableCell>
                      <TableCell className="text-right">
                        <Button
                          size="sm"
                          variant="ghost"
                          onClick={() => setSelectedProductForVariants(p)}
                          className="gap-1 text-xs"
                        >
                          <Eye className="h-3.5 w-3.5" /> Manage Variants
                        </Button>
                      </TableCell>
                    </TableRow>
                  );
                })
              )}
            </TableBody>
          </Table>

          {/* Pagination Controls */}
          {meta && meta.last_page > 1 && (
            <div className="flex items-center justify-between border-t border-slate-200 p-4 dark:border-slate-800">
              <div className="text-xs text-slate-500">
                Showing <strong>{meta.from || 0}</strong> to <strong>{meta.to || 0}</strong> of{" "}
                <strong>{meta.total}</strong> products
              </div>
              <div className="flex items-center gap-2">
                <Button
                  size="sm"
                  variant="outline"
                  disabled={meta.current_page <= 1}
                  onClick={() => setParams((prev) => ({ ...prev, page: (prev.page || 1) - 1 }))}
                  className="gap-1 text-xs"
                >
                  <ChevronLeft className="h-3.5 w-3.5" /> Previous
                </Button>
                <span className="text-xs font-medium px-2">
                  Page {meta.current_page} of {meta.last_page}
                </span>
                <Button
                  size="sm"
                  variant="outline"
                  disabled={meta.current_page >= meta.last_page}
                  onClick={() => setParams((prev) => ({ ...prev, page: (prev.page || 1) + 1 }))}
                  className="gap-1 text-xs"
                >
                  Next <ChevronRight className="h-3.5 w-3.5" />
                </Button>
              </div>
            </div>
          )}
        </CardContent>
      </Card>

      {/* Create Product Form Modal */}
      <ProductFormModal
        isOpen={isCreateModalOpen}
        onClose={() => setIsCreateModalOpen(false)}
      />

      {/* Manage Variants Modal */}
      <VariantListModal
        product={selectedProductForVariants}
        isOpen={!!selectedProductForVariants}
        onClose={() => setSelectedProductForVariants(null)}
      />
    </div>
  );
}
