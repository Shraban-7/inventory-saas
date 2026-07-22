import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { apiClient } from "@/lib/api-client";
import {
  Customer,
  Invoice,
  InvoicePayload,
  RecordReceiptPayload,
  Receipt,
  CreditNote,
  CreditNotePayload,
  ListInvoicesParams,
  CursorPaginatedResponse,
} from "@/types/sales";
import { PaginatedResponse } from "@/types/products";

export const SALES_QUERY_KEYS = {
  all: ["sales"] as const,
  customers: () => [...SALES_QUERY_KEYS.all, "customers"] as const,
  invoices: (params: ListInvoicesParams) => [...SALES_QUERY_KEYS.all, "invoices", params] as const,
  invoiceDetail: (id: number) => [...SALES_QUERY_KEYS.all, "invoice", id] as const,
  creditNotes: () => [...SALES_QUERY_KEYS.all, "credit-notes"] as const,
};

// Customer API

export async function fetchCustomers(): Promise<PaginatedResponse<Customer>> {
  const response = await apiClient.get<PaginatedResponse<Customer>>("/customers");
  return response.data;
}

export async function createCustomer(payload: Partial<Customer>): Promise<Customer> {
  const response = await apiClient.post<{ data: Customer }>("/customers", payload);
  return response.data.data;
}

// Invoice API

export async function fetchInvoices(params: ListInvoicesParams): Promise<CursorPaginatedResponse<Invoice>> {
  const queryParams: Record<string, string | number> = {};
  if (params.status) queryParams.status = params.status;
  if (params.date_from) queryParams.date_from = params.date_from;
  if (params.date_to) queryParams.date_to = params.date_to;
  if (params.customer_id) queryParams.customer_id = params.customer_id;
  if (params.per_page) queryParams.per_page = params.per_page;
  if (params.cursor) queryParams.cursor = params.cursor;

  const response = await apiClient.get<CursorPaginatedResponse<Invoice>>("/invoices", {
    params: queryParams,
  });
  return response.data;
}

export async function fetchInvoiceById(id: number): Promise<Invoice> {
  const response = await apiClient.get<{ data: Invoice }>(`/invoices/${id}`);
  return response.data.data;
}

export async function createInvoice(payload: InvoicePayload): Promise<Invoice> {
  const response = await apiClient.post<{ data: Invoice }>("/invoices", payload);
  return response.data.data;
}

export async function recordInvoiceReceipt(invoiceId: number, payload: RecordReceiptPayload): Promise<Receipt> {
  const response = await apiClient.post<{ data: Receipt }>(`/invoices/${invoiceId}/receipts`, payload);
  return response.data.data;
}

export async function voidInvoice(invoiceId: number, reason: string): Promise<Invoice> {
  const response = await apiClient.put<{ data: Invoice }>(`/invoices/${invoiceId}/void`, { reason });
  return response.data.data;
}

// Credit Notes API

export async function fetchCreditNotes(): Promise<PaginatedResponse<CreditNote>> {
  const response = await apiClient.get<PaginatedResponse<CreditNote>>("/credit-notes");
  return response.data;
}

export async function createCreditNote(payload: CreditNotePayload): Promise<CreditNote> {
  const response = await apiClient.post<{ data: CreditNote }>("/credit-notes", payload);
  return response.data.data;
}

export async function approveCreditNote(id: number): Promise<CreditNote> {
  const response = await apiClient.put<{ data: CreditNote }>(`/credit-notes/${id}/approve`);
  return response.data.data;
}

// React Query Hooks

export function useCustomersQuery() {
  return useQuery({
    queryKey: SALES_QUERY_KEYS.customers(),
    queryFn: fetchCustomers,
  });
}

export function useCreateCustomerMutation() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: createCustomer,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: SALES_QUERY_KEYS.customers() });
    },
  });
}

export function useInvoicesQuery(params: ListInvoicesParams) {
  return useQuery({
    queryKey: SALES_QUERY_KEYS.invoices(params),
    queryFn: () => fetchInvoices(params),
  });
}

export function useInvoiceDetailQuery(id: number, enabled = true) {
  return useQuery({
    queryKey: SALES_QUERY_KEYS.invoiceDetail(id),
    queryFn: () => fetchInvoiceById(id),
    enabled: enabled && id > 0,
  });
}

export function useCreateInvoiceMutation() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: createInvoice,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: SALES_QUERY_KEYS.all });
      queryClient.invalidateQueries({ queryKey: ["products"] });
      queryClient.invalidateQueries({ queryKey: ["inventory"] });
    },
  });
}

export function useRecordReceiptMutation(invoiceId: number) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (payload: RecordReceiptPayload) => recordInvoiceReceipt(invoiceId, payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: SALES_QUERY_KEYS.invoiceDetail(invoiceId) });
      queryClient.invalidateQueries({ queryKey: SALES_QUERY_KEYS.all });
    },
  });
}

export function useVoidInvoiceMutation(invoiceId: number) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (reason: string) => voidInvoice(invoiceId, reason),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: SALES_QUERY_KEYS.invoiceDetail(invoiceId) });
      queryClient.invalidateQueries({ queryKey: SALES_QUERY_KEYS.all });
      queryClient.invalidateQueries({ queryKey: ["products"] });
      queryClient.invalidateQueries({ queryKey: ["inventory"] });
    },
  });
}

export function useCreditNotesQuery() {
  return useQuery({
    queryKey: SALES_QUERY_KEYS.creditNotes(),
    queryFn: fetchCreditNotes,
  });
}

export function useCreateCreditNoteMutation() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: createCreditNote,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: SALES_QUERY_KEYS.creditNotes() });
      queryClient.invalidateQueries({ queryKey: SALES_QUERY_KEYS.all });
    },
  });
}

export function useApproveCreditNoteMutation() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => approveCreditNote(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: SALES_QUERY_KEYS.creditNotes() });
      queryClient.invalidateQueries({ queryKey: SALES_QUERY_KEYS.all });
    },
  });
}
