"use client";

import * as React from "react";
import Link from "next/link";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { PageHeader } from "@/components/shell/page-header";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select } from "@/components/ui/select";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Skeleton } from "@/components/ui/skeleton";
import { PermissionGuard } from "@/components/shared/permission-guard";
import { StatusBadge } from "@/components/shared/status-badge";
import { ProfitAndLossStatement } from "@/features/reports/components/profit-and-loss-statement";
import {
  useQueueProfitAndLossMutation,
  useReportJobQuery,
  useProfitAndLossResultQuery,
} from "@/features/reports/api/reports-api";
import {
  queueProfitAndLossSchema,
  QueueProfitAndLossFormValues,
} from "@/features/reports/schemas/report-schemas";
import { useAuthStore } from "@/lib/stores/auth-store";
import { useShellStore } from "@/lib/stores/shell-store";
import { parseProblemDetails, ProblemDetails } from "@/lib/api-client";
import { PieChart, ShieldAlert, Loader2, ExternalLink } from "lucide-react";
import { toast } from "sonner";

export default function ProfitAndLossPage() {
  const { branches } = useAuthStore();
  const { activeBranchId } = useShellStore();
  const queueMutation = useQueueProfitAndLossMutation();

  const [jobId, setJobId] = React.useState<string | null>(null);
  const [apiError, setApiError] = React.useState<ProblemDetails | null>(null);

  const today = new Date().toISOString().split("T")[0];
  const monthStart = `${today.slice(0, 8)}01`;

  const { register, handleSubmit, formState: { errors } } = useForm({
    resolver: zodResolver(queueProfitAndLossSchema),
    defaultValues: {
      start: monthStart,
      end: today,
      branch_id: undefined as number | undefined,
    },
  });

  const { data: job, isError: jobError, error: jobErr } = useReportJobQuery(jobId);
  const { data: result, isLoading: resultLoading, isError: resultError, error: resultErr } =
    useProfitAndLossResultQuery(jobId, job?.status);

  const onSubmit = async (values: QueueProfitAndLossFormValues) => {
    setApiError(null);
    setJobId(null);
    try {
      const job = await queueMutation.mutateAsync({
        start: values.start,
        end: values.end,
        branch_id: values.branch_id ?? null,
      });
      setJobId(job.id);
      toast.success(`P&L job queued (${job.id.slice(0, 8)}…)`);
    } catch (err: unknown) {
      const problem = parseProblemDetails(err);
      setApiError(problem);
      toast.error(problem.title || "Failed to queue P&L report");
    }
  };

  const branchOptions = [
    { label: "All authorized branches", value: "" },
    ...branches.map((b) => ({
      label: `${b.name}${b.id === activeBranchId ? " (active)" : ""}`,
      value: b.id,
    })),
  ];

  return (
    <PermissionGuard permission="report.view" showBanner>
      <div className="space-y-6">
        <PageHeader
          title="Profit & Loss"
          description="Queue an async P&L job (202 Accepted), poll status every 2s, then load the result."
          actions={
            <Link href="/reports/jobs">
              <Button variant="outline" size="sm" className="gap-1 text-xs">
                <ExternalLink className="h-3.5 w-3.5" /> Job Monitor
              </Button>
            </Link>
          }
        />

        <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
          {apiError && (
            <Alert variant="destructive">
              <ShieldAlert className="h-4 w-4" />
              <AlertTitle>{apiError.title}</AlertTitle>
              <AlertDescription>{apiError.detail}</AlertDescription>
            </Alert>
          )}

          <Card>
            <CardHeader>
              <CardTitle className="text-base flex items-center gap-2">
                <PieChart className="h-4 w-4 text-teal-600" />
                Report Parameters
              </CardTitle>
              <CardDescription>
                Body matches QueueProfitAndLossReportRequest: <code>start</code>, <code>end</code>,
                optional <code>branch_id</code>.
              </CardDescription>
            </CardHeader>
            <CardContent className="grid grid-cols-1 md:grid-cols-4 gap-4">
              <div className="space-y-1">
                <Label required>Start (Y-m-d)</Label>
                <Input type="date" {...register("start")} className="h-9 text-xs" />
                {errors.start && (
                  <span className="text-xs text-red-500">{errors.start.message}</span>
                )}
              </div>
              <div className="space-y-1">
                <Label required>End (Y-m-d)</Label>
                <Input type="date" {...register("end")} className="h-9 text-xs" />
                {errors.end && (
                  <span className="text-xs text-red-500">{errors.end.message}</span>
                )}
              </div>
              <div className="space-y-1">
                <Label>Branch (optional)</Label>
                <Select {...register("branch_id")} options={branchOptions} />
              </div>
              <div className="flex items-end">
                <Button type="submit" isLoading={queueMutation.isPending} className="w-full text-xs">
                  Queue P&amp;L Job
                </Button>
              </div>
            </CardContent>
          </Card>
        </form>

        {jobId && (
          <Card>
            <CardHeader className="py-3 px-4 border-b border-slate-200 dark:border-slate-800">
              <CardTitle className="text-sm font-semibold flex items-center gap-2">
                Job Status
                {job?.status && <StatusBadge status={String(job.status)} />}
                {(job?.status === "queued" || job?.status === "running") && (
                  <Loader2 className="h-3.5 w-3.5 animate-spin text-teal-600" />
                )}
              </CardTitle>
              <CardDescription className="font-mono text-xs break-all">ID: {jobId}</CardDescription>
            </CardHeader>
            <CardContent className="p-4 text-xs space-y-2 text-slate-600 dark:text-slate-400">
              {jobError && (
                <Alert variant="destructive">
                  <AlertTitle>Failed to poll job</AlertTitle>
                  <AlertDescription>{parseProblemDetails(jobErr).detail}</AlertDescription>
                </Alert>
              )}
              {job && (
                <>
                  <div>
                    Type: <span className="font-mono">{job.type}</span>
                  </div>
                  <div>
                    Period: {job.parameters?.start || "—"} → {job.parameters?.end || "—"}
                  </div>
                  <div className="grid grid-cols-2 sm:grid-cols-4 gap-2 font-mono text-[11px]">
                    <span>queued: {job.timestamps?.queued_at || "—"}</span>
                    <span>started: {job.timestamps?.started_at || "—"}</span>
                    <span>completed: {job.timestamps?.completed_at || "—"}</span>
                    <span>expires: {job.timestamps?.expires_at || "—"}</span>
                  </div>
                  {job.status === "failed" && (
                    <Alert variant="destructive">
                      <AlertTitle>Report generation failed</AlertTitle>
                      <AlertDescription>
                        {job.error?.message || job.error?.code || "Unknown error"}
                      </AlertDescription>
                    </Alert>
                  )}
                  {job.status === "expired" && (
                    <Alert variant="warning">
                      <AlertTitle>Report expired</AlertTitle>
                      <AlertDescription>Re-queue a new P&amp;L job for this period.</AlertDescription>
                    </Alert>
                  )}
                </>
              )}
            </CardContent>
          </Card>
        )}

        {job?.status === "completed" && (
          <>
            {resultLoading && <Skeleton className="h-48 w-full" />}
            {resultError && (
              <Alert variant="destructive">
                <AlertTitle>Failed to load result</AlertTitle>
                <AlertDescription>{parseProblemDetails(resultErr).detail}</AlertDescription>
              </Alert>
            )}
            {result && <ProfitAndLossStatement result={result} />}
          </>
        )}
      </div>
    </PermissionGuard>
  );
}
