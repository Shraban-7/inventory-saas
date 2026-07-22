"use client";

import * as React from "react";
import { useAuthStore } from "@/lib/stores/auth-store";
import { RoleName } from "@/types/auth";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { ShieldAlert } from "lucide-react";

interface PermissionGuardProps {
  permission?: string;
  role?: RoleName;
  children: React.ReactNode;
  fallback?: React.ReactNode;
  showBanner?: boolean;
}

/**
 * Guards UI elements based on backend Spatie permissions (`can:invoice.view`, etc.) or user Roles.
 */
export function PermissionGuard({
  permission,
  role,
  children,
  fallback,
  showBanner = false,
}: PermissionGuardProps) {
  const hasPermission = useAuthStore((state) => state.hasPermission);
  const hasRole = useAuthStore((state) => state.hasRole);

  const isAllowed = React.useMemo(() => {
    if (role && !hasRole(role)) return false;
    if (permission && !hasPermission(permission)) return false;
    return true;
  }, [permission, role, hasPermission, hasRole]);

  if (isAllowed) {
    return <>{children}</>;
  }

  if (fallback) {
    return <>{fallback}</>;
  }

  if (showBanner) {
    return (
      <Alert variant="destructive" className="my-4">
        <ShieldAlert className="h-4 w-4" />
        <AlertTitle>Access Restricted</AlertTitle>
        <AlertDescription>
          You do not have the required permission ({permission || role}) to perform this action or view this resource.
        </AlertDescription>
      </Alert>
    );
  }

  return null;
}
