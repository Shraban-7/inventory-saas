import { ModulePlaceholder } from "@/components/shell/module-placeholder";

export default function StockAdjustmentsPage() {
  return (
    <ModulePlaceholder
      title="Manual Stock Adjustments"
      description="Perform manual stock corrections (In/Out) with audit reason codes and append-only ledger entries."
      phase="Phase 05"
      permission="stock.adjust"
      apiEndpoint="POST /api/v1/stock-adjustments"
    />
  );
}
