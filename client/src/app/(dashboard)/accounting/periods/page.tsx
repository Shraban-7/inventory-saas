import { ModulePlaceholder } from "@/components/shell/module-placeholder";

export default function AccountingPeriodsPage() {
  return (
    <ModulePlaceholder
      title="Accounting Period Locking"
      description="Lock financial accounting periods to prevent retroactive GL entries or adjustments."
      phase="Phase 08"
      permission="report.view"
      apiEndpoint="PUT /api/v1/accounting-periods/{id}/lock"
    />
  );
}
