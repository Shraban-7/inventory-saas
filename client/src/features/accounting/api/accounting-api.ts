import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { apiClient } from "@/lib/api-client";
import {
  AccountingPeriod,
  ChartOfAccountNode,
  JournalEntry,
  JournalListResponse,
  ListJournalEntriesParams,
  ManualJournalPayload,
} from "@/types/accounting";

export const ACCOUNTING_QUERY_KEYS = {
  all: ["accounting"] as const,
  coa: () => [...ACCOUNTING_QUERY_KEYS.all, "coa"] as const,
  journals: (params: ListJournalEntriesParams) =>
    [...ACCOUNTING_QUERY_KEYS.all, "journals", params] as const,
  journalDetail: (id: number) => [...ACCOUNTING_QUERY_KEYS.all, "journal", id] as const,
};

export async function fetchChartOfAccounts(): Promise<ChartOfAccountNode[]> {
  const response = await apiClient.get<{ data: ChartOfAccountNode[] }>("/chart-of-accounts");
  return response.data.data;
}

export async function fetchJournalEntries(
  params: ListJournalEntriesParams,
): Promise<JournalListResponse> {
  const queryParams: Record<string, string | number> = {};
  if (params.date_from) queryParams.date_from = params.date_from;
  if (params.date_to) queryParams.date_to = params.date_to;
  if (params.reference_type) queryParams.reference_type = params.reference_type;
  if (params.per_page) queryParams.per_page = params.per_page;
  if (params.cursor) queryParams.cursor = params.cursor;

  const response = await apiClient.get<JournalListResponse>("/journal-entries", {
    params: queryParams,
  });
  return response.data;
}

export async function fetchJournalEntryById(id: number): Promise<JournalEntry> {
  const response = await apiClient.get<{ data: JournalEntry }>(`/journal-entries/${id}`);
  return response.data.data;
}

export async function createManualJournal(payload: ManualJournalPayload): Promise<JournalEntry> {
  const response = await apiClient.post<{ data: JournalEntry }>("/journal-entries", payload);
  return response.data.data;
}

export async function lockAccountingPeriod(periodId: number): Promise<AccountingPeriod> {
  const response = await apiClient.put<{ data: AccountingPeriod }>(
    `/accounting-periods/${periodId}/lock`,
  );
  return response.data.data;
}

export function useChartOfAccountsQuery() {
  return useQuery({
    queryKey: ACCOUNTING_QUERY_KEYS.coa(),
    queryFn: fetchChartOfAccounts,
  });
}

export function useJournalEntriesQuery(params: ListJournalEntriesParams) {
  return useQuery({
    queryKey: ACCOUNTING_QUERY_KEYS.journals(params),
    queryFn: () => fetchJournalEntries(params),
  });
}

export function useJournalEntryDetailQuery(id: number, enabled = true) {
  return useQuery({
    queryKey: ACCOUNTING_QUERY_KEYS.journalDetail(id),
    queryFn: () => fetchJournalEntryById(id),
    enabled: enabled && id > 0,
  });
}

export function useCreateManualJournalMutation() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: createManualJournal,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ACCOUNTING_QUERY_KEYS.all });
    },
  });
}

export function useLockAccountingPeriodMutation() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: lockAccountingPeriod,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ACCOUNTING_QUERY_KEYS.all });
    },
  });
}

export function accountingMutationToast(problem: {
  status: number;
  type?: string;
  title?: string;
  detail?: string;
}): string {
  if (problem.type === "urn:problem:accounting-period-locked" || problem.status === 422) {
    if (problem.type?.includes("period-locked") || problem.detail?.toLowerCase().includes("locked")) {
      return problem.detail || "Cannot post: selected accounting period is locked.";
    }
  }
  if (problem.status === 409) {
    return "This journal was already submitted (idempotency conflict).";
  }
  if (problem.status === 403) {
    return problem.detail || "Forbidden — manual journals require the Accountant role on the branch.";
  }
  return problem.title || problem.detail || "Request failed";
}
