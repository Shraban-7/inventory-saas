import { ModulePlaceholder } from "@/components/shell/module-placeholder";

export default function StockOverviewPage() {
  return (
    <ModulePlaceholder
      title="Multi-Branch Stock Levels"
      description="Inspect on-hand inventory levels, reorder points, and lot quantities across branches."
      phase="Phase 05"
      apiEndpoint="GET /api/v1/products/{id}/stock"
    />
  );
}
