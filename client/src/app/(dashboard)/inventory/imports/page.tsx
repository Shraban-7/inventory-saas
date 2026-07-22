import { ModulePlaceholder } from "@/components/shell/module-placeholder";

export default function BulkImportsPage() {
  return (
    <ModulePlaceholder
      title="Bulk CSV Imports"
      description="Upload CSV files for products or stock adjustments with async job status & error row inspection."
      phase="Phase 05"
      permission="product.manage"
      apiEndpoint="POST /api/v1/bulk/products"
    />
  );
}
