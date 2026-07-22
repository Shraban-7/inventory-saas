import { ModulePlaceholder } from "@/components/shell/module-placeholder";

export default function ChartOfAccountsPage() {
  return (
    <ModulePlaceholder
      title="Chart of Accounts"
      description="View general ledger account tree (Asset, Liability, Equity, Revenue, Expense, COGS) cached via Redis."
      phase="Phase 08"
      permission="report.view"
      apiEndpoint="GET /api/v1/chart-of-accounts"
    />
  );
}
