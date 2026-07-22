import { ModulePlaceholder } from "@/components/shell/module-placeholder";

export default function JournalEntriesPage() {
  return (
    <ModulePlaceholder
      title="General Ledger Journal Entries"
      description="Inspect cursor-paginated append-only double-entry GL transactions and post balanced manual journals."
      phase="Phase 08"
      permission="report.view"
      apiEndpoint="GET /api/v1/journal-entries"
    />
  );
}
