"use client";

import { PageHeader } from "@/components/shell/page-header";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Skeleton } from "@/components/ui/skeleton";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { EmptyState } from "@/components/shared/empty-state";
import { PermissionGuard } from "@/components/shared/permission-guard";
import { ChartOfAccountsTree } from "@/features/accounting/components/chart-of-accounts-tree";
import { useChartOfAccountsQuery } from "@/features/accounting/api/accounting-api";
import { parseProblemDetails } from "@/lib/api-client";
import { BookOpen, ShieldAlert } from "lucide-react";

export default function ChartOfAccountsPage() {
  const { data, isLoading, isError, error, refetch } = useChartOfAccountsQuery();
  const roots = data || [];

  return (
    <PermissionGuard permission="report.view" showBanner>
      <div className="space-y-6">
        <PageHeader
          title="Chart of Accounts"
          description="Read-only general ledger account tree. Types: asset, liability, equity, revenue, expense, cogs."
        />

        {isError && (
          <Alert variant="destructive">
            <ShieldAlert className="h-4 w-4" />
            <AlertTitle>Failed to load chart of accounts</AlertTitle>
            <AlertDescription>{parseProblemDetails(error).detail}</AlertDescription>
          </Alert>
        )}

        <Card>
          <CardHeader className="py-3 px-4 border-b border-slate-200 dark:border-slate-800">
            <CardTitle className="text-sm font-semibold flex items-center gap-2">
              <BookOpen className="h-4 w-4 text-teal-600" />
              Account Hierarchy
            </CardTitle>
          </CardHeader>
          <CardContent className="p-2">
            {isLoading ? (
              <div className="space-y-2 p-4">
                {Array.from({ length: 8 }).map((_, i) => (
                  <Skeleton key={i} className="h-6 w-full" />
                ))}
              </div>
            ) : roots.length === 0 ? (
              <div className="py-10">
                <EmptyState
                  title="No accounts found"
                  description="The tenant chart of accounts is empty."
                  actionLabel="Retry"
                  onAction={() => refetch()}
                />
              </div>
            ) : (
              <ChartOfAccountsTree roots={roots} />
            )}
          </CardContent>
        </Card>
      </div>
    </PermissionGuard>
  );
}
