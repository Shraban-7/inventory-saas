import { ModulePlaceholder } from "@/components/shell/module-placeholder";

export default function CustomersPage() {
  return (
    <ModulePlaceholder
      title="Customers Directory"
      description="Manage customer profiles, billing addresses, and sales history."
      phase="Phase 06"
      permission="invoice.view"
      apiEndpoint="GET /api/v1/customers"
    />
  );
}
