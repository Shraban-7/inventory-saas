export type ReportJobStatus = "queued" | "running" | "completed" | "failed" | "expired";

export type ReportJobType = "profit_and_loss";

export interface ReportJobTimestamps {
  queued_at?: string | null;
  started_at?: string | null;
  completed_at?: string | null;
  expires_at?: string | null;
  created_at?: string | null;
  updated_at?: string | null;
}

export interface ReportJobError {
  code?: string | null;
  message?: string | null;
}

export interface ReportJob {
  id: string;
  type: ReportJobType | string;
  status: ReportJobStatus | string;
  parameters?: {
    start?: string;
    end?: string;
    branch_id?: number | null;
    branch_ids?: number[] | null;
  } | null;
  timestamps?: ReportJobTimestamps;
  result_url?: string | null;
  error?: ReportJobError | null;
}

export interface ProfitAndLossResult {
  revenue: string;
  cogs: string;
  gross_profit: string;
  operating_expenses: string;
  net_profit: string;
}

export interface QueueProfitAndLossPayload {
  start: string;
  end: string;
  branch_id?: number | null;
}

export const REPORT_JOB_TERMINAL: ReportJobStatus[] = ["completed", "failed", "expired"];

export const RECENT_REPORT_JOBS_KEY = "reports.recent_job_ids";

export function isTerminalReportStatus(status: string): boolean {
  return REPORT_JOB_TERMINAL.includes(status as ReportJobStatus);
}

export function rememberReportJobId(jobId: string): void {
  if (typeof window === "undefined") return;
  try {
    const raw = localStorage.getItem(RECENT_REPORT_JOBS_KEY);
    const existing: string[] = raw ? (JSON.parse(raw) as string[]) : [];
    const next = [jobId, ...existing.filter((id) => id !== jobId)].slice(0, 20);
    localStorage.setItem(RECENT_REPORT_JOBS_KEY, JSON.stringify(next));
  } catch {
    /* ignore */
  }
}

export function loadRecentReportJobIds(): string[] {
  if (typeof window === "undefined") return [];
  try {
    const raw = localStorage.getItem(RECENT_REPORT_JOBS_KEY);
    return raw ? (JSON.parse(raw) as string[]) : [];
  } catch {
    return [];
  }
}
