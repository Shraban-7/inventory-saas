"use client";

import * as React from "react";
import Link from "next/link";
import { PageHeader } from "@/components/shell/page-header";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Skeleton } from "@/components/ui/skeleton";
import { PermissionGuard } from "@/components/shared/permission-guard";
import { StatusBadge } from "@/components/shared/status-badge";
import { EmptyState } from "@/components/shared/empty-state";
import { ProfitAndLossStatement } from "@/features/reports/components/profit-and-loss-statement";
import {
  useReportJobQuery,
  useProfitAndLossResultQuery,
} from "@/features/reports/api/reports-api";
import { loadRecentReportJobIds, rememberReportJobId } from "@/types/reports";
import { parseProblemDetails } from "@/lib/api-client";
import { BarChart3, Info, Loader2, Search, ShieldAlert } from "lucide-react";

/**
 * No GET /reports/jobs list endpoint exists.
 * Monitor by job UUID + recent IDs stored client-side after queue.
 */
export default function ReportJobsPage() {
  const [lookupId, setLookupId] = React.useState("");
  const [activeId, setActiveId] = React.useState<string | null>(null);
  const [recent, setRecent] = React.useState<string[]>([]);

  React.useEffect(() => {
    setTimeout(() => setRecent(loadRecentReportJobIds()), 0);
  }, [activeId]);

  const { data: job, isLoading, isError, error } = useReportJobQuery(activeId);
  const { data: result, isLoading: resultLoading } = useProfitAndLossResultQuery(
    activeId,
    job?.status,
  );

  const openJob = (id: string) => {
    const trimmed = id.trim();
    if (!trimmed) return;
    rememberReportJobId(trimmed);
    setActiveId(trimmed);
    setRecent(loadRecentReportJobIds());
  };

  return (
    <PermissionGuard permission="report.view" showBanner>
      <div className="space-y-6">
        <PageHeader
          title="Report Job Monitor"
          description="Poll a single report job by UUID. There is no jobs list API — recent IDs are stored locally after you queue a P&L."
          actions={
            <Link href="/reports/profit-and-loss">
              <Button size="sm" className="text-xs">
                Queue New P&amp;L
              </Button>
            </Link>
          }
        />

        <Alert>
          <Info className="h-4 w-4" />
          <AlertTitle>No report jobs index endpoint</AlertTitle>
          <AlertDescription>
            Backend exposes <code>GET /reports/jobs/&#123;id&#125;</code> and{" "}
            <code>.../result</code> only. This page does not invent a jobs catalog API.
          </AlertDescription>
        </Alert>

        <Card>
          <CardHeader>
            <CardTitle className="text-base flex items-center gap-2">
              <Search className="h-4 w-4 text-teal-600" />
              Look up job
            </CardTitle>
            <CardDescription>Paste a report job UUID to poll (2s while queued/running).</CardDescription>
          </CardHeader>
          <CardContent className="flex flex-col sm:flex-row gap-3 items-end">
            <div className="space-y-1 flex-1 w-full">
              <Label>Report Job ID</Label>
              <Input
                value={lookupId}
                onChange={(e) => setLookupId(e.target.value)}
                placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"
                className="h-9 text-xs font-mono"
              />
            </div>
            <Button
              type="button"
              className="text-xs"
              onClick={() => openJob(lookupId)}
              disabled={!lookupId.trim()}
            >
              Monitor Job
            </Button>
          </CardContent>
        </Card>

        {recent.length > 0 && (
          <Card>
            <CardHeader className="py-3 px-4">
              <CardTitle className="text-sm font-semibold">Recent jobs (this browser)</CardTitle>
            </CardHeader>
            <CardContent className="flex flex-wrap gap-2 px-4 pb-4">
              {recent.map((id) => (
                <Button
                  key={id}
                  size="sm"
                  variant={activeId === id ? "default" : "outline"}
                  className="font-mono text-[10px]"
                  onClick={() => openJob(id)}
                >
                  {id.slice(0, 8)}…
                </Button>
              ))}
            </CardContent>
          </Card>
        )}

        {!activeId && recent.length === 0 && (
          <EmptyState
            title="No jobs to monitor"
            description="Queue a Profit & Loss report first, then return here to inspect status."
            actionLabel="Go to P&L"
            onAction={() => {
              window.location.href = "/reports/profit-and-loss";
            }}
          />
        )}

        {activeId && (
          <Card>
            <CardHeader className="py-3 px-4 border-b border-slate-200 dark:border-slate-800 flex flex-row items-center justify-between gap-2">
              <div>
                <CardTitle className="text-sm font-semibold flex items-center gap-2">
                  <BarChart3 className="h-4 w-4 text-teal-600" />
                  Job detail
                  {job?.status && <StatusBadge status={String(job.status)} />}
                  {(job?.status === "queued" || job?.status === "running") && (
                    <Loader2 className="h-3.5 w-3.5 animate-spin text-teal-600" />
                  )}
                </CardTitle>
                <CardDescription className="font-mono text-[11px] break-all">{activeId}</CardDescription>
              </div>
            </CardHeader>
            <CardContent className="p-4 space-y-3">
              {isLoading && <Skeleton className="h-20 w-full" />}
              {isError && (
                <Alert variant="destructive">
                  <ShieldAlert className="h-4 w-4" />
                  <AlertTitle>Lookup failed</AlertTitle>
                  <AlertDescription>{parseProblemDetails(error).detail}</AlertDescription>
                </Alert>
              )}
              {job && (
                <div className="text-xs space-y-1 text-slate-600 dark:text-slate-400">
                  <div>
                    Type: <span className="font-mono">{job.type}</span>
                  </div>
                  <div>
                    Parameters: {job.parameters?.start || "—"} → {job.parameters?.end || "—"}
                    {job.parameters?.branch_id != null
                      ? ` · branch #${job.parameters.branch_id}`
                      : " · all authorized branches"}
                  </div>
                  {job.status === "failed" && (
                    <Alert variant="destructive">
                      <AlertTitle>Failed</AlertTitle>
                      <AlertDescription>
                        {job.error?.message || job.error?.code || "Generation failed"}
                      </AlertDescription>
                    </Alert>
                  )}
                  {job.status === "expired" && (
                    <Alert variant="warning">
                      <AlertTitle>Expired</AlertTitle>
                      <AlertDescription>Result is no longer available. Queue a new job.</AlertDescription>
                    </Alert>
                  )}
                </div>
              )}
            </CardContent>
          </Card>
        )}

        {job?.status === "completed" && (
          <>
            {resultLoading && <Skeleton className="h-40 w-full" />}
            {result && <ProfitAndLossStatement result={result} />}
          </>
        )}
      </div>
    </PermissionGuard>
  );
}
