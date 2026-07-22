import { ModulePlaceholder } from "@/components/shell/module-placeholder";

export default function PurchaseOrdersPage() {
  return (
    <ModulePlaceholder
      title="Purchase Orders"
      description="Create, confirm, and cancel vendor purchase orders."
      phase="Phase 07"
      permission="purchase.create"
      apiEndpoint="GET /api/v1/purchase-orders"
    />
  );
}
