export type StockAdjustmentType = "STOCK_ADJUSTMENT_IN" | "STOCK_ADJUSTMENT_OUT";

export interface StockAdjustmentPayload {
  variant_id: number;
  branch_id: number;
  quantity_delta: number;
  reason: string;
  type: StockAdjustmentType;
}

export interface StockAdjustment {
  id: number;
  tenant_id: number;
  branch_id: number;
  product_variant_id: number;
  quantity_delta: number;
  reason: string;
  type: StockAdjustmentType;
  created_at: string;
}

export interface StockTransferItemPayload {
  variant_id: number;
  quantity: number;
}

export interface StockTransferPayload {
  from_branch_id: number;
  to_branch_id: number;
  items: StockTransferItemPayload[];
}

export interface StockTransfer {
  id: number;
  tenant_id: number;
  from_branch_id: number;
  to_branch_id: number;
  status: string;
  created_at: string;
}

export type BulkImportType = "products" | "stock_adjustments";
export type BulkImportStatus = "queued" | "running" | "completed" | "failed";

export interface BulkImportJob {
  id: string;
  type: BulkImportType;
  status: BulkImportStatus;
  total_rows?: number;
  processed_rows?: number;
  failed_rows?: number;
  created_at: string;
}

export interface BulkImportErrorRow {
  id: number;
  row_number: number;
  error_message: string;
  row_data?: Record<string, unknown>;
}
