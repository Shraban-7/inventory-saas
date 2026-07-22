"use client";

import * as React from "react";
import Link from "next/link";
import { PageHeader } from "@/components/shell/page-header";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select } from "@/components/ui/select";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Skeleton } from "@/components/ui/skeleton";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { EmptyState } from "@/components/shared/empty-state";
import { PermissionGuard } from "@/components/shared/permission-guard";
import { useJournalEntriesQuery } from "@/features/accounting/api/accounting-api";
import { JOURNAL_REFERENCE_TYPES, ListJournalEntriesParams } from "@/types/accounting";
import { parseProblemDetails } from "@/lib/api-client";
import {
  ClipboardList,
  Plus,
  Eye,
  Filter,
  ChevronLeft,
  ChevronRight,
  ShieldAlert,
  Info,
} from "lucide-react";

export default function JournalEntriesPage() {
  const [params, setParams] = React.useState<ListJournalEntriesParams>({
    per_page: 25,
    cursor: undefined,
  });

  const { data: response, isLoading, isError, error } = useJournalEntriesQuery(params);
  const entries = response?.data || [];
  const nextCursor = response?.next_cursor;
  const prevCursor = response?.prev_cursor;

  return (
    <PermissionGuard permission="report.view" showBanner>
      <div className="space-y-6">
        <PageHeader
          title="General Ledger Journals"
          description="Append-only double-entry journal history. Posted entries cannot be edited or deleted."
          actions={
            <PermissionGuard role="Accountant">
              <Link href="/accounting/journals/new">
                <Button className="gap-2 text-xs">
                  <Plus className="h-4 w-4" /> New Manual Journal
                </Button>
              </Link>
            </PermissionGuard>
          }
        />

        <Alert>
          <Info className="h-4 w-4" />
          <AlertTitle>Manual posting requires Accountant</AlertTitle>
          <AlertDescription>
            Route middleware is <code>report.view</code>, but{" "}
            <code>JournalEntryController::store</code> also requires the{" "}
            <strong>Accountant</strong> role on the target branch. Admin alone receives 403.
          </AlertDescription>
        </Alert>

        {isError && (
          <Alert variant="destructive">
            <ShieldAlert className="h-4 w-4" />
            <AlertTitle>Failed to load journals</AlertTitle>
            <AlertDescription>{parseProblemDetails(error).detail}</AlertDescription>
          </Alert>
        )}

        <Card>
          <CardHeader className="py-3 px-4 border-b border-slate-200 dark:border-slate-800">
            <CardTitle className="text-sm font-semibold flex items-center gap-2">
              <Filter className="h-4 w-4 text-teal-600" />
              Filters (cursor pagination)
            </CardTitle>
          </CardHeader>
          <CardContent className="p-4 grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div className="space-y-1">
              <Label>Date From</Label>
              <Input
                type="date"
                className="h-9 text-xs"
                value={params.date_from || ""}
                onChange={(e) =>
                  setParams((p) => ({
                    ...p,
                    cursor: undefined,
                    date_from: e.target.value || undefined,
                  }))
                }
              />
            </div>
            <div className="space-y-1">
              <Label>Date To</Label>
              <Input
                type="date"
                className="h-9 text-xs"
                value={params.date_to || ""}
                onChange={(e) =>
                  setParams((p) => ({
                    ...p,
                    cursor: undefined,
                    date_to: e.target.value || undefined,
                  }))
                }
              />
            </div>
            <div className="space-y-1">
              <Label>Reference Type</Label>
              <Select
                value={params.reference_type || ""}
                onChange={(e) =>
                  setParams((p) => ({
                    ...p,
                    cursor: undefined,
                    reference_type: e.target.value || undefined,
                  }))
                }
                options={[
                  { label: "All references", value: "" },
                  ...JOURNAL_REFERENCE_TYPES.map((t) => ({ label: t, value: t })),
                ]}
              />
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="py-3 px-4 border-b border-slate-200 dark:border-slate-800">
            <CardTitle className="text-sm font-semibold flex items-center gap-2">
              <ClipboardList className="h-4 w-4 text-teal-600" />
              Journal Entries
            </CardTitle>
          </CardHeader>
          <CardContent className="p-0">
            <Table dense>
              <TableHeader>
                <TableRow>
                  <TableHead className="w-16">ID</TableHead>
                  <TableHead>Number</TableHead>
                  <TableHead>Posted</TableHead>
                  <TableHead>Branch</TableHead>
                  <TableHead>Reference</TableHead>
                  <TableHead>Description</TableHead>
                  <TableHead className="text-right">Actions</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {isLoading ? (
                  Array.from({ length: 5 }).map((_, idx) => (
                    <TableRow key={idx}>
                      {Array.from({ length: 7 }).map((__, c) => (
                        <TableCell key={c}>
                          <Skeleton className="h-4 w-16" />
                        </TableCell>
                      ))}
                    </TableRow>
                  ))
                ) : entries.length === 0 ? (
                  <TableRow>
                    <TableCell colSpan={7} className="py-12">
                      <EmptyState
                        title="No journal entries"
                        description="No GL entries match the current filters."
                      />
                    </TableCell>
                  </TableRow>
                ) : (
                  entries.map((entry) => (
                    <TableRow key={entry.id}>
                      <TableCell className="font-mono text-xs font-semibold">#{entry.id}</TableCell>
                      <TableCell className="font-mono text-xs font-bold">
                        {entry.journal_entry_number || `JE-${entry.id}`}
                      </TableCell>
                      <TableCell className="font-mono text-xs text-slate-500">
                        {entry.posted_at}
                      </TableCell>
                      <TableCell className="text-xs">
                        {entry.branch?.name || `Branch #${entry.branch_id}`}
                      </TableCell>
                      <TableCell className="text-xs font-mono text-slate-500">
                        {entry.reference_type
                          ? `${entry.reference_type}${entry.reference_id ? ` #${entry.reference_id}` : ""}`
                          : "—"}
                      </TableCell>
                      <TableCell className="text-xs max-w-[240px] truncate">
                        {entry.description || "—"}
                      </TableCell>
                      <TableCell className="text-right">
                        <Link href={`/accounting/journals/${entry.id}`}>
                          <Button size="sm" variant="ghost" className="gap-1 text-xs">
                            <Eye className="h-3.5 w-3.5" /> View
                          </Button>
                        </Link>
                      </TableCell>
                    </TableRow>
                  ))
                )}
              </TableBody>
            </Table>

            {(nextCursor || prevCursor) && (
              <div className="flex items-center justify-between border-t border-slate-200 p-4 dark:border-slate-800">
                <span className="text-xs text-slate-500">Cursor pagination</span>
                <div className="flex gap-2">
                  <Button
                    size="sm"
                    variant="outline"
                    disabled={!prevCursor}
                    onClick={() =>
                      setParams((p) => ({ ...p, cursor: prevCursor || undefined }))
                    }
                    className="gap-1 text-xs"
                  >
                    <ChevronLeft className="h-3.5 w-3.5" /> Previous
                  </Button>
                  <Button
                    size="sm"
                    variant="outline"
                    disabled={!nextCursor}
                    onClick={() =>
                      setParams((p) => ({ ...p, cursor: nextCursor || undefined }))
                    }
                    className="gap-1 text-xs"
                  >
                    Next <ChevronRight className="h-3.5 w-3.5" />
                  </Button>
                </div>
              </div>
            )}
          </CardContent>
        </Card>
      </div>
    </PermissionGuard>
  );
}
