export const WEBHOOK_EVENTS = [
  "invoice.created",
  "invoice.voided",
  "stock.low",
  "grn.processed",
  "credit_note.approved",
] as const;

export type WebhookEvent = (typeof WEBHOOK_EVENTS)[number];

export interface WebhookEndpoint {
  id: string;
  url: string;
  events: WebhookEvent[] | string[];
  is_active: boolean;
  deactivated_at?: string | null;
  created_at?: string;
  updated_at?: string;
  /** Present only on create response */
  secret?: string;
}

export interface CreateWebhookPayload {
  url: string;
  events: WebhookEvent[];
}
