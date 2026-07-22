import { ModulePlaceholder } from "@/components/shell/module-placeholder";

export default function VendorBillsPage() {
  return (
    <ModulePlaceholder
      title="Vendor Bills & Payments"
      description="Record vendor bills against GRNs, approve bills for Accounts Payable, and execute payments."
      phase="Phase 07"
      permission="purchase.create"
      apiEndpoint="GET /api/v1/bills"
    />
  );
}
