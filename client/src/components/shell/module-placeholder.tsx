import * as React from "react";
import { PageHeader } from "./page-header";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { EmptyState } from "@/components/shared/empty-state";
import { Layers } from "lucide-react";

interface ModulePlaceholderProps {
  title: string;
  description: string;
  phase: string;
  permission?: string;
  apiEndpoint?: string;
}

export function ModulePlaceholder({
  title,
  description,
  phase,
  apiEndpoint,
}: ModulePlaceholderProps) {
  return (
    <div className="space-y-6">
      <PageHeader title={title} description={description} />

      <Card>
        <CardHeader>
          <CardTitle className="text-base flex items-center gap-2">
            <Layers className="h-4 w-4 text-teal-600" />
            Module Shell Scaffold ({phase})
          </CardTitle>
          <CardDescription>
            {apiEndpoint ? `Connected to API: ${apiEndpoint}` : "Frontend route active and mapped to Information Architecture."}
          </CardDescription>
        </CardHeader>
        <CardContent>
          <EmptyState
            title={`${title} Module Scaffold Active`}
            description={`Route target active. Domain UI logic, Zod validation, and TanStack Query integration will be wired in ${phase}.`}
          />
        </CardContent>
      </Card>
    </div>
  );
}
