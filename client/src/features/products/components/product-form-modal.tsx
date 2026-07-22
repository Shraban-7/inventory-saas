"use client";

import * as React from "react";
import { useForm, useFieldArray } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { Plus, Trash2, ShieldAlert } from "lucide-react";
import { Dialog } from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Label } from "@/components/ui/label";
import { Select } from "@/components/ui/select";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { createProductSchema, CreateProductFormValues } from "../schemas/product-schema";
import { useCreateProductMutation } from "../api/product-api";
import { parseProblemDetails, ProblemDetails } from "@/lib/api-client";
import { toast } from "sonner";

interface ProductFormModalProps {
  isOpen: boolean;
  onClose: () => void;
}

export function ProductFormModal({ isOpen, onClose }: ProductFormModalProps) {
  const createMutation = useCreateProductMutation();
  const [apiError, setApiError] = React.useState<ProblemDetails | null>(null);

  const {
    register,
    control,
    handleSubmit,
    reset,
    formState: { errors },
  } = useForm({
    resolver: zodResolver(createProductSchema),
    defaultValues: {
      category_id: 1,
      name: "",
      description: "",
      costing_method: "fifo" as const,
      variants: [
        {
          sku: "",
          barcode: "",
          cost_price: 0,
          sale_price: 0,
          reorder_point: 5,
        },
      ],
    },
  });

  const { fields, append, remove } = useFieldArray({
    control,
    name: "variants",
  });

  const onSubmit = async (values: CreateProductFormValues) => {
    setApiError(null);
    try {
      await createMutation.mutateAsync({
        category_id: Number(values.category_id),
        name: values.name,
        description: values.description,
        costing_method: values.costing_method,
        variants: values.variants.map((v) => ({
          sku: v.sku,
          barcode: v.barcode,
          cost_price: Number(v.cost_price),
          sale_price: Number(v.sale_price),
          reorder_point: Number(v.reorder_point || 0),
        })),
      });
      toast.success("Product and variants created successfully!");
      reset();
      onClose();
    } catch (err: unknown) {
      const problem = parseProblemDetails(err);
      setApiError(problem);
      toast.error(problem.title || "Failed to create product");
    }
  };

  return (
    <Dialog
      isOpen={isOpen}
      onClose={onClose}
      title="Create New Product"
      description="Define a new product header with costing method and initial SKU variants."
      className="max-w-2xl"
    >
      <form onSubmit={handleSubmit(onSubmit)} className="space-y-6 pt-2">
        {apiError && (
          <Alert variant="destructive">
            <ShieldAlert className="h-4 w-4" />
            <AlertTitle>{apiError.title}</AlertTitle>
            <AlertDescription>
              {apiError.detail}
              {apiError.errors && (
                <ul className="mt-1 list-disc pl-4 text-xs">
                  {Object.entries(apiError.errors).map(([field, msgs]) => (
                    <li key={field}>
                      <strong>{field}:</strong> {Array.isArray(msgs) ? msgs.join(", ") : msgs}
                    </li>
                  ))}
                </ul>
              )}
            </AlertDescription>
          </Alert>
        )}

        {/* Product General Info */}
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div className="space-y-1">
            <Label required>Product Name</Label>
            <Input {...register("name")} placeholder="e.g. Mechanical Ergonomic Keyboard" />
            {errors.name && <span className="text-xs text-red-500">{errors.name.message}</span>}
          </div>

          <div className="space-y-1">
            <div className="flex items-center justify-between">
              <Label required>Category ID</Label>
              <Badge variant="outline" className="text-[10px] text-amber-600">GET /categories Pending</Badge>
            </div>
            <Input type="number" {...register("category_id")} placeholder="1" />
            {errors.category_id && <span className="text-xs text-red-500">{errors.category_id.message}</span>}
          </div>

          <div className="space-y-1">
            <Label required>Inventory Costing Method</Label>
            <Select
              {...register("costing_method")}
              options={[
                { label: "FIFO (First In, First Out)", value: "fifo" },
                { label: "AVCO (Weighted Average Cost)", value: "avco" },
              ]}
            />
            <span className="text-[11px] text-slate-500">Determines lot valuation method.</span>
          </div>

          <div className="space-y-1">
            <Label>Description / Specs</Label>
            <Textarea {...register("description")} placeholder="Optional product details..." rows={2} />
          </div>
        </div>

        {/* Variants Section */}
        <div className="space-y-3 border-t border-slate-200 pt-4 dark:border-slate-800">
          <div className="flex items-center justify-between">
            <h4 className="text-sm font-semibold text-slate-900 dark:text-slate-100">
              Product Variants (Min 1 Required)
            </h4>
            <Button
              type="button"
              variant="outline"
              size="sm"
              onClick={() =>
                append({
                  sku: "",
                  barcode: "",
                  cost_price: 0,
                  sale_price: 0,
                  reorder_point: 5,
                })
              }
              className="gap-1 text-xs"
            >
              <Plus className="h-3.5 w-3.5" /> Add Variant Line
            </Button>
          </div>

          {errors.variants?.message && (
            <span className="text-xs text-red-500">{errors.variants.message}</span>
          )}

          <div className="space-y-3 max-h-60 overflow-y-auto pr-1">
            {fields.map((field, index) => (
              <div
                key={field.id}
                className="relative grid grid-cols-1 sm:grid-cols-5 gap-2 rounded-md border border-slate-200 p-3 bg-slate-50 dark:border-slate-800 dark:bg-slate-900/50"
              >
                <div className="space-y-1">
                  <Label className="text-[11px]" required>SKU</Label>
                  <Input
                    {...register(`variants.${index}.sku` as const)}
                    placeholder="KB-BLK-01"
                    className="h-8 text-xs font-mono"
                  />
                  {errors.variants?.[index]?.sku && (
                    <span className="text-[10px] text-red-500">{errors.variants[index]?.sku?.message}</span>
                  )}
                </div>

                <div className="space-y-1">
                  <Label className="text-[11px]">Barcode</Label>
                  <Input
                    {...register(`variants.${index}.barcode` as const)}
                    placeholder="EAN-13 / UPC"
                    className="h-8 text-xs font-mono"
                  />
                </div>

                <div className="space-y-1">
                  <Label className="text-[11px]" required>Cost ($)</Label>
                  <Input
                    type="number"
                    step="0.01"
                    {...register(`variants.${index}.cost_price` as const)}
                    className="h-8 text-xs tabular-nums"
                  />
                  {errors.variants?.[index]?.cost_price && (
                    <span className="text-[10px] text-red-500">{errors.variants[index]?.cost_price?.message}</span>
                  )}
                </div>

                <div className="space-y-1">
                  <Label className="text-[11px]" required>Sale ($)</Label>
                  <Input
                    type="number"
                    step="0.01"
                    {...register(`variants.${index}.sale_price` as const)}
                    className="h-8 text-xs tabular-nums"
                  />
                  {errors.variants?.[index]?.sale_price && (
                    <span className="text-[10px] text-red-500">{errors.variants[index]?.sale_price?.message}</span>
                  )}
                </div>

                <div className="flex items-end gap-1">
                  <div className="space-y-1 flex-1">
                    <Label className="text-[11px]">Reorder Pt</Label>
                    <Input
                      type="number"
                      {...register(`variants.${index}.reorder_point` as const)}
                      className="h-8 text-xs tabular-nums"
                    />
                  </div>
                  {fields.length > 1 && (
                    <Button
                      type="button"
                      variant="ghost"
                      size="icon"
                      onClick={() => remove(index)}
                      className="h-8 w-8 text-red-500 hover:bg-red-50"
                    >
                      <Trash2 className="h-3.5 w-3.5" />
                    </Button>
                  )}
                </div>
              </div>
            ))}
          </div>
        </div>

        <div className="flex justify-end gap-3 pt-4 border-t border-slate-200 dark:border-slate-800">
          <Button type="button" variant="outline" onClick={onClose}>
            Cancel
          </Button>
          <Button type="submit" isLoading={createMutation.isPending}>
            Save Product & Variants
          </Button>
        </div>
      </form>
    </Dialog>
  );
}
