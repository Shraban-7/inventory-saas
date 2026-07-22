"use client";

import Link from "next/link";
import { PageHeader } from "@/components/shell/page-header";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { PermissionGuard } from "@/components/shared/permission-guard";
import { Info, Settings, Lock } from "lucide-react";

/**
 * Settings is intentionally deferred — no Branches/Roles/Users/Audit list APIs.
 * See client/docs/frontend-backend-gap-register.md
 */
export default function SettingsPage() {
  return (
    <PermissionGuard role="Admin" showBanner>
      <div className="space-y-6">
        <PageHeader
          title="Settings"
          description="System configuration surfaces are deferred until backend APIs exist."
        />

        <Alert variant="warning">
          <Info className="h-4 w-4" />
          <AlertTitle>BACKEND CHANGE REQUIRED — settings APIs missing</AlertTitle>
          <AlertDescription>
            This page is <strong>not</strong> a complete settings product. Branches CRUD, roles/users
            admin, and audit log viewer are listed in the gap register and must not be invented in
            the frontend.
          </AlertDescription>
        </Alert>

        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          {[
            {
              title: "Branches",
              need: "GET/POST /api/v1/branches",
              status: "Open gap",
            },
            {
              title: "Roles & users",
              need: "Tenant user/role admin APIs",
              status: "Open gap",
            },
            {
              title: "Audit logs",
              need: "Tenant-facing audit list API",
              status: "Open gap",
            },
            {
              title: "Categories / taxes",
              need: "GET /categories, GET /taxes",
              status: "Open gap",
            },
          ].map((item) => (
            <Card key={item.title}>
              <CardHeader className="pb-2">
                <CardTitle className="text-sm flex items-center gap-2">
                  <Lock className="h-4 w-4 text-slate-400" />
                  {item.title}
                </CardTitle>
                <CardDescription className="text-xs font-mono">{item.need}</CardDescription>
              </CardHeader>
              <CardContent>
                <Badge variant="warning" className="text-[10px]">
                  {item.status}
                </Badge>
              </CardContent>
            </Card>
          ))}
        </div>

        <Card>
          <CardHeader>
            <CardTitle className="text-sm flex items-center gap-2">
              <Settings className="h-4 w-4 text-teal-600" />
              Available admin tools
            </CardTitle>
          </CardHeader>
          <CardContent className="flex flex-wrap gap-2">
            <Link href="/webhooks">
              <Button size="sm" className="text-xs">
                Webhooks
              </Button>
            </Link>
            <Link href="/accounting/periods">
              <Button size="sm" variant="outline" className="text-xs">
                Period locks
              </Button>
            </Link>
          </CardContent>
        </Card>
      </div>
    </PermissionGuard>
  );
}
