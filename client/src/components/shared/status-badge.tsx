import * as React from "react";
import { Badge } from "@/components/ui/badge";

export type EntityStatus =
  | "draft"
  | "pending"
  | "confirmed"
  | "approved"
  | "completed"
  | "cancelled"
  | "void"
  | "paid"
  | "unpaid"
  | "partially_paid"
  | "stock_adjustment_in"
  | "stock_adjustment_out"
  | "low_stock"
  | "out_of_stock"
  | "queued"
  | "running"
  | "failed"
  | "expired"
  | string;

interface StatusBadgeProps {
  status: EntityStatus;
  customLabel?: string;
  className?: string;
}

/**
 * Standardized status badge component mapping backend enum statuses to design system semantic tokens.
 */
export function StatusBadge({ status, customLabel, className }: StatusBadgeProps) {
  const normalized = (status || "").toLowerCase().trim();

  let variant: "default" | "secondary" | "destructive" | "outline" | "success" | "warning" | "info" = "outline";
  let label = customLabel || status;

  switch (normalized) {
    case "draft":
      variant = "secondary";
      label = customLabel || "Draft";
      break;
    case "pending":
    case "queued":
      variant = "warning";
      label = customLabel || (normalized === "queued" ? "Queued" : "Pending");
      break;
    case "running":
      variant = "info";
      label = customLabel || "Running";
      break;
    case "confirmed":
    case "approved":
      variant = "info";
      label = customLabel || (normalized === "approved" ? "Approved" : "Confirmed");
      break;
    case "completed":
    case "paid":
      variant = "success";
      label = customLabel || (normalized === "paid" ? "Paid" : "Completed");
      break;
    case "partially_paid":
    case "partial":
      variant = "info";
      label = customLabel || "Partially Paid";
      break;
    case "unpaid":
      variant = "warning";
      label = customLabel || "Unpaid";
      break;
    case "cancelled":
    case "void":
    case "failed":
    case "expired":
      variant = "destructive";
      label = customLabel || (normalized === "void" ? "Voided" : normalized.toUpperCase());
      break;
    case "stock_adjustment_in":
      variant = "success";
      label = customLabel || "Adjustment In (+)";
      break;
    case "stock_adjustment_out":
      variant = "destructive";
      label = customLabel || "Adjustment Out (-)";
      break;
    case "low_stock":
      variant = "warning";
      label = customLabel || "Low Stock";
      break;
    case "out_of_stock":
      variant = "destructive";
      label = customLabel || "Out of Stock";
      break;
    default:
      variant = "outline";
      label = customLabel || status;
      break;
  }

  return (
    <Badge variant={variant} className={className}>
      {label}
    </Badge>
  );
}
