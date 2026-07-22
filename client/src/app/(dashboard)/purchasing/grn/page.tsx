import { ModulePlaceholder } from "@/components/shell/module-placeholder";

export default function GoodsReceiptPage() {
  return (
    <ModulePlaceholder
      title="Goods Receipt Notes (GRN)"
      description="Record received shipments from suppliers, log lot unit costs, and update GRNI liabilities."
      phase="Phase 07"
      permission="purchase.receive"
      apiEndpoint="GET /api/v1/goods-receipt-notes"
    />
  );
}
