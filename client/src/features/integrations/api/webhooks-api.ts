import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { apiClient } from "@/lib/api-client";
import { CreateWebhookPayload, WebhookEndpoint } from "@/types/webhooks";

export const WEBHOOK_QUERY_KEYS = {
  all: ["webhooks"] as const,
  list: () => [...WEBHOOK_QUERY_KEYS.all, "list"] as const,
};

export async function fetchWebhooks(): Promise<WebhookEndpoint[]> {
  const response = await apiClient.get<{ data: WebhookEndpoint[] }>("/webhooks");
  return response.data.data;
}

export async function createWebhook(
  payload: CreateWebhookPayload,
): Promise<WebhookEndpoint> {
  const response = await apiClient.post<{ data: WebhookEndpoint }>("/webhooks", payload);
  return response.data.data;
}

export async function deactivateWebhook(id: string): Promise<void> {
  await apiClient.delete(`/webhooks/${id}`);
}

export function useWebhooksQuery(enabled = true) {
  return useQuery({
    queryKey: WEBHOOK_QUERY_KEYS.list(),
    queryFn: fetchWebhooks,
    enabled,
  });
}

export function useCreateWebhookMutation() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: createWebhook,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: WEBHOOK_QUERY_KEYS.all });
    },
  });
}

export function useDeactivateWebhookMutation() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: deactivateWebhook,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: WEBHOOK_QUERY_KEYS.all });
    },
  });
}
