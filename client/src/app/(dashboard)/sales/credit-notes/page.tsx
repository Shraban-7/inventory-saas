import { ModulePlaceholder } from "@/components/shell/module-placeholder";

export default function CreditNotesPage() {
  return (
    <ModulePlaceholder
      title="Credit Notes & Sales Returns"
      description="Manage customer returns, credit notes, and approval workflows."
      phase="Phase 06"
      permission="invoice.view"
      apiEndpoint="GET /api/v1/credit-notes"
    />
  );
}
