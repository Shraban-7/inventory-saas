"use client";

import * as React from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { PageHeader } from "@/components/shell/page-header";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Dialog } from "@/components/ui/dialog";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Skeleton } from "@/components/ui/skeleton";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { EmptyState } from "@/components/shared/empty-state";
import { PermissionGuard } from "@/components/shared/permission-guard";
import {
  useWebhooksQuery,
  useCreateWebhookMutation,
  useDeactivateWebhookMutation,
} from "@/features/integrations/api/webhooks-api";
import {
  createWebhookSchema,
  CreateWebhookFormValues,
} from "@/features/integrations/schemas/webhook-schemas";
import { WEBHOOK_EVENTS, WebhookEndpoint } from "@/types/webhooks";
import { parseProblemDetails, ProblemDetails } from "@/lib/api-client";
import { useAuthStore } from "@/lib/stores/auth-store";
import {
  Webhook,
  Plus,
  Trash2,
  ShieldAlert,
  Info,
  Copy,
  Check,
  EyeOff,
} from "lucide-react";
import { toast } from "sonner";

export default function WebhooksPage() {
  const isAdmin = useAuthStore((s) => s.hasRole("Admin"));
  const { data: endpoints = [], isLoading, isError, error, refetch } = useWebhooksQuery(isAdmin);
  const createMutation = useCreateWebhookMutation();
  const deactivateMutation = useDeactivateWebhookMutation();

  const [createOpen, setCreateOpen] = React.useState(false);
  const [apiError, setApiError] = React.useState<ProblemDetails | null>(null);
  const [createdSecret, setCreatedSecret] = React.useState<{
    endpoint: WebhookEndpoint;
    secret: string;
  } | null>(null);
  const [copied, setCopied] = React.useState(false);
  const [deactivateTarget, setDeactivateTarget] = React.useState<WebhookEndpoint | null>(null);

  const {
    register,
    handleSubmit,
    reset,
    watch,
    setValue,
    formState: { errors },
  } = useForm({
    resolver: zodResolver(createWebhookSchema),
    defaultValues: {
      url: "",
      events: [] as CreateWebhookFormValues["events"],
    },
  });

  const selectedEvents = watch("events") || [];

  const toggleEvent = (event: (typeof WEBHOOK_EVENTS)[number]) => {
    const current = selectedEvents as string[];
    if (current.includes(event)) {
      setValue(
        "events",
        current.filter((e) => e !== event) as CreateWebhookFormValues["events"],
        { shouldValidate: true },
      );
    } else {
      setValue("events", [...current, event] as CreateWebhookFormValues["events"], {
        shouldValidate: true,
      });
    }
  };

  const onCreate = async (values: CreateWebhookFormValues) => {
    setApiError(null);
    try {
      const created = await createMutation.mutateAsync({
        url: values.url,
        events: values.events,
      });
      if (!created.secret) {
        toast.error("Endpoint created but secret was not returned.");
      } else {
        setCreatedSecret({ endpoint: created, secret: created.secret });
      }
      reset({ url: "", events: [] });
      setCreateOpen(false);
      refetch();
      toast.success("Webhook endpoint created.");
    } catch (err: unknown) {
      const problem = parseProblemDetails(err);
      setApiError(problem);
      toast.error(problem.title || "Failed to create webhook");
    }
  };

  const onConfirmDeactivate = async () => {
    if (!deactivateTarget) return;
    try {
      await deactivateMutation.mutateAsync(deactivateTarget.id);
      toast.success("Webhook deactivated.");
      setDeactivateTarget(null);
      refetch();
    } catch (err: unknown) {
      toast.error(parseProblemDetails(err).title || "Failed to deactivate webhook");
    }
  };

  const copySecret = async () => {
    if (!createdSecret) return;
    try {
      await navigator.clipboard.writeText(createdSecret.secret);
      setCopied(true);
      toast.success("Secret copied to clipboard");
      setTimeout(() => setCopied(false), 2000);
    } catch {
      toast.error("Could not copy secret");
    }
  };

  return (
    <PermissionGuard role="Admin" showBanner>
      <div className="space-y-6">
        <PageHeader
          title="Webhooks"
          description="Admin-only outbound webhook endpoints. Signing secret is shown once at creation."
          actions={
            <Button
              onClick={() => {
                setApiError(null);
                setCreateOpen(true);
              }}
              className="gap-2 text-xs"
            >
              <Plus className="h-4 w-4" /> Add Endpoint
            </Button>
          }
        />

        <Alert>
          <Info className="h-4 w-4" />
          <AlertTitle>Endpoints only — no delivery history API</AlertTitle>
          <AlertDescription>
            <code>routes/api.php</code> exposes list / create / deactivate. There is no delivery
            log UI. Events: {WEBHOOK_EVENTS.join(", ")}.
          </AlertDescription>
        </Alert>

        {isError && (
          <Alert variant="destructive">
            <ShieldAlert className="h-4 w-4" />
            <AlertTitle>Failed to load webhooks</AlertTitle>
            <AlertDescription>{parseProblemDetails(error).detail}</AlertDescription>
          </Alert>
        )}

        <Card>
          <CardHeader className="py-3 px-4 border-b border-slate-200 dark:border-slate-800">
            <CardTitle className="text-sm font-semibold flex items-center gap-2">
              <Webhook className="h-4 w-4 text-teal-600" />
              Active endpoints ({endpoints.length})
            </CardTitle>
          </CardHeader>
          <CardContent className="p-0">
            <Table dense>
              <TableHeader>
                <TableRow>
                  <TableHead>URL</TableHead>
                  <TableHead>Events</TableHead>
                  <TableHead>Created</TableHead>
                  <TableHead className="text-right">Actions</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {isLoading ? (
                  Array.from({ length: 3 }).map((_, i) => (
                    <TableRow key={i}>
                      {Array.from({ length: 4 }).map((__, c) => (
                        <TableCell key={c}>
                          <Skeleton className="h-4 w-24" />
                        </TableCell>
                      ))}
                    </TableRow>
                  ))
                ) : endpoints.length === 0 ? (
                  <TableRow>
                    <TableCell colSpan={4} className="py-12">
                      <EmptyState
                        title="No active webhooks"
                        description="Create an HTTPS public endpoint to receive inventory SaaS events."
                        actionLabel="Add Endpoint"
                        onAction={() => setCreateOpen(true)}
                      />
                    </TableCell>
                  </TableRow>
                ) : (
                  endpoints.map((ep) => (
                    <TableRow key={ep.id}>
                      <TableCell className="font-mono text-xs max-w-70 truncate" title={ep.url}>
                        {ep.url}
                      </TableCell>
                      <TableCell>
                        <div className="flex flex-wrap gap-1">
                          {(ep.events || []).map((ev) => (
                            <Badge key={ev} variant="secondary" className="text-[10px] font-mono">
                              {ev}
                            </Badge>
                          ))}
                        </div>
                      </TableCell>
                      <TableCell className="text-xs text-slate-500">
                        {ep.created_at ? new Date(ep.created_at).toLocaleString() : "—"}
                      </TableCell>
                      <TableCell className="text-right">
                        <Button
                          size="sm"
                          variant="ghost"
                          className="gap-1 text-xs text-red-600"
                          onClick={() => setDeactivateTarget(ep)}
                          aria-label={`Deactivate webhook ${ep.url}`}
                        >
                          <Trash2 className="h-3.5 w-3.5" /> Deactivate
                        </Button>
                      </TableCell>
                    </TableRow>
                  ))
                )}
              </TableBody>
            </Table>
          </CardContent>
        </Card>

        {/* Create dialog */}
        <Dialog
          isOpen={createOpen}
          onClose={() => setCreateOpen(false)}
          title="Create webhook endpoint"
          description="URL must be a public HTTPS destination (SSRF checks apply on the backend)."
          className="max-w-xl"
        >
          <form onSubmit={handleSubmit(onCreate)} className="space-y-4 pt-2">
            {apiError && (
              <Alert variant="destructive">
                <ShieldAlert className="h-4 w-4" />
                <AlertTitle>{apiError.title}</AlertTitle>
                <AlertDescription>{apiError.detail}</AlertDescription>
              </Alert>
            )}

            <div className="space-y-1">
              <Label required htmlFor="webhook-url">
                Destination URL
              </Label>
              <Input
                id="webhook-url"
                {...register("url")}
                placeholder="https://hooks.example.com/inventory"
                className="font-mono text-xs"
              />
              {errors.url && (
                <span className="text-xs text-red-500" role="alert">
                  {errors.url.message}
                </span>
              )}
            </div>

            <fieldset className="space-y-2">
              <legend className="text-sm font-medium">
                Events <span className="text-red-500">*</span>
              </legend>
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
                {WEBHOOK_EVENTS.map((event) => {
                  const checked = (selectedEvents as string[]).includes(event);
                  return (
                    <label
                      key={event}
                      className="flex items-center gap-2 rounded-md border border-slate-200 px-3 py-2 text-xs cursor-pointer hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-900"
                    >
                      <input
                        type="checkbox"
                        className="rounded border-slate-300 text-teal-600 focus:ring-teal-600"
                        checked={checked}
                        onChange={() => toggleEvent(event)}
                      />
                      <span className="font-mono">{event}</span>
                    </label>
                  );
                })}
              </div>
              {errors.events && (
                <span className="text-xs text-red-500" role="alert">
                  {errors.events.message as string}
                </span>
              )}
            </fieldset>

            <div className="flex justify-end gap-2 pt-2">
              <Button type="button" variant="outline" onClick={() => setCreateOpen(false)}>
                Cancel
              </Button>
              <Button type="submit" isLoading={createMutation.isPending}>
                Create Endpoint
              </Button>
            </div>
          </form>
        </Dialog>

        {/* Secret once */}
        <Dialog
          isOpen={!!createdSecret}
          onClose={() => setCreatedSecret(null)}
          title="Signing secret — copy now"
          description="This secret is shown only once. Store it securely; it cannot be retrieved again."
          className="max-w-lg"
        >
          {createdSecret && (
            <div className="space-y-4 pt-2">
              <Alert variant="warning">
                <EyeOff className="h-4 w-4" />
                <AlertTitle>One-time reveal</AlertTitle>
                <AlertDescription>
                  Closing this dialog discards the secret from the UI. Endpoint ID:{" "}
                  <code className="font-mono text-[11px]">{createdSecret.endpoint.id}</code>
                </AlertDescription>
              </Alert>
              <div className="rounded-md border border-slate-200 bg-slate-50 p-3 dark:border-slate-800 dark:bg-slate-900">
                <code className="break-all text-xs font-mono">{createdSecret.secret}</code>
              </div>
              <div className="flex justify-end gap-2">
                <Button type="button" variant="outline" className="gap-1 text-xs" onClick={copySecret}>
                  {copied ? <Check className="h-3.5 w-3.5" /> : <Copy className="h-3.5 w-3.5" />}
                  {copied ? "Copied" : "Copy secret"}
                </Button>
                <Button type="button" onClick={() => setCreatedSecret(null)} className="text-xs">
                  I have saved the secret
                </Button>
              </div>
            </div>
          )}
        </Dialog>

        {/* Deactivate confirm */}
        <Dialog
          isOpen={!!deactivateTarget}
          onClose={() => setDeactivateTarget(null)}
          title="Deactivate webhook?"
          description="The endpoint will be marked inactive and stop receiving events."
        >
          <div className="space-y-4 pt-2">
            <p className="text-xs font-mono break-all text-slate-600">{deactivateTarget?.url}</p>
            <div className="flex justify-end gap-2">
              <Button type="button" variant="outline" onClick={() => setDeactivateTarget(null)}>
                Cancel
              </Button>
              <Button
                type="button"
                variant="destructive"
                isLoading={deactivateMutation.isPending}
                onClick={onConfirmDeactivate}
              >
                Deactivate
              </Button>
            </div>
          </div>
        </Dialog>
      </div>
    </PermissionGuard>
  );
}
