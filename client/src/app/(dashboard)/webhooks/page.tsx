import { ModulePlaceholder } from "@/components/shell/module-placeholder";

export default function WebhooksPage() {
  return (
    <ModulePlaceholder
      title="Webhooks Subscriptions"
      description="Manage webhook endpoints, event subscriptions (invoice.created, stock.low), and secret keys."
      phase="Phase 10"
      apiEndpoint="GET /api/v1/webhooks"
    />
  );
}
