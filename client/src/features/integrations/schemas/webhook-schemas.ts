import { z } from "zod";
import { WEBHOOK_EVENTS } from "@/types/webhooks";

/** Matches StoreWebhookEndpointRequest */
export const createWebhookSchema = z.object({
  url: z
    .string()
    .min(1, "URL is required")
    .max(2048, "URL must be at most 2048 characters")
    .url("Must be a valid URL"),
  events: z
    .array(z.enum(WEBHOOK_EVENTS))
    .min(1, "Select at least one event"),
});

export type CreateWebhookFormValues = z.infer<typeof createWebhookSchema>;
