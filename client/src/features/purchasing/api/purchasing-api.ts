import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { apiClient } from "@/lib/api-client";
import {
  Supplier,
  PurchaseOrder,
  PurchaseOrderPayload,
  GoodsReceiptNote,
  GoodsReceiptNotePayload,
  Bill,
  BillPayload,
  BillPayment,
  RecordBillPaymentPayload,
  ListPurchaseOrdersParams,
  ListBillsParams,
  ListSuppliersParams,
  ListGrnsParams,
} from "@/types/purchasing";
import { PaginatedResponse } from "@/types/products";

export const PURCHASING_QUERY_KEYS = {
  all: ["purchasing"] as const,
  suppliers: (params?: ListSuppliersParams) =>
    [...PURCHASING_QUERY_KEYS.all, "suppliers", params ?? {}] as const,
  supplierDetail: (id: number) => [...PURCHASING_QUERY_KEYS.all, "supplier", id] as const,
  orders: (params: ListPurchaseOrdersParams) =>
    [...PURCHASING_QUERY_KEYS.all, "orders", params] as const,
  grns: (params?: ListGrnsParams) => [...PURCHASING_QUERY_KEYS.all, "grns", params ?? {}] as const,
  bills: (params: ListBillsParams) => [...PURCHASING_QUERY_KEYS.all, "bills", params] as const,
};

export async function fetchSuppliers(
  params: ListSuppliersParams = {},
): Promise<PaginatedResponse<Supplier>> {
  const response = await apiClient.get<PaginatedResponse<Supplier>>("/suppliers", {
    params: {
      page: params.page,
      per_page: params.per_page ?? 50,
    },
  });
  return response.data;
}

export async function fetchSupplierById(id: number): Promise<Supplier> {
  const response = await apiClient.get<{ data: Supplier }>(`/suppliers/${id}`);
  return response.data.data;
}

export async function createSupplier(payload: Partial<Supplier>): Promise<Supplier> {
  const response = await apiClient.post<{ data: Supplier }>("/suppliers", payload);
  return response.data.data;
}

export async function updateSupplier(
  id: number,
  payload: Partial<Supplier>,
): Promise<Supplier> {
  const response = await apiClient.put<{ data: Supplier }>(`/suppliers/${id}`, payload);
  return response.data.data;
}

export async function fetchPurchaseOrders(
  params: ListPurchaseOrdersParams,
): Promise<PaginatedResponse<PurchaseOrder>> {
  const queryParams: Record<string, string | number> = {};
  if (params.status) queryParams.status = params.status;
  if (params.supplier_id) queryParams.supplier_id = params.supplier_id;
  if (params.page) queryParams.page = params.page;
  if (params.per_page) queryParams.per_page = params.per_page;

  const response = await apiClient.get<PaginatedResponse<PurchaseOrder>>("/purchase-orders", {
    params: queryParams,
  });
  return response.data;
}

export async function createPurchaseOrder(payload: PurchaseOrderPayload): Promise<PurchaseOrder> {
  const response = await apiClient.post<{ data: PurchaseOrder }>("/purchase-orders", payload);
  return response.data.data;
}

export async function confirmPurchaseOrder(id: number): Promise<PurchaseOrder> {
  const response = await apiClient.put<{ data: PurchaseOrder }>(
    `/purchase-orders/${id}/confirm`,
  );
  return response.data.data;
}

export async function cancelPurchaseOrder(id: number): Promise<PurchaseOrder> {
  const response = await apiClient.put<{ data: PurchaseOrder }>(
    `/purchase-orders/${id}/cancel`,
  );
  return response.data.data;
}

export async function fetchGoodsReceiptNotes(
  params: ListGrnsParams = {},
): Promise<PaginatedResponse<GoodsReceiptNote>> {
  const response = await apiClient.get<PaginatedResponse<GoodsReceiptNote>>(
    "/goods-receipt-notes",
    {
      params: {
        page: params.page,
        per_page: params.per_page ?? 50,
      },
    },
  );
  return response.data;
}

export async function createGoodsReceiptNote(
  payload: GoodsReceiptNotePayload,
): Promise<GoodsReceiptNote> {
  const response = await apiClient.post<{ data: GoodsReceiptNote }>(
    "/goods-receipt-notes",
    payload,
  );
  return response.data.data;
}

export async function fetchBills(params: ListBillsParams): Promise<PaginatedResponse<Bill>> {
  const queryParams: Record<string, string | number> = {};
  if (params.status) queryParams.status = params.status;
  if (params.supplier_id) queryParams.supplier_id = params.supplier_id;
  if (params.page) queryParams.page = params.page;
  if (params.per_page) queryParams.per_page = params.per_page;

  const response = await apiClient.get<PaginatedResponse<Bill>>("/bills", {
    params: queryParams,
  });
  return response.data;
}

export async function createBill(payload: BillPayload): Promise<Bill> {
  const response = await apiClient.post<{ data: Bill }>("/bills", payload);
  return response.data.data;
}

export async function approveBill(id: number): Promise<Bill> {
  const response = await apiClient.put<{ data: Bill }>(`/bills/${id}/approve`);
  return response.data.data;
}

export async function recordBillPayment(
  billId: number,
  payload: RecordBillPaymentPayload,
): Promise<BillPayment> {
  const response = await apiClient.post<{ data: BillPayment }>(
    `/bills/${billId}/payments`,
    payload,
  );
  return response.data.data;
}

export function useSuppliersQuery(params: ListSuppliersParams = {}) {
  return useQuery({
    queryKey: PURCHASING_QUERY_KEYS.suppliers(params),
    queryFn: () => fetchSuppliers(params),
  });
}

export function useSupplierDetailQuery(id: number, enabled = true) {
  return useQuery({
    queryKey: PURCHASING_QUERY_KEYS.supplierDetail(id),
    queryFn: () => fetchSupplierById(id),
    enabled: enabled && id > 0,
  });
}

export function useCreateSupplierMutation() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: createSupplier,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: PURCHASING_QUERY_KEYS.all });
    },
  });
}

export function useUpdateSupplierMutation() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: Partial<Supplier> }) =>
      updateSupplier(id, payload),
    onSuccess: (_data, vars) => {
      queryClient.invalidateQueries({ queryKey: PURCHASING_QUERY_KEYS.all });
      queryClient.invalidateQueries({
        queryKey: PURCHASING_QUERY_KEYS.supplierDetail(vars.id),
      });
    },
  });
}

export function usePurchaseOrdersQuery(params: ListPurchaseOrdersParams) {
  return useQuery({
    queryKey: PURCHASING_QUERY_KEYS.orders(params),
    queryFn: () => fetchPurchaseOrders(params),
  });
}

export function useCreatePurchaseOrderMutation() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: createPurchaseOrder,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: PURCHASING_QUERY_KEYS.all });
    },
  });
}

export function useConfirmPurchaseOrderMutation() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: confirmPurchaseOrder,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: PURCHASING_QUERY_KEYS.all });
    },
  });
}

export function useCancelPurchaseOrderMutation() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: cancelPurchaseOrder,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: PURCHASING_QUERY_KEYS.all });
    },
  });
}

export function useGoodsReceiptNotesQuery(params: ListGrnsParams = {}) {
  return useQuery({
    queryKey: PURCHASING_QUERY_KEYS.grns(params),
    queryFn: () => fetchGoodsReceiptNotes(params),
  });
}

export function useCreateGoodsReceiptNoteMutation() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: createGoodsReceiptNote,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: PURCHASING_QUERY_KEYS.all });
      queryClient.invalidateQueries({ queryKey: ["inventory"] });
      queryClient.invalidateQueries({ queryKey: ["products"] });
    },
  });
}

export function useBillsQuery(params: ListBillsParams) {
  return useQuery({
    queryKey: PURCHASING_QUERY_KEYS.bills(params),
    queryFn: () => fetchBills(params),
  });
}

export function useCreateBillMutation() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: createBill,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: PURCHASING_QUERY_KEYS.all });
    },
  });
}

export function useApproveBillMutation() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: approveBill,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: PURCHASING_QUERY_KEYS.all });
    },
  });
}

export function useRecordBillPaymentMutation(billId: number) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (payload: RecordBillPaymentPayload) => recordBillPayment(billId, payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: PURCHASING_QUERY_KEYS.all });
    },
  });
}

export function mutationErrorToast(problem: {
  status: number;
  title?: string;
  detail?: string;
}): string {
  if (problem.status === 409) {
    return "This transaction was already submitted (idempotency conflict). Refresh and open the existing record.";
  }
  return problem.title || problem.detail || "Request failed";
}
