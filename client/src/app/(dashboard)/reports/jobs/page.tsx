import { ModulePlaceholder } from "@/components/shell/module-placeholder";

export default function ReportJobsPage() {
  return (
    <ModulePlaceholder
      title="Report Job Status Monitor"
      description="Monitor queued, running, completed, or failed reporting jobs."
      phase="Phase 09"
      permission="report.view"
      apiEndpoint="GET /api/v1/reports/jobs/{id}"
    />
  );
}
