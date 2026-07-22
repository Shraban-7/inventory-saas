import { ModulePlaceholder } from "@/components/shell/module-placeholder";

export default function SuppliersPage() {
  return (
    <ModulePlaceholder
      title="Suppliers Directory"
      description="Manage vendor details, contacts, and purchasing history."
      phase="Phase 07"
      permission="purchase.create"
      apiEndpoint="GET /api/v1/suppliers"
    />
  );
}
