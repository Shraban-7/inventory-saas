import { ModulePlaceholder } from "@/components/shell/module-placeholder";

export default function InvoicesPage() {
  return (
    <ModulePlaceholder
      title="Sales Invoices"
      description="Issue sales invoices, record customer payments, and void invoices with FIFO stock deduction."
      phase="Phase 06"
      permission="invoice.view"
      apiEndpoint="GET /api/v1/invoices"
    />
  );
}
