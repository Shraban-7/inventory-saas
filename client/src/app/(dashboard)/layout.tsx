"use client";

import * as React from "react";
import { useRouter } from "next/navigation";
import { Topbar } from "@/components/shell/topbar";
import { Sidebar } from "@/components/shell/sidebar";
import { CommandPalette } from "@/components/shell/command-palette";
import { QueryProvider } from "@/providers/query-provider";
import { useAuthStore } from "@/lib/stores/auth-store";
import { Skeleton } from "@/components/ui/skeleton";

export default function DashboardLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  const router = useRouter();
  const { isAuthenticated } = useAuthStore();
  const [isMounted, setIsMounted] = React.useState(false);

  React.useEffect(() => {
    setIsMounted(true);
    if (!isAuthenticated) {
      router.push("/login");
    }
  }, [isAuthenticated, router]);

  if (!isMounted) {
    return null;
  }

  if (!isAuthenticated) {
    return (
      <div className="flex h-screen w-screen items-center justify-center bg-slate-950 p-6">
        <div className="space-y-4 text-center max-w-sm">
          <Skeleton className="h-12 w-12 rounded-full mx-auto" />
          <Skeleton className="h-4 w-48 mx-auto" />
          <p className="text-xs text-slate-400">Redirecting to login portal...</p>
        </div>
      </div>
    );
  }

  return (
    <QueryProvider>
      <div className="flex min-h-screen flex-col bg-slate-50 dark:bg-slate-950">
        <Topbar />
        <div className="flex flex-1 overflow-hidden">
          <Sidebar />
          <main className="flex-1 overflow-y-auto p-6 md:p-8">
            <div className="mx-auto max-w-7xl">
              {children}
            </div>
          </main>
        </div>
        <CommandPalette />
      </div>
    </QueryProvider>
  );
}
