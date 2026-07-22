"use client";

import Link from "next/link";
import { useParams } from "next/navigation";
import { PageHeader } from "@/components/shell/page-header";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Skeleton } from "@/components/ui/skeleton";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { EmptyState } from "@/components/shared/empty-state";
import { PermissionGuard } from "@/components/shared/permission-guard";
import { AccountTypeBadge } from "@/features/accounting/components/chart-of-accounts-tree";
import { useJournalEntryDetailQuery } from "@/features/accounting/api/accounting-api";
import { formatCurrency } from "@/lib/utils";
import { parseProblemDetails } from "@/lib/api-client";
import { ArrowLeft, ShieldAlert, Lock } from "lucide-react";

export default function JournalEntryDetailPage() {
  const params = useParams();
  const id = Number(params.id);
  const { data: entry, isLoading, isError, error } = useJournalEntryDetailQuery(id);

  const lines = entry?.lines || [];
  const totalDebit = lines.reduce((acc, line) => acc + Number(line.debit || 0), 0);
  const totalCredit = lines.reduce((acc, line) => acc + Number(line.credit || 0), 0);

  return (
    <PermissionGuard permission="report.view" showBanner>
      <div className="space-y-6">
        <PageHeader
          title={
            entry
              ? `Journal ${entry.journal_entry_number || `#${entry.id}`}`
              : "Journal Entry"
          }
          description="Read-only detail. Posted journals are append-only — no edit or delete."
          actions={
            <Link href="/accounting/journals">
              <Button variant="outline" className="gap-2 text-xs">
                <ArrowLeft className="h-4 w-4" /> Back to Journals
              </Button>
            </Link>
          }
        />

        <Alert>
          <Lock className="h-4 w-4" />
          <AlertTitle>Immutable ledger entry</AlertTitle>
          <AlertDescription>
            This UI never offers edit/delete actions for posted journal entries.
          </AlertDescription>
        </Alert>

        {isError && (
          <Alert variant="destructive">
            <ShieldAlert className="h-4 w-4" />
            <AlertTitle>Failed to load journal</AlertTitle>
            <AlertDescription>{parseProblemDetails(error).detail}</AlertDescription>
          </Alert>
        )}

        {isLoading ? (
          <Card>
            <CardContent className="p-6 space-y-3">
              <Skeleton className="h-6 w-48" />
              <Skeleton className="h-32 w-full" />
            </CardContent>
          </Card>
        ) : !entry ? (
          <EmptyState
            title={`Journal #${id} not found`}
            description="The journal entry may be outside your branch authorization."
            actionLabel="Back to Journals"
            onAction={() => {
              window.location.href = "/accounting/journals";
            }}
          />
        ) : (
          <>
            <Card>
              <CardHeader>
                <CardTitle className="text-base">Header</CardTitle>
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 pt-2 text-xs">
                  <div>
                    <div className="text-slate-500">Posted At</div>
                    <div className="font-mono font-semibold">{entry.posted_at}</div>
                  </div>
                  <div>
                    <div className="text-slate-500">Branch</div>
                    <div className="font-semibold">
                      {entry.branch?.name || `Branch #${entry.branch_id}`}
                    </div>
                  </div>
                  <div>
                    <div className="text-slate-500">Reference</div>
                    <div className="font-mono">
                      {entry.reference_type
                        ? `${entry.reference_type}${entry.reference_id ? ` #${entry.reference_id}` : ""}`
                        : "—"}
                    </div>
                  </div>
                  <div>
                    <div className="text-slate-500">Description</div>
                    <div>{entry.description || "—"}</div>
                  </div>
                </div>
              </CardHeader>
              <CardContent className="p-0">
                <Table dense>
                  <TableHeader>
                    <TableRow>
                      <TableHead>Account</TableHead>
                      <TableHead>Type</TableHead>
                      <TableHead>Line Note</TableHead>
                      <TableHead className="text-right">Debit</TableHead>
                      <TableHead className="text-right">Credit</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {lines.map((line) => (
                      <TableRow key={line.id}>
                        <TableCell className="text-xs">
                          <span className="font-mono font-semibold">
                            {line.account?.code || `CoA #${line.coa_id}`}
                          </span>{" "}
                          <span className="text-slate-600">{line.account?.name || ""}</span>
                        </TableCell>
                        <TableCell>
                          {line.account?.type ? (
                            <AccountTypeBadge type={line.account.type} />
                          ) : (
                            "—"
                          )}
                        </TableCell>
                        <TableCell className="text-xs text-slate-500">
                          {line.description || "—"}
                        </TableCell>
                        <TableCell className="text-right font-mono text-xs tabular-nums">
                          {Number(line.debit) > 0 ? formatCurrency(Number(line.debit)) : "—"}
                        </TableCell>
                        <TableCell className="text-right font-mono text-xs tabular-nums">
                          {Number(line.credit) > 0 ? formatCurrency(Number(line.credit)) : "—"}
                        </TableCell>
                      </TableRow>
                    ))}
                    <TableRow>
                      <TableCell colSpan={3} className="text-right text-xs font-semibold">
                        Totals
                      </TableCell>
                      <TableCell className="text-right font-mono text-xs font-bold tabular-nums text-teal-700">
                        {formatCurrency(totalDebit)}
                      </TableCell>
                      <TableCell className="text-right font-mono text-xs font-bold tabular-nums text-teal-700">
                        {formatCurrency(totalCredit)}
                      </TableCell>
                    </TableRow>
                  </TableBody>
                </Table>
              </CardContent>
            </Card>
          </>
        )}
      </div>
    </PermissionGuard>
  );
}
