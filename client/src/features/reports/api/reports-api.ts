import { useMutation, useQuery } from "@tanstack/react-query";
import { apiClient } from "@/lib/api-client";
import {
  isTerminalReportStatus,
  ProfitAndLossResult,
  QueueProfitAndLossPayload,
  rememberReportJobId,
  ReportJob,
} from "@/types/reports";

export const REPORTS_QUERY_KEYS = {
  all: ["reports"] as const,
  job: (id: string) => [...REPORTS_QUERY_KEYS.all, "job", id] as const,
  result: (id: string) => [...REPORTS_QUERY_KEYS.all, "result", id] as const,
};

export async function queueProfitAndLoss(
  payload: QueueProfitAndLossPayload,
): Promise<ReportJob> {
  const body: Record<string, string | number> = {
    start: payload.start,
    end: payload.end,
  };
  if (payload.branch_id != null) {
    body.branch_id = payload.branch_id;
  }

  const response = await apiClient.post<{ data: ReportJob }>(
    "/reports/profit-and-loss",
    body,
  );
  return response.data.data;
}

export async function fetchReportJob(jobId: string): Promise<ReportJob> {
  const response = await apiClient.get<{ data: ReportJob }>(`/reports/jobs/${jobId}`);
  return response.data.data;
}

export async function fetchProfitAndLossResult(
  jobId: string,
): Promise<ProfitAndLossResult> {
  const response = await apiClient.get<{ data: ProfitAndLossResult }>(
    `/reports/jobs/${jobId}/result`,
  );
  return response.data.data;
}

export function useQueueProfitAndLossMutation() {
  return useMutation({
    mutationFn: queueProfitAndLoss,
    onSuccess: (job) => {
      rememberReportJobId(job.id);
    },
  });
}

export function useReportJobQuery(jobId: string | null, enabled = true) {
  return useQuery({
    queryKey: REPORTS_QUERY_KEYS.job(jobId || ""),
    queryFn: () => fetchReportJob(jobId!),
    enabled: enabled && !!jobId,
    refetchInterval: (query) => {
      const status = query.state.data?.status;
      if (!status || isTerminalReportStatus(String(status))) {
        return false;
      }
      return 2000;
    },
  });
}

export function useProfitAndLossResultQuery(
  jobId: string | null,
  status: string | undefined,
) {
  const ready = status === "completed" && !!jobId;
  return useQuery({
    queryKey: REPORTS_QUERY_KEYS.result(jobId || ""),
    queryFn: () => fetchProfitAndLossResult(jobId!),
    enabled: ready,
  });
}
