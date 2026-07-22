"use client";

import * as React from "react";
import { PageHeader } from "@/components/shell/page-header";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Select } from "@/components/ui/select";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Sheet } from "@/components/ui/sheet";
import { StatusBadge } from "@/components/shared/status-badge";
import {
  useUploadBulkCsvMutation,
  useBulkImportStatusQuery,
  useBulkImportErrorsQuery,
} from "@/features/inventory/api/inventory-api";
import { BulkImportType } from "@/types/inventory";
import { parseProblemDetails, ProblemDetails } from "@/lib/api-client";
import { Upload, FileSpreadsheet, RefreshCw, AlertCircle, ShieldAlert } from "lucide-react";
import { toast } from "sonner";

export default function BulkImportsPage() {
  const [importType, setImportType] = React.useState<BulkImportType>("products");
  const [file, setFile] = React.useState<File | null>(null);
  const [activeJobId, setActiveJobId] = React.useState<string | null>(null);
  const [showErrorsSheet, setShowErrorsSheet] = React.useState(false);
  const [apiError, setApiError] = React.useState<ProblemDetails | null>(null);

  const uploadMutation = useUploadBulkCsvMutation();
  const { data: jobStatus, isFetching: isPolling } = useBulkImportStatusQuery(
    activeJobId || "",
    !!activeJobId
  );
  const { data: errorRows, isLoading: isLoadingErrors } = useBulkImportErrorsQuery(
    activeJobId || "",
    showErrorsSheet && !!activeJobId
  );

  const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    if (e.target.files && e.target.files.length > 0) {
      setFile(e.target.files[0]);
    }
  };

  const handleUpload = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!file) {
      toast.error("Please select a CSV file to upload.");
      return;
    }
    setApiError(null);

    try {
      const job = await uploadMutation.mutateAsync({ file, type: importType });
      setActiveJobId(job.id);
      toast.success(`Bulk ${importType} import accepted! Job ID: #${job.id}`);
      setFile(null);
    } catch (err: unknown) {
      const problem = parseProblemDetails(err);
      setApiError(problem);
      if (problem.status === 429) {
        toast.error("Rate limit exceeded: Maximum 10 bulk imports per minute.");
      } else {
        toast.error(problem.title || "Failed to upload CSV file");
      }
    }
  };

  return (
    <div className="space-y-6">
      <PageHeader
        title="Bulk CSV Imports"
        description="Upload CSV files for products or stock adjustments with asynchronous background processing and error inspection."
      />

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        {/* Upload Form */}
        <Card className="lg:col-span-2">
          <CardHeader>
            <CardTitle className="text-base flex items-center gap-2">
              <Upload className="h-5 w-5 text-teal-600" />
              Upload CSV Dataset
            </CardTitle>
            <CardDescription>
              Backend processes files in chunked background jobs. Maximum 10 uploads/min limit applies.
            </CardDescription>
          </CardHeader>
          <CardContent>
            <form onSubmit={handleUpload} className="space-y-6">
              
              {apiError && (
                <Alert variant="destructive">
                  <ShieldAlert className="h-4 w-4" />
                  <AlertTitle>{apiError.title}</AlertTitle>
                  <AlertDescription>{apiError.detail}</AlertDescription>
                </Alert>
              )}

              <div className="space-y-1">
                <label className="text-xs font-semibold text-slate-700 dark:text-slate-300">Import Entity Type</label>
                <Select
                  value={importType}
                  onChange={(e) => setImportType(e.target.value as BulkImportType)}
                  options={[
                    { label: "Products Catalog (Requires product.manage)", value: "products" },
                    { label: "Stock Adjustments (Requires stock.adjust)", value: "stock_adjustments" },
                  ]}
                />
              </div>

              {/* Drag & Drop File Select */}
              <div className="flex flex-col items-center justify-center rounded-lg border-2 border-dashed border-slate-300 p-8 text-center bg-slate-50 dark:border-slate-800 dark:bg-slate-900/50">
                <FileSpreadsheet className="h-10 w-10 text-teal-600 mb-2" />
                <div className="text-sm font-semibold text-slate-900 dark:text-slate-100">
                  {file ? file.name : "Select a .csv file"}
                </div>
                <div className="text-xs text-slate-500 mt-1">
                  {file ? `${(file.size / 1024).toFixed(1)} KB` : "CSV format only, max 10MB"}
                </div>

                <label className="mt-4">
                  <span className="inline-flex h-9 items-center justify-center rounded-md bg-teal-600 px-4 text-xs font-medium text-white transition-colors hover:bg-teal-700 cursor-pointer">
                    Browse CSV File
                  </span>
                  <input
                    type="file"
                    accept=".csv"
                    onChange={handleFileChange}
                    className="hidden"
                  />
                </label>
              </div>

              <div className="flex justify-end">
                <Button type="submit" isLoading={uploadMutation.isPending} disabled={!file}>
                  Upload & Queue Import Job
                </Button>
              </div>

            </form>
          </CardContent>
        </Card>

        {/* Active Job Poller Widget */}
        <Card className="border-slate-200 shadow-sm dark:border-slate-800">
          <CardHeader>
            <CardTitle className="text-xs font-bold uppercase tracking-wider text-slate-500 flex items-center justify-between">
              <span>Active Job Monitor</span>
              {isPolling && <RefreshCw className="h-3.5 w-3.5 text-teal-600 animate-spin" />}
            </CardTitle>
          </CardHeader>
          <CardContent className="space-y-4 text-xs">
            {!activeJobId ? (
              <div className="py-6 text-center text-slate-400">
                No active import job queued in this session.
              </div>
            ) : !jobStatus ? (
              <div className="py-6 text-center text-slate-400">
                Fetching status for job #{activeJobId}...
              </div>
            ) : (
              <div className="space-y-3">
                <div className="flex justify-between items-center pb-2 border-b border-slate-100 dark:border-slate-800">
                  <span className="font-semibold text-slate-700 dark:text-slate-200">Job ID:</span>
                  <span className="font-mono font-bold">#{jobStatus.id}</span>
                </div>

                <div className="flex justify-between items-center">
                  <span className="text-slate-500">Type:</span>
                  <span className="capitalize font-mono">{jobStatus.type}</span>
                </div>

                <div className="flex justify-between items-center">
                  <span className="text-slate-500">Status:</span>
                  <StatusBadge status={jobStatus.status} />
                </div>

                <div className="flex justify-between items-center">
                  <span className="text-slate-500">Total Rows:</span>
                  <span className="font-mono tabular-nums font-semibold">{jobStatus.total_rows || 0}</span>
                </div>

                <div className="flex justify-between items-center text-emerald-600">
                  <span>Processed Rows:</span>
                  <span className="font-mono tabular-nums font-semibold">{jobStatus.processed_rows || 0}</span>
                </div>

                <div className="flex justify-between items-center text-red-600">
                  <span>Failed Rows:</span>
                  <span className="font-mono tabular-nums font-semibold">{jobStatus.failed_rows || 0}</span>
                </div>

                {(jobStatus.failed_rows || 0) > 0 && (
                  <Button
                    size="sm"
                    variant="destructive"
                    onClick={() => setShowErrorsSheet(true)}
                    className="w-full text-xs gap-1 mt-2"
                  >
                    <AlertCircle className="h-3.5 w-3.5" /> Inspect Error Rows
                  </Button>
                )}
              </div>
            )}
          </CardContent>
        </Card>

      </div>

      {/* Errors Drawer Sheet */}
      <Sheet
        isOpen={showErrorsSheet}
        onClose={() => setShowErrorsSheet(false)}
        title={`Failed Import Rows (Job #${activeJobId})`}
        description="Detailed row error messages returned by BulkImportRowErrorResource."
      >
        <div className="space-y-4 py-4">
          {isLoadingErrors ? (
            <div className="py-6 text-center text-xs text-slate-500">Loading error log...</div>
          ) : !errorRows || errorRows.length === 0 ? (
            <div className="py-6 text-center text-xs text-slate-500">No failed row errors logged.</div>
          ) : (
            <Table dense>
              <TableHeader>
                <TableRow>
                  <TableHead>Row #</TableHead>
                  <TableHead>Error Message</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {errorRows.map((err) => (
                  <TableRow key={err.id}>
                    <TableCell className="font-mono font-bold text-xs">Line {err.row_number}</TableCell>
                    <TableCell className="text-xs text-red-600">{err.error_message}</TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          )}

          <Button className="w-full" onClick={() => setShowErrorsSheet(false)}>
            Close Drawer
          </Button>
        </div>
      </Sheet>

    </div>
  );
}
