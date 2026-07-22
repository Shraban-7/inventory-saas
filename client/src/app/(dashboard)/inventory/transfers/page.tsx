import { ModulePlaceholder } from "@/components/shell/module-placeholder";

export default function StockTransfersPage() {
  return (
    <ModulePlaceholder
      title="Inter-Branch Stock Transfers"
      description="Execute atomic stock transfers between branches with branch-level row concurrency locking."
      phase="Phase 05"
      permission="stock.transfer"
      apiEndpoint="POST /api/v1/stock-transfers"
    />
  );
}
