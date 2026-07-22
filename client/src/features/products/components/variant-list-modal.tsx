"use client";

import * as React from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { Plus, ShieldAlert, Layers } from "lucide-react";
import { Dialog } from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Badge } from "@/components/ui/badge";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Product } from "@/types/products";
import { useProductVariantsQuery, useAddProductVariantMutation } from "../api/product-api";
import { addVariantSchema, CreateVariantFormValues } from "../schemas/product-schema";
import { PermissionGuard } from "@/components/shared/permission-guard";
import { formatCurrency, formatQuantity } from "@/lib/utils";
import { parseProblemDetails, ProblemDetails } from "@/lib/api-client";
import { toast } from "sonner";

interface VariantListModalProps {
  product: Product | null;
  isOpen: boolean;
  onClose: () => void;
}

export function VariantListModal({ product, isOpen, onClose }: VariantListModalProps) {
  const productId = product?.id || 0;
  const { data: variants, isLoading, refetch } = useProductVariantsQuery(productId, isOpen);
  const addMutation = useAddProductVariantMutation(productId);
  const [showAddForm, setShowAddForm] = React.useState(false);
  const [apiError, setApiError] = React.useState<ProblemDetails | null>(null);

  const {
    register,
    handleSubmit,
    reset,
    formState: { errors },
  } = useForm({
    resolver: zodResolver(addVariantSchema),
    defaultValues: {
      sku: "",
      barcode: "",
      cost_price: 0,
      sale_price: 0,
      reorder_point: 5,
    },
  });

  const onAddVariant = async (values: CreateVariantFormValues) => {
    setApiError(null);
    try {
      await addMutation.mutateAsync({
        sku: values.sku,
        barcode: values.barcode,
        cost_price: Number(values.cost_price),
        sale_price: Number(values.sale_price),
        reorder_point: Number(values.reorder_point || 0),
      });
      toast.success("Variant added successfully!");
      reset();
      setShowAddForm(false);
      refetch();
    } catch (err: unknown) {
      const problem = parseProblemDetails(err);
      setApiError(problem);
      toast.error(problem.title || "Failed to add variant");
    }
  };

  if (!product) return null;

  return (
    <Dialog
      isOpen={isOpen}
      onClose={onClose}
      title={`Variants for ${product.name}`}
      description={`Costing Method: ${product.costing_method.toUpperCase()} · Category #${product.category_id}`}
      className="max-w-3xl"
    >
      <div className="space-y-6 pt-2">
        {/* Parent Costing Method Info Banner */}
        <div className="flex items-center justify-between rounded-lg border border-slate-200 bg-slate-50 p-3 dark:border-slate-800 dark:bg-slate-900">
          <div className="flex items-center gap-2">
            <Layers className="h-4 w-4 text-teal-600" />
            <span className="text-xs font-semibold text-slate-700 dark:text-slate-200">
              Valuation Method:
            </span>
            <Badge variant="outline" className="font-mono text-xs uppercase">
              {product.costing_method}
            </Badge>
          </div>
          <PermissionGuard permission="product.manage">
            <Button
              size="sm"
              variant={showAddForm ? "secondary" : "default"}
              onClick={() => setShowAddForm(!showAddForm)}
              className="gap-1 text-xs"
            >
              <Plus className="h-3.5 w-3.5" />
              {showAddForm ? "Cancel Add Variant" : "Add New Variant"}
            </Button>
          </PermissionGuard>
        </div>

        {/* Add Variant Form */}
        {showAddForm && (
          <form
            onSubmit={handleSubmit(onAddVariant)}
            className="rounded-lg border border-teal-200 bg-teal-50/50 p-4 space-y-4 dark:border-teal-900/50 dark:bg-teal-950/20"
          >
            <h4 className="text-xs font-bold uppercase tracking-wider text-teal-800 dark:text-teal-300">
              Create New Variant
            </h4>

            {apiError && (
              <Alert variant="destructive">
                <ShieldAlert className="h-4 w-4" />
                <AlertTitle>{apiError.title}</AlertTitle>
                <AlertDescription>{apiError.detail}</AlertDescription>
              </Alert>
            )}

            <div className="grid grid-cols-1 sm:grid-cols-5 gap-3">
              <div className="space-y-1">
                <Label className="text-xs" required>SKU</Label>
                <Input {...register("sku")} placeholder="SKU-101" className="h-8 text-xs font-mono" />
                {errors.sku && <span className="text-[10px] text-red-500">{errors.sku.message}</span>}
              </div>

              <div className="space-y-1">
                <Label className="text-xs">Barcode</Label>
                <Input {...register("barcode")} placeholder="Barcode" className="h-8 text-xs font-mono" />
              </div>

              <div className="space-y-1">
                <Label className="text-xs" required>Cost Price ($)</Label>
                <Input type="number" step="0.01" {...register("cost_price")} className="h-8 text-xs tabular-nums" />
                {errors.cost_price && <span className="text-[10px] text-red-500">{errors.cost_price.message}</span>}
              </div>

              <div className="space-y-1">
                <Label className="text-xs" required>Sale Price ($)</Label>
                <Input type="number" step="0.01" {...register("sale_price")} className="h-8 text-xs tabular-nums" />
                {errors.sale_price && <span className="text-[10px] text-red-500">{errors.sale_price.message}</span>}
              </div>

              <div className="space-y-1">
                <Label className="text-xs">Reorder Pt</Label>
                <Input type="number" {...register("reorder_point")} className="h-8 text-xs tabular-nums" />
              </div>
            </div>

            <div className="flex justify-end">
              <Button type="submit" size="sm" isLoading={addMutation.isPending}>
                Save Variant
              </Button>
            </div>
          </form>
        )}

        {/* Variants List Table */}
        <div className="max-h-80 overflow-y-auto">
          <Table dense>
            <TableHeader>
              <TableRow>
                <TableHead>Variant ID</TableHead>
                <TableHead>SKU</TableHead>
                <TableHead>Barcode</TableHead>
                <TableHead>Unit Cost</TableHead>
                <TableHead>Sale Price</TableHead>
                <TableHead>Margin</TableHead>
                <TableHead>Reorder Pt</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {isLoading ? (
                <TableRow>
                  <TableCell colSpan={7} className="text-center py-6 text-xs text-slate-500">
                    Loading variants...
                  </TableCell>
                </TableRow>
              ) : !variants || variants.length === 0 ? (
                <TableRow>
                  <TableCell colSpan={7} className="text-center py-6 text-xs text-slate-500">
                    No variants found for this product.
                  </TableCell>
                </TableRow>
              ) : (
                variants.map((v) => {
                  const cost = Number(v.cost_price || 0);
                  const sale = Number(v.sale_price || 0);
                  const margin = sale > 0 ? (((sale - cost) / sale) * 100).toFixed(1) : "0.0";

                  return (
                    <TableRow key={v.id}>
                      <TableCell className="font-mono text-xs">#{v.id}</TableCell>
                      <TableCell className="font-mono font-bold">{v.sku}</TableCell>
                      <TableCell className="font-mono text-slate-500">{v.barcode || "—"}</TableCell>
                      <TableCell className="tabular-nums">{formatCurrency(cost)}</TableCell>
                      <TableCell className="tabular-nums font-semibold">{formatCurrency(sale)}</TableCell>
                      <TableCell className="tabular-nums font-medium text-emerald-600">{margin}%</TableCell>
                      <TableCell className="tabular-nums">{formatQuantity(v.reorder_point)}</TableCell>
                    </TableRow>
                  );
                })
              )}
            </TableBody>
          </Table>
        </div>

        <div className="flex justify-end pt-2">
          <Button variant="outline" onClick={onClose}>
            Close
          </Button>
        </div>
      </div>
    </Dialog>
  );
}
