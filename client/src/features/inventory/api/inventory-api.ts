import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { apiClient } from "@/lib/api-client";
import {
  StockAdjustmentPayload,
  StockAdjustment,
  StockTransferPayload,
  StockTransfer,
  BulkImportJob,
  BulkImportErrorRow,
  BulkImportType,
} from "@/types/inventory";

export const INVENTORY_QUERY_KEYS = {
  all: ["inventory"] as const,
  adjustments: () => [...INVENTORY_QUERY_KEYS.all, "adjustments"] as const,
  transfers: () => [...INVENTORY_QUERY_KEYS.all, "transfers"] as const,
  bulkStatus: (id: string) => [...INVENTORY_QUERY_KEYS.all, "bulk", id] as const,
  bulkErrors: (id: string) => [...INVENTORY_QUERY_KEYS.all, "bulk-errors", id] as const,
};

/**
 * Perform manual stock adjustment (In/Out).
 */
export async function createStockAdjustment(payload: StockAdjustmentPayload): Promise<StockAdjustment> {
  // Client rule: Ensure delta is signed according to type
  const signedDelta =
    payload.type === "STOCK_ADJUSTMENT_OUT"
      ? -Math.abs(payload.quantity_delta)
      : Math.abs(payload.quantity_delta);

  const response = await apiClient.post<{ data: StockAdjustment }>("/stock-adjustments", {
    ...payload,
    quantity_delta: signedDelta,
  });
  return response.data.data;
}

/**
 * Execute inter-branch stock transfer.
 */
export async function createStockTransfer(payload: StockTransferPayload): Promise<StockTransfer> {
  const response = await apiClient.post<{ data: StockTransfer }>("/stock-transfers", payload);
  return response.data.data;
}

/**
 * Upload bulk CSV file for products or stock adjustments.
 */
export async function uploadBulkCsv(file: File, type: BulkImportType): Promise<BulkImportJob> {
  const formData = new FormData();
  formData.append("csv", file);

  const endpoint = type === "products" ? "/bulk/products" : "/bulk/stock-adjustments";
  const response = await apiClient.post<{ data: BulkImportJob }>(endpoint, formData, {
    headers: {
      "Content-Type": "multipart/form-data",
    },
  });
  return response.data.data;
}

/**
 * Fetch bulk import job status.
 */
export async function fetchBulkImportStatus(id: string): Promise<BulkImportJob> {
  const response = await apiClient.get<{ data: BulkImportJob }>(`/bulk/imports/${id}`);
  return response.data.data;
}

/**
 * Fetch failed row errors for a bulk import job.
 */
export async function fetchBulkImportErrors(id: string): Promise<BulkImportErrorRow[]> {
  const response = await apiClient.get<{ data: BulkImportErrorRow[] }>(`/bulk/imports/${id}/errors`);
  return response.data.data;
}

// React Query Hooks

export function useCreateStockAdjustmentMutation() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: createStockAdjustment,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: INVENTORY_QUERY_KEYS.all });
      queryClient.invalidateQueries({ queryKey: ["products"] });
    },
  });
}

export function useCreateStockTransferMutation() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: createStockTransfer,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: INVENTORY_QUERY_KEYS.all });
      queryClient.invalidateQueries({ queryKey: ["products"] });
    },
  });
}

export function useUploadBulkCsvMutation() {
  return useMutation({
    mutationFn: ({ file, type }: { file: File; type: BulkImportType }) => uploadBulkCsv(file, type),
  });
}

export function useBulkImportStatusQuery(id: string, enabled = true) {
  return useQuery({
    queryKey: INVENTORY_QUERY_KEYS.bulkStatus(id),
    queryFn: () => fetchBulkImportStatus(id),
    enabled: enabled && !!id,
    refetchInterval: (query) => {
      const status = query.state.data?.status;
      if (status === "queued" || status === "running") {
        return 2000; // Poll every 2 seconds while job is active
      }
      return false;
    },
  });
}

export function useBulkImportErrorsQuery(id: string, enabled = false) {
  return useQuery({
    queryKey: INVENTORY_QUERY_KEYS.bulkErrors(id),
    queryFn: () => fetchBulkImportErrors(id),
    enabled: enabled && !!id,
  });
}
