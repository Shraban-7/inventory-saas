import { ModulePlaceholder } from "@/components/shell/module-placeholder";

export default function ProductsPage() {
  return (
    <ModulePlaceholder
      title="Products & Variant Catalog"
      description="Manage product headers, costing methods (FIFO/AVCO), SKUs, barcodes, and variant combinations."
      phase="Phase 04"
      apiEndpoint="GET /api/v1/products"
    />
  );
}
