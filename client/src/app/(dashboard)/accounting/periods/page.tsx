"use client";

import * as React from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { PageHeader } from "@/components/shell/page-header";
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Dialog } from "@/components/ui/dialog";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { PermissionGuard } from "@/components/shared/permission-guard";
import {
  useLockAccountingPeriodMutation,
  accountingMutationToast,
} from "@/features/accounting/api/accounting-api";
import {
  lockPeriodSchema,
  LockPeriodFormValues,
} from "@/features/accounting/schemas/accounting-schemas";
import { AccountingPeriod } from "@/types/accounting";
import { parseProblemDetails, ProblemDetails } from "@/lib/api-client";
import { Lock, ShieldAlert, Info, AlertTriangle } from "lucide-react";
import { toast } from "sonner";

/**
 * GET /accounting-periods is missing (BACKEND CHANGE REQUIRED).
 * This Admin stub locks by known period ID via PUT .../lock only.
 */
export default function AccountingPeriodsPage() {
  const lockMutation = useLockAccountingPeriodMutation();
  const [confirmOpen, setConfirmOpen] = React.useState(false);
  const [pendingId, setPendingId] = React.useState<number | null>(null);
  const [apiError, setApiError] = React.useState<ProblemDetails | null>(null);
  const [lastLocked, setLastLocked] = React.useState<AccountingPeriod | null>(null);

  const {
    register,
    handleSubmit,
    formState: { errors },
    getValues,
  } = useForm({
    resolver: zodResolver(lockPeriodSchema),
    defaultValues: { accounting_period_id: 1 },
  });

  const onRequestLock = (values: LockPeriodFormValues) => {
    setApiError(null);
    setPendingId(Number(values.accounting_period_id));
    setConfirmOpen(true);
  };

  const onConfirmLock = async () => {
    if (!pendingId) return;
    setApiError(null);
    try {
      const period = await lockMutation.mutateAsync(pendingId);
      setLastLocked(period);
      setConfirmOpen(false);
      toast.success(`Period #${period.id} (${period.year}-${String(period.month).padStart(2, "0")}) locked.`);
    } catch (err: unknown) {
      const problem = parseProblemDetails(err);
      setApiError(problem);
      setConfirmOpen(false);
      toast.error(accountingMutationToast(problem));
    }
  };

  return (
    <PermissionGuard role="Admin" showBanner>
      <div className="space-y-6">
        <PageHeader
          title="Accounting Period Locks"
          description="Irreversible lock of a financial period. Admin tenant-wide role required."
        />

        <Alert variant="warning">
          <Info className="h-4 w-4" />
          <AlertTitle>BACKEND CHANGE REQUIRED — period list missing</AlertTitle>
          <AlertDescription>
            There is no <code>GET /api/v1/accounting-periods</code>. This page only calls{" "}
            <code>PUT /api/v1/accounting-periods/&#123;id&#125;/lock</code> when you already know
            the period ID (from DB/seed/ops). A full period table UI waits on the list API.
          </AlertDescription>
        </Alert>

        {apiError && (
          <Alert variant="destructive">
            <ShieldAlert className="h-4 w-4" />
            <AlertTitle>{apiError.title}</AlertTitle>
            <AlertDescription>{apiError.detail}</AlertDescription>
          </Alert>
        )}

        {lastLocked && (
          <Alert variant="success">
            <Lock className="h-4 w-4" />
            <AlertTitle>Period locked</AlertTitle>
            <AlertDescription>
              ID #{lastLocked.id} · {lastLocked.year}-{String(lastLocked.month).padStart(2, "0")} ·
              locked_at {lastLocked.locked_at || "now"} ·{" "}
              <Badge variant="destructive" className="ml-1 text-[10px]">
                is_locked={String(lastLocked.is_locked)}
              </Badge>
            </AlertDescription>
          </Alert>
        )}

        <Card>
          <CardHeader>
            <CardTitle className="text-base flex items-center gap-2">
              <Lock className="h-4 w-4 text-teal-600" />
              Lock by Period ID
            </CardTitle>
            <CardDescription>
              Confirm before submit — locks cannot be undone from this UI.
            </CardDescription>
          </CardHeader>
          <CardContent>
            <form onSubmit={handleSubmit(onRequestLock)} className="flex flex-col sm:flex-row gap-3 items-end max-w-lg">
              <div className="space-y-1 flex-1 w-full">
                <Label required>Accounting Period ID</Label>
                <Input
                  type="number"
                  {...register("accounting_period_id")}
                  placeholder="e.g. 12"
                  className="h-9 text-xs font-mono"
                />
                {errors.accounting_period_id && (
                  <span className="text-xs text-red-500">
                    {errors.accounting_period_id.message}
                  </span>
                )}
              </div>
              <Button type="submit" variant="destructive" className="gap-2 text-xs">
                <AlertTriangle className="h-3.5 w-3.5" /> Lock Period…
              </Button>
            </form>
          </CardContent>
        </Card>

        <Dialog
          isOpen={confirmOpen}
          onClose={() => setConfirmOpen(false)}
          title="Irreversible period lock"
          description={`You are about to lock accounting period ID ${pendingId ?? getValues("accounting_period_id")}.`}
        >
          <div className="space-y-4 pt-2">
            <Alert variant="destructive">
              <AlertTriangle className="h-4 w-4" />
              <AlertTitle>This cannot be undone in the product UI</AlertTitle>
              <AlertDescription>
                After lock, posting journals or inventory/sales mutations dated in this period will
                fail with <code>urn:problem:accounting-period-locked</code>.
              </AlertDescription>
            </Alert>
            <div className="flex justify-end gap-2">
              <Button type="button" variant="outline" onClick={() => setConfirmOpen(false)}>
                Cancel
              </Button>
              <Button
                type="button"
                variant="destructive"
                isLoading={lockMutation.isPending}
                onClick={onConfirmLock}
              >
                Confirm Lock
              </Button>
            </div>
          </div>
        </Dialog>
      </div>
    </PermissionGuard>
  );
}
